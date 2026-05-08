<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════════════════════
   CANDIDATE SEO — bilingual titles + descriptions
   ══════════════════════════════════════════════════════════════════════ */

function enx_build_candidate_seo_title( $post_id ) {
    $hi      = ENX_IS_HI;
    $name    = $hi
        ? ( get_post_meta($post_id,'candidate_name_hi',true) ?: get_post_meta($post_id,'candidate_name_text',true) )
        : get_post_meta($post_id,'candidate_name_text',true);
    $name    = $name ?: get_the_title($post_id);
    $lang    = $hi ? 'hi' : 'en';
    $et      = get_post_meta($post_id,'election_type',true) ?: 'panchayat';
    $contest = enx_get_contest_label_for_post($post_id,$lang);
    $loc     = enx_resolve_location($post_id,$lang);
    $c_slug  = get_post_meta($post_id,'contest_slug',true);
    $is_zp   = in_array($c_slug,['zila-parishad','zila-parishad-member'],true);
    $is_bdc  = in_array($c_slug,['panchayat-samiti','panchayat-samiti-bdc','bdc-member'],true);

    if ( $et === 'assembly' ) {
        $place = $loc['constituency'];
        $elec  = $hi ? 'हिमाचल विधानसभा चुनाव 2027' : 'HP Assembly Elections 2027';
    } elseif ( $et === 'ulb' ) {
        $place = implode(', ',array_filter([$loc['ward'],$loc['ulb'],$loc['district']]));
        $elec  = $hi ? 'नगर निकाय चुनाव 2026' : 'HP ULB Elections 2026';
    } else {
        if ( $is_zp )  $place = implode(', ',array_filter([get_post_meta($post_id,'zp_ward_text',true),$loc['district']]));
        elseif($is_bdc)$place = implode(', ',array_filter([$loc['block'],$loc['district']]));
        else           $place = implode(', ',array_filter([$loc['panchayat'],$loc['block'],$loc['district']]));
        $elec = $hi ? 'हिमाचल पंचायत चुनाव 2026' : 'HP Panchayat Elections 2026';
    }
    $from = $place ? ($hi ? ' — '.$place : ' from '.$place) : '';
    $site = $hi ? 'Enoxx News Hindi' : 'Enoxx News';
    return implode(' | ',array_filter([$name,$contest.$from,$elec,$site]));
}

function enx_build_candidate_meta_description( $post_id ) {
    $hi      = ENX_IS_HI;
    $name    = $hi
        ? ( get_post_meta($post_id,'candidate_name_hi',true) ?: get_post_meta($post_id,'candidate_name_text',true) )
        : get_post_meta($post_id,'candidate_name_text',true);
    $name    = $name ?: get_the_title($post_id);
    $lang    = $hi ? 'hi' : 'en';
    $et      = get_post_meta($post_id,'election_type',true) ?: 'panchayat';
    $contest = enx_get_contest_label_for_post($post_id,$lang);
    $loc     = enx_resolve_location($post_id,$lang);
    $c_slug  = get_post_meta($post_id,'contest_slug',true);
    $is_zp   = in_array($c_slug,['zila-parishad','zila-parishad-member'],true);
    $is_bdc  = in_array($c_slug,['panchayat-samiti','panchayat-samiti-bdc','bdc-member'],true);

    if ( $et === 'assembly' ) {
        $place = $loc['constituency'] ?: 'Himachal Pradesh';
        $elec  = $hi ? 'हिमाचल विधानसभा चुनाव 2027' : 'HP Assembly Elections 2027';
    } elseif ( $et === 'ulb' ) {
        $place = implode(', ',array_filter([$loc['ulb'],$loc['district']])) ?: 'HP';
        $elec  = $hi ? 'नगर निकाय चुनाव 2026' : 'HP ULB Elections 2026';
    } else {
        if ( $is_zp )  $place = implode(', ',array_filter([get_post_meta($post_id,'zp_ward_text',true),$loc['district']]));
        elseif($is_bdc)$place = implode(', ',array_filter([$loc['block'],$loc['district']]));
        else           $place = implode(', ',array_filter([$loc['panchayat'],$loc['block'],$loc['district']]));
        $place = $place ?: 'Himachal Pradesh';
        $elec  = $hi ? 'हिमाचल पंचायत चुनाव 2026' : 'HP Panchayat Elections 2026';
    }
    if ( $hi ) return "{$name} — {$contest}, {$place}. {$elec} उम्मीदवार। प्रोफाइल, बायो, संपर्क व पोस्टर Enoxx News पर।";
    return "View {$name}'s profile — {$contest} from {$place} in {$elec}. Bio, contact & poster on Enoxx News.";
}

/* RankMath filters — prefer stored custom SEO over auto-generated */
foreach ( [
    'rank_math/frontend/title'                 => 'enx_build_candidate_seo_title',
    'rank_math/frontend/description'           => 'enx_build_candidate_meta_description',
    'rank_math/opengraph/facebook/title'       => 'enx_build_candidate_seo_title',
    'rank_math/opengraph/facebook/description' => 'enx_build_candidate_meta_description',
    'rank_math/opengraph/twitter/title'        => 'enx_build_candidate_seo_title',
    'rank_math/opengraph/twitter/description'  => 'enx_build_candidate_meta_description',
] as $hook => $fn ) {
    add_filter( $hook, function($v) use($fn) {
        if ( ! is_singular('candidate') ) return $v;
        $id  = get_queried_object_id();
        if ( ! $id ) return $v;
        $key = ($fn === 'enx_build_candidate_seo_title') ? 'enx_seo_title' : 'enx_seo_desc';
        $stored = get_post_meta($id,$key,true);
        if ( $stored ) return $stored;
        return $fn($id);
    }, 20 );
}

foreach ( ['rank_math/opengraph/facebook/image','rank_math/opengraph/twitter/image'] as $hook ) {
    add_filter( $hook, function($img) {
        if ( ! is_singular('candidate') ) return $img;
        $id = get_queried_object_id();
        return ($id && ($url=enx_photo_url_raw($id,'full'))) ? $url : $img;
    }, 20 );
}

add_filter( 'rank_math/frontend/robots', function($r) {
    if ( ! is_singular('candidate') ) return $r;
    $r['index']='index'; $r['follow']='follow';
    unset($r['noindex'],$r['nofollow']);
    return $r;
}, 20 );

/* ══════════════════════════════════════════════════════════════════════
   DIRECTORY PAGE SEO
   /elections/, /ulb-elections/, /assembly-elections/ + sub-paths
   ══════════════════════════════════════════════════════════════════════ */

function enx_is_directory_request() {
    $req = trim(parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),'/');
    $seg = explode('/',$req);
    return in_array($seg[0]??'',['elections','ulb-elections','assembly-elections'],true);
}

function enx_get_directory_seo() {
    if ( ! enx_is_directory_request() ) return null;
    $req  = trim(parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),'/');
    $segs = array_values(array_filter(explode('/',$req)));
    $type = $segs[0] ?? '';
    $d    = $segs[1] ?? '';
    $b    = $segs[2] ?? '';
    $p    = $segs[3] ?? '';
    $hi   = ENX_IS_HI;

    if ( $type === 'elections' ) {
        if ( $p ) {
            $pname = enx_panchayat_label($d,$b,$p) ?: enx_labelize($p);
            $bname = enx_block_label($d,$b) ?: enx_labelize($b);
            $dname = enx_district_label($d) ?: enx_labelize($d);
            return [
                'title' => $hi ? "{$pname} पंचायत — उम्मीदवार | {$bname}, {$dname} | पंचायत चुनाव 2026 | Enoxx"
                               : "{$pname} Panchayat Candidates | {$bname}, {$dname} | Panchayat Elections 2026 | Enoxx",
                'desc'  => $hi ? "{$pname} पंचायत, {$bname} ब्लॉक, {$dname} से पंचायत चुनाव 2026 के सभी उम्मीदवार।"
                               : "All candidates from {$pname} Panchayat, {$bname} Block, {$dname} in HP Panchayat Elections 2026.",
            ];
        } elseif ( $b ) {
            $bname = enx_block_label($d,$b) ?: enx_labelize($b);
            $dname = enx_district_label($d) ?: enx_labelize($d);
            return [
                'title' => $hi ? "{$bname} ब्लॉक उम्मीदवार | {$dname} | पंचायत चुनाव 2026 | Enoxx"
                               : "{$bname} Block Candidates | {$dname} | Panchayat Elections 2026 | Enoxx",
                'desc'  => $hi ? "{$bname} ब्लॉक की सभी ग्राम पंचायतों के चुनाव 2026 उम्मीदवार, {$dname} जिला।"
                               : "All panchayat election 2026 candidates from {$bname} Block, {$dname} district, Himachal Pradesh.",
            ];
        } elseif ( $d ) {
            $dname = enx_district_label($d) ?: enx_labelize($d);
            return [
                'title' => $hi ? "{$dname} जिला पंचायत चुनाव 2026 उम्मीदवार | Enoxx News"
                               : "{$dname} District Panchayat Elections 2026 Candidates | Enoxx News",
                'desc'  => $hi ? "{$dname} जिले के सभी ब्लॉकों और ग्राम पंचायतों के 2026 चुनाव उम्मीदवारों की पूरी जानकारी।"
                               : "Complete list of all panchayat election 2026 candidates from {$dname} district, Himachal Pradesh.",
            ];
        } else {
            return [
                'title' => $hi ? 'हिमाचल पंचायत चुनाव 2026 — जिलेवार उम्मीदवार | Enoxx News'
                               : 'Himachal Pradesh Panchayat Elections 2026 — District-wise Candidates | Enoxx News',
                'desc'  => $hi ? 'हिमाचल प्रदेश के 12 जिलों की सभी ग्राम पंचायतों के पंचायत चुनाव 2026 उम्मीदवार।'
                               : 'Browse panchayat election 2026 candidates from all 12 districts of Himachal Pradesh.',
            ];
        }
    } elseif ( $type === 'ulb-elections' ) {
        if ( $d ) {
            $dname = enx_district_label($d) ?: enx_labelize($d);
            return [
                'title' => $hi ? "{$dname} नगर निकाय चुनाव 2026 उम्मीदवार | Enoxx News"
                               : "{$dname} ULB Elections 2026 Candidates | Enoxx News",
                'desc'  => $hi ? "{$dname} जिले के नगर पालिका, नगर परिषद और नगर पंचायत चुनाव 2026 उम्मीदवार।"
                               : "Municipal election 2026 candidates from {$dname} district — MC, NP, and Nagar Panchayat.",
            ];
        }
        return [
            'title' => $hi ? 'हिमाचल नगर निकाय चुनाव 2026 — सभी उम्मीदवार | Enoxx News'
                           : 'Himachal Pradesh ULB Elections 2026 — All Candidates | Enoxx News',
            'desc'  => $hi ? 'हिमाचल के सभी नगर पालिकाओं और नगर पंचायतों के 2026 चुनाव उम्मीदवार।'
                           : 'All municipal and urban local body election 2026 candidates across Himachal Pradesh.',
        ];
    } else { // assembly-elections
        if ( $d ) {
            $cname = enx_constituency_label($d) ?: enx_labelize($d);
            return [
                'title' => $hi ? "{$cname} विधानसभा चुनाव 2027 उम्मीदवार | Enoxx News"
                               : "{$cname} Assembly Elections 2027 Candidates | Enoxx News",
                'desc'  => $hi ? "{$cname} विधानसभा क्षेत्र के 2027 चुनाव उम्मीदवारों की पूरी जानकारी Enoxx News पर।"
                               : "All candidates from {$cname} assembly constituency in HP Assembly Elections 2027 on Enoxx News.",
            ];
        }
        return [
            'title' => $hi ? 'हिमाचल विधानसभा चुनाव 2027 — 68 क्षेत्र उम्मीदवार | Enoxx News'
                           : 'Himachal Pradesh Assembly Elections 2027 — All 68 Constituencies | Enoxx News',
            'desc'  => $hi ? 'हिमाचल प्रदेश के सभी 68 विधानसभा क्षेत्रों के चुनाव 2027 उम्मीदवारों की जानकारी।'
                           : 'Candidates from all 68 assembly constituencies in HP Assembly Elections 2027 on Enoxx News.',
        ];
    }
}

/* Override RankMath title + description on directory pages */
add_filter( 'rank_math/frontend/title', function($t) {
    $s = enx_get_directory_seo(); return $s ? $s['title'] : $t;
}, 30 );
add_filter( 'rank_math/frontend/description', function($d) {
    $s = enx_get_directory_seo(); return $s ? $s['desc'] : $d;
}, 30 );

/* Fallback <title> and <meta> if RankMath not active */
add_action( 'wp_head', function() {
    if ( class_exists('RankMath') ) return;
    $s = enx_get_directory_seo(); if(!$s) return;
    echo '<title>'.esc_html($s['title']).'</title>'."\n";
    echo '<meta name="description" content="'.esc_attr($s['desc']).'">'."\n";
}, 5 );

/* ══════════════════════════════════════════════════════════════════════
   DISCOVER DOMINATION — max-image-preview, view tracking, internal links
   ══════════════════════════════════════════════════════════════════════ */

/* Boost image preview for candidates and directory pages */
add_action( 'wp_head', function() {
    if ( is_singular('candidate') || enx_is_directory_request() ) {
        echo '<meta name="robots" content="max-image-preview:large">'."\n";
        echo '<meta name="googlebot" content="max-image-preview:large">'."\n";
    }
}, 2 );

/* View counter */
add_action( 'wp', function() {
    if ( ! is_singular('candidate') || is_admin() ) return;
    global $post;
    if ( ! $post ) return;
    update_post_meta($post->ID,'enx_views',(int)get_post_meta($post->ID,'enx_views',true)+1);
} );

/* Internal discovery links below candidate content */
add_filter( 'the_content', function($content) {
    if ( ! is_singular('candidate') || ! in_the_loop() || ! is_main_query() ) return $content;
    global $post;
    if ( ! $post || $post->post_type !== 'candidate' ) return $content;
    $et   = get_post_meta($post->ID,'election_type',true)?:'panchayat';
    $lang = ENX_IS_HI ? 'hi' : 'en';
    $loc  = enx_resolve_location($post->ID,$lang);
    $links=[];
    if ( $et==='panchayat' ) {
        $d=get_post_meta($post->ID,'district_slug',true);
        $b=get_post_meta($post->ID,'block_slug',true);
        if($d) $links[]='<a href="'.esc_url(home_url('/elections/'.$d.'/')).'">'.(ENX_IS_HI?$loc['district'].' के उम्मीदवार':'Candidates in '.$loc['district']).'</a>';
        if($d&&$b) $links[]='<a href="'.esc_url(home_url('/elections/'.$d.'/'.$b.'/')).'">'.(ENX_IS_HI?$loc['block'].' ब्लॉक':'Block: '.$loc['block']).'</a>';
    } elseif ( $et==='ulb' ) {
        $d=get_post_meta($post->ID,'district_slug',true);
        if($d) $links[]='<a href="'.esc_url(home_url('/ulb-elections/'.$d.'/')).'">'.(ENX_IS_HI?$loc['district'].' ULB':'ULB candidates in '.$loc['district']).'</a>';
    } elseif ( $et==='assembly' ) {
        $links[]='<a href="'.esc_url(home_url('/assembly-elections/')).'">'.(ENX_IS_HI?'सभी विधानसभा उम्मीदवार':'All Assembly Candidates').'</a>';
    }
    if(empty($links)) return $content;
    $head=ENX_IS_HI?'और उम्मीदवार देखें':'Explore More Candidates';
    $html='<div style="margin-top:18px;padding:14px 18px;background:#f8f5ef;border-left:4px solid #f59e0b;border-radius:0 8px 8px 0;font-size:13px"><strong style="display:block;margin-bottom:8px">🔍 '.esc_html($head).'</strong>'.implode(' &nbsp;•&nbsp; ',$links).'</div>';
    return $content.$html;
} );

/* JSON-LD — Person for candidate pages */
add_action( 'wp_head', function() {
    if ( ! is_singular('candidate') ) return;
    $id=get_queried_object_id(); if(!$id) return;
    $hi=$_hi=ENX_IS_HI;
    $name=$hi?(get_post_meta($id,'candidate_name_hi',true)?:get_post_meta($id,'candidate_name_text',true)):get_post_meta($id,'candidate_name_text',true);
    $schema=['@context'=>'https://schema.org','@type'=>'Person','name'=>$name?:get_the_title($id),'url'=>get_permalink($id),'description'=>enx_build_candidate_meta_description($id)];
    $img=enx_photo_url_raw($id,'full'); if($img) $schema['image']=$img;
    $sa=array_values(array_filter([get_post_meta($id,'facebook_url',true),get_post_meta($id,'instagram_url',true),get_post_meta($id,'youtube_url',true)]));
    if($sa) $schema['sameAs']=$sa;
    echo '<script type="application/ld+json">'.wp_json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>'."\n";
}, 30 );

/* JSON-LD — CollectionPage for directory pages */
add_action( 'wp_head', function() {
    if ( ! enx_is_directory_request() ) return;
    $s=enx_get_directory_seo(); if(!$s) return;
    $schema=['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>$s['title'],'description'=>$s['desc'],'url'=>esc_url('https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']),'publisher'=>['@type'=>'Organization','name'=>'Enoxx News','url'=>home_url()]];
    echo '<script type="application/ld+json">'.wp_json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>'."\n";
}, 31 );
