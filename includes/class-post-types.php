<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Register Candidate CPT ─────────────────────────────────────────────── */
add_action( 'init', function() {
    if ( post_type_exists( 'candidate' ) ) return;
    register_post_type( 'candidate', [
        'label'           => 'Candidates',
        'labels'          => [
            'name'          => 'Candidates',
            'singular_name' => 'Candidate',
            'add_new_item'  => 'Add New Candidate',
            'edit_item'     => 'Edit Candidate',
        ],
        'public'          => true,
        'has_archive'     => false,
        'rewrite'         => [ 'slug' => 'candidate', 'with_front' => false ],
        'supports'        => [ 'title', 'thumbnail', 'custom-fields' ],
        'show_in_rest'    => true,
        'menu_icon'       => 'dashicons-groups',
        'capability_type' => 'post',
        'map_meta_cap'    => true,
        'show_in_menu'    => false,
    ] );
}, 5 );

/* ── Draft workflow ─────────────────────────────────────────────────────── */
add_filter( 'wp_insert_post_data', function( $data, $postarr ) {
    if ( $data['post_type'] !== 'candidate' ) return $data;
    if ( current_user_can( 'administrator' ) || current_user_can( 'editor' ) ) return $data;
    if ( in_array( $data['post_status'], ['publish', 'future'], true ) ) {
        $data['post_status'] = 'pending';
    }
    return $data;
}, 10, 2 );

/* ── Dynamic capability check based on settings ─────────────────────────── */
add_filter( 'user_has_cap', function( $caps, $cap, $args ) {
    if ( empty( $args[2] ) || get_post_type( $args[2] ) !== 'candidate' ) return $caps;
    $user = wp_get_current_user();
    if ( in_array( 'administrator', $user->roles ) ) return $caps;

    $post    = get_post( $args[2] );
    $status  = $post ? $post->post_status : '';

    $is_editor      = in_array( 'editor', $user->roles );
    $is_contributor = in_array( 'contributor', $user->roles );

    $can_edit   = false;
    $can_delete = false;

    if ( $is_editor ) {
        $can_edit   = (bool) get_option( 'enx_perm_editor_edit',   '1' );
        $can_delete = (bool) get_option( 'enx_perm_editor_delete', '0' );
    } elseif ( $is_contributor ) {
        $can_edit   = (bool) get_option( 'enx_perm_contributor_edit',   '1' );
        $can_delete = (bool) get_option( 'enx_perm_contributor_delete', '0' );
        // Contributors can only edit their own pending posts
        if ( $can_edit && $status === 'publish' ) $can_edit = false;
        if ( $post && $post->post_author != $user->ID ) { $can_edit = false; $can_delete = false; }
    }

    if ( in_array( $args[0], ['edit_post','edit_published_posts'], true ) && ! $can_edit ) {
        $caps[$args[0]] = false;
    }
    if ( in_array( $args[0], ['delete_post','delete_published_posts'], true ) && ! $can_delete ) {
        $caps[$args[0]] = false;
    }

    return $caps;
}, 10, 3 );

/* ── Suppress comments ──────────────────────────────────────────────────── */
add_filter( 'comments_open',      function($o,$id){ return get_post_type($id)==='candidate' ? false : $o; }, 10, 2 );
add_filter( 'pings_open',         function($o,$id){ return get_post_type($id)==='candidate' ? false : $o; }, 10, 2 );
add_filter( 'comments_template',  function($t) {
    if ( is_singular('candidate') ) { $e=ENX_DIR.'templates/empty-comments.php'; return file_exists($e)?$e:$t; }
    return $t;
} );

/* ── Template override ──────────────────────────────────────────────────── */
add_filter( 'template_include', function($t) {
    if ( is_singular('candidate') ) { $p=ENX_DIR.'templates/single-candidate.php'; if(file_exists($p))return $p; }
    return $t;
}, 20 );

/* ── All meta fields ────────────────────────────────────────────────────── */
function enx_meta_fields() {
    return [
        'candidate_name_text','candidate_name_hi',
        'candidate_photo_id',
        'notes_text',           // Telecaller notes
        'short_intro_en',       // Admin-generated EN bio
        'short_intro_hi',       // Admin-generated HI bio
        'election_type',
        'election_text','election_slug',
        'contest_text','contest_slug',
        // Panchayat
        'district_slug','district_text',
        'block_slug','block_text',
        'panchayat_slug','panchayat_text',
        'zp_ward_slug','zp_ward_text','bdc_ward_slug','bdc_ward_text',
        // ULB
        'ulb_slug','ulb_text','ulb_type','ulb_category',
        'ward_slug','ward_text',
        'candidate_won','special_designation',
        // Assembly
        'constituency_slug','constituency_text',
        // Personal
        'age_text','gender_text',
        'party_affiliation_text',
        'profile_tier_text',
        // Media
        'candidate_video_url','candidate_interviews_urls',
        // Social
        'facebook_url','instagram_url','youtube_url',
        'whatsapp_text','phone_text',
        // System
        'en_source_post_id','submitted_by',
    ];
}
