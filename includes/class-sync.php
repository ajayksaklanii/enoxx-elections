<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* === SENDER (English site) === */
if ( ! ENX_IS_HI ) {

    add_action( 'wp_ajax_enx_sync_single', function() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'enx_admin' ) ) wp_send_json_error('nonce');
        if ( ! current_user_can('administrator') && ! current_user_can('editor') ) wp_send_json_error('permission');
        $id = absint( $_POST['post_id'] ?? 0 );
        $r  = enx_do_sync( $id );
        if ( is_wp_error($r) ) wp_send_json_error( $r->get_error_message() );
        wp_send_json_success( $r );
    } );

    add_action( 'wp_ajax_enx_bulk_sync', function() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'enx_sync' ) ) wp_send_json_error('nonce');
        if ( ! current_user_can('administrator') ) wp_send_json_error('permission');
        $ids = get_posts( ['post_type'=>'candidate','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids'] );
        $ok = 0; $fail = 0;
        foreach ( $ids as $id ) { $r = enx_do_sync($id); is_wp_error($r) ? $fail++ : $ok++; }
        wp_send_json_success( ['message' => "Synced: {$ok} OK, {$fail} failed."] );
    } );

    function enx_do_sync( $post_id ) {
        if ( get_post_type($post_id) !== 'candidate' ) return new WP_Error('invalid','Not a candidate');
        $photo_url = enx_photo_url_raw( $post_id, 'full' );
        $meta = [];
        foreach ( enx_meta_fields() as $k ) { $v = get_post_meta($post_id,$k,true); if($v!==''&&$v!==false) $meta[$k]=$v; }
        $payload = ['source_post_id'=>$post_id,'title'=>get_the_title($post_id),'meta'=>$meta,'photo_url'=>$photo_url];
        $hi_url  = rtrim( get_option('enx_hi_site_url','https://enoxxnews.in'), '/' );
        $api_key = get_option('enx_api_key','enoxx-secret-key');
        $response = wp_remote_post( $hi_url.'/wp-json/enx/v1/sync', [
            'headers'=>['Content-Type'=>'application/json','X-API-KEY'=>$api_key],
            'body'=>wp_json_encode($payload),'timeout'=>30,
        ]);
        if ( is_wp_error($response) ) return $response;
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response),true);
        if($code>=200&&$code<300) return $body;
        return new WP_Error('sync_failed',"HTTP {$code}: ".wp_remote_retrieve_body($response));
    }
}

/* === RECEIVER (Hindi site) === */
if ( ENX_IS_HI ) {

    add_action( 'rest_api_init', function() {
        register_rest_route('enx/v1','/sync',[
            'methods'=>'POST','callback'=>'enx_receive_sync','permission_callback'=>'__return_true',
        ]);
    });

    function enx_receive_sync( $request ) {
        if ( $request->get_header('x-api-key') !== get_option('enx_api_key','enoxx-secret-key') )
            return new WP_REST_Response(['status'=>'unauthorized'],401);
        $data = $request->get_json_params();
        if ( empty($data['source_post_id']) || empty($data['title']) )
            return new WP_REST_Response(['status'=>'error','message'=>'Missing fields'],400);
        $source_id = (int)$data['source_post_id'];
        $meta      = is_array($data['meta'])?$data['meta']:[];
        $existing  = get_posts(['post_type'=>'candidate','post_status'=>'any','posts_per_page'=>1,'fields'=>'ids',
            'meta_query'=>[['key'=>'en_source_post_id','value'=>(string)$source_id]]]);
        $mode='created';
        if(!empty($existing[0])){
            $post_id=$existing[0];
            wp_update_post(['ID'=>$post_id,'post_title'=>sanitize_text_field($data['title']),'post_status'=>'publish']);
            $mode='updated';
        }else{
            $post_id=wp_insert_post(['post_type'=>'candidate','post_title'=>sanitize_text_field($data['title']),'post_status'=>'publish'],true);
            if(is_wp_error($post_id)) return new WP_REST_Response(['status'=>'error','message'=>$post_id->get_error_message()],500);
        }
        update_post_meta($post_id,'en_source_post_id',(string)$source_id);
        foreach(enx_meta_fields() as $k){ if(isset($meta[$k])) update_post_meta($post_id,$k,$meta[$k]); }
        if(!empty($data['photo_url'])){
            require_once ABSPATH.'wp-admin/includes/file.php';
            require_once ABSPATH.'wp-admin/includes/media.php';
            require_once ABSPATH.'wp-admin/includes/image.php';
            $tmp=download_url($data['photo_url']);
            if(!is_wp_error($tmp)){
                $file=['name'=>basename(parse_url($data['photo_url'],PHP_URL_PATH)),'tmp_name'=>$tmp];
                $att_id=media_handle_sideload($file,$post_id);
                if(!is_wp_error($att_id)){update_post_meta($post_id,'candidate_photo_id',$att_id);set_post_thumbnail($post_id,$att_id);}
                else @unlink($tmp);
            }
        }
        return new WP_REST_Response(['status'=>'success','mode'=>$mode,'post_id'=>$post_id],200);
    }
}
