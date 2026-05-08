<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function enx_meta( $post_id, $key ) {
    $v = get_post_meta( $post_id, $key, true );
    return ( $v || $v === '0' ) ? $v : '';
}
function enx_show( $val, $fallback = 'Not Available' ) {
    return ! empty( $val ) ? esc_html( $val ) : $fallback;
}
function enx_labelize( $text ) {
    if ( empty($text) ) return '';
    return ucwords( trim( preg_replace( '/\s+/', ' ', str_replace( ['-','_'], ' ', $text ) ) ) );
}

/* ── Placeholder URL ────────────────────────────────────────────────────────── */
function enx_placeholder_url() {
    $custom = get_option('enx_placeholder_url','');
    if ( $custom ) return esc_url($custom);
    return includes_url('images/media/default.png');
}

/* ── Photo URL (tier-aware for frontend) ─────────────────────────────────── */
function enx_photo_url( $post_id, $size = 'large' ) {
    // Use a safe placeholder: first try a known URL, then fall back to an SVG data URI
    $custom_ph = get_option('enx_placeholder_url','');
    if ( $custom_ph ) {
        $placeholder = esc_url($custom_ph);
    } else {
        // Inline SVG placeholder - always works, no missing file dependency
        $placeholder = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="300" height="375" viewBox="0 0 300 375"><rect width="300" height="375" fill="#e8ecf0"/><circle cx="150" cy="140" r="60" fill="#b0bec5"/><ellipse cx="150" cy="310" rx="90" ry="70" fill="#b0bec5"/><text x="150" y="360" text-anchor="middle" fill="#90a4ae" font-size="14" font-family="sans-serif">No Photo</text></svg>');
    }
    $tier = strtolower( (string) get_post_meta( $post_id, 'profile_tier_text', true ) );
    if ( $tier !== 'premium' ) return $placeholder;
    return enx_photo_url_raw( $post_id, $size ) ?: $placeholder;
}

/* ── Photo URL raw (for admin/poster — ignores tier) ─────────────────────── */
function enx_photo_url_raw( $post_id, $size = 'large' ) {
    $thumb = get_the_post_thumbnail_url( $post_id, $size );
    if ( $thumb ) return esc_url( $thumb );
    $att_id = (int) get_post_meta( $post_id, 'candidate_photo_id', true );
    if ( $att_id ) {
        $img = wp_get_attachment_image_src( $att_id, $size );
        if ( $img ) return esc_url( $img[0] );
    }
    // Legacy WPUF field
    $legacy = get_post_meta( $post_id, 'candidate_photo_upload', true );
    if ( is_array( $legacy ) ) {
        if ( ! empty($legacy['url']) ) return esc_url( $legacy['url'] );
        if ( ! empty($legacy[0]) && is_numeric($legacy[0]) ) {
            $url = wp_get_attachment_url( (int)$legacy[0] ); if ($url) return esc_url($url);
        }
    } elseif ( is_numeric($legacy) && $legacy > 0 ) {
        $url = wp_get_attachment_url( (int)$legacy ); if ($url) return esc_url($url);
    } elseif ( filter_var($legacy, FILTER_VALIDATE_URL) ) {
        return esc_url($legacy);
    }
    return '';
}

/* ── Auto rename photo on upload ─────────────────────────────────────────── */
function enx_rename_candidate_photo( $attachment_id, $post_id ) {
    if ( ! $attachment_id || ! $post_id ) return;
    $name     = trim( (string) get_post_meta( $post_id, 'candidate_name_text', true ) );
    $contest  = trim( (string) get_post_meta( $post_id, 'contest_text', true ) );
    $location = trim( (string) get_post_meta( $post_id, 'panchayat_text', true ) )
             ?: trim( (string) get_post_meta( $post_id, 'ulb_text', true ) )
             ?: trim( (string) get_post_meta( $post_id, 'constituency_text', true ) );

    $parts = array_filter( [$name, $contest, $location] );
    if ( empty($parts) ) return;

    $title = implode( ' - ', $parts );
    $slug  = sanitize_title( $title );

    // Update attachment post (title + slug)
    wp_update_post( [
        'ID'           => $attachment_id,
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_excerpt' => $title, // caption
    ] );

    // Update alt text
    update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );
}

/* ── Contest labels ──────────────────────────────────────────────────────── */
function enx_contest_labels() {
    return [
        'pradhan'          => ['en'=>'Pradhan Candidate',         'hi'=>'प्रधान उम्मीदवार'],
        'up-pradhan'       => ['en'=>'Up-Pradhan Candidate',      'hi'=>'उप-प्रधान उम्मीदवार'],
        'ward-member'      => ['en'=>'Ward Member Candidate',     'hi'=>'वार्ड सदस्य उम्मीदवार'],
        'panch'            => ['en'=>'Ward Member (Panch)',       'hi'=>'वार्ड सदस्य उम्मीदवार'],
        'zila-parishad'    => ['en'=>'Zila Parishad Candidate',   'hi'=>'जिला परिषद उम्मीदवार'],
        'panchayat-samiti' => ['en'=>'Panchayat Samiti Candidate','hi'=>'पंचायत समिति उम्मीदवार'],
        'bdc-member'       => ['en'=>'Panchayat Samiti (BDC)',    'hi'=>'पंचायत समिति (BDC) उम्मीदवार'],
        'councillor'       => ['en'=>'Councillor Candidate',      'hi'=>'पार्षद उम्मीदवार'],
        'mayor'            => ['en'=>'Mayor Candidate',           'hi'=>'मेयर उम्मीदवार'],
        'deputy-mayor'     => ['en'=>'Deputy Mayor Candidate',    'hi'=>'डिप्टी मेयर उम्मीदवार'],
        'president'        => ['en'=>'President Candidate',       'hi'=>'अध्यक्ष उम्मीदवार'],
        'vice-president'   => ['en'=>'Vice President Candidate',  'hi'=>'उपाध्यक्ष उम्मीदवार'],
        'mla-candidate'    => ['en'=>'MLA Candidate',             'hi'=>'विधायक उम्मीदवार'],
        // Legacy slug aliases
        'panchayat-samiti-bdc'         => ['en'=>'Panchayat Samiti (BDC)',        'hi'=>'पंचायत समिति (BDC)'],
        'ward-member-panch'            => ['en'=>'Ward Member (Panch)',            'hi'=>'वार्ड सदस्य (पंच)'],
        // Slug aliases — from sanitize_title() of contest_text values
        'zila-parishad-member'         => ['en'=>'Zila Parishad Member',           'hi'=>'जिला परिषद सदस्य'],
        'panchayat-samiti-member'      => ['en'=>'Panchayat Samiti Member',        'hi'=>'पंचायत समिति सदस्य'],
        'panchayat-samiti-member-bdc'  => ['en'=>'Panchayat Samiti Member (BDC)', 'hi'=>'पंचायत समिति सदस्य (BDC)'],
        'pradhan-candidate'            => ['en'=>'Pradhan Candidate',              'hi'=>'प्रधान उम्मीदवार'],
        'up-pradhan-candidate'         => ['en'=>'Up-Pradhan Candidate',           'hi'=>'उप-प्रधान उम्मीदवार'],
    ];
}

/* ── Contest label resolver — robust with multiple fallbacks ─────────────── */
function enx_contest_label( $contest_slug, $lang = 'en' ) {
    $labels = enx_contest_labels();

    // Direct slug match
    $slug = sanitize_title( $contest_slug );
    if ( isset( $labels[$slug] ) ) return $labels[$slug][$lang] ?? $labels[$slug]['en'];

    // Try known text→slug mapping
    $text_map = [
        'pradhan'                      => 'pradhan',
        'up-pradhan'                   => 'up-pradhan',
        'up pradhan'                   => 'up-pradhan',
        'ward member'                  => 'ward-member',
        'ward member (panch)'          => 'ward-member',
        'panch'                        => 'ward-member',
        'zila parishad member'         => 'zila-parishad',
        'zila parishad'                => 'zila-parishad',
        'panchayat samiti member (bdc)'=> 'panchayat-samiti',
        'panchayat samiti member'      => 'panchayat-samiti',
        'panchayat samiti (bdc)'       => 'panchayat-samiti',
        'bdc member'                   => 'panchayat-samiti',
        'councillor'                   => 'councillor',
        'mayor'                        => 'mayor',
        'deputy mayor'                 => 'deputy-mayor',
        'president'                    => 'president',
        'vice president'               => 'vice-president',
        'mla candidate'                => 'mla-candidate',
    ];
    $lower = strtolower( trim( $contest_slug ) );
    if ( isset( $text_map[$lower] ) ) {
        $mapped = $text_map[$lower];
        return $labels[$mapped][$lang] ?? $labels[$mapped]['en'] ?? enx_labelize($contest_slug);
    }

    return enx_labelize( $contest_slug );
}

/* ── Resolve contest from post (slug → text → fallback) ─────────────────── */
function enx_get_contest_label_for_post( $post_id, $lang = 'en' ) {
    $contest_slug = trim( (string) get_post_meta( $post_id, 'contest_slug', true ) );
    $contest_text = trim( (string) get_post_meta( $post_id, 'contest_text', true ) );

    // Try slug first
    if ( $contest_slug && $contest_slug !== 'mla-candidate' ) {
        $label = enx_contest_label( $contest_slug, $lang );
        if ( $label ) return $label;
    }

    // Try text (handles legacy entries where slug was set wrong)
    if ( $contest_text ) {
        $label = enx_contest_label( $contest_text, $lang );
        if ( $label ) return $label;
        return $contest_text; // return raw text as last resort
    }

    if ( $contest_slug ) return enx_contest_label( $contest_slug, $lang );
    return '';
}

function enx_is_ulb_contest( $slug ) {
    return in_array( sanitize_title($slug), ['mayor','deputy-mayor','president','vice-president','councillor'], true );
}

/* ── Poster themes ───────────────────────────────────────────────────────── */
function enx_poster_themes() {
    return apply_filters( 'enx_poster_themes', [
        'pradhan'          => ['primary'=>'#0e3368','secondary'=>'#d97706','gradient'=>'linear-gradient(135deg,#0e3368,#173a63,#214c7f)'],
        'up-pradhan'       => ['primary'=>'#1a4a8a','secondary'=>'#f59e0b','gradient'=>'linear-gradient(135deg,#1a4a8a,#1e5799,#2b6cb0)'],
        'ward-member'      => ['primary'=>'#065f46','secondary'=>'#f59e0b','gradient'=>'linear-gradient(135deg,#065f46,#047857,#059669)'],
        'panch'            => ['primary'=>'#065f46','secondary'=>'#f59e0b','gradient'=>'linear-gradient(135deg,#065f46,#047857,#059669)'],
        'zila-parishad'    => ['primary'=>'#14532d','secondary'=>'#d97706','gradient'=>'linear-gradient(135deg,#14532d,#166534,#15803d)'],
        'panchayat-samiti' => ['primary'=>'#312e81','secondary'=>'#f59e0b','gradient'=>'linear-gradient(135deg,#312e81,#3730a3,#4338ca)'],
        'bdc-member'       => ['primary'=>'#312e81','secondary'=>'#f59e0b','gradient'=>'linear-gradient(135deg,#312e81,#3730a3,#4338ca)'],
        'councillor'       => ['primary'=>'#7c2d12','secondary'=>'#f59e0b','gradient'=>'linear-gradient(135deg,#7c2d12,#9a3412,#c2410c)'],
        'mayor'            => ['primary'=>'#4c1d95','secondary'=>'#f6d07f','gradient'=>'linear-gradient(135deg,#4c1d95,#5b21b6,#7c3aed)'],
        'deputy-mayor'     => ['primary'=>'#6b21a8','secondary'=>'#f59e0b','gradient'=>'linear-gradient(135deg,#6b21a8,#7e22ce,#9333ea)'],
        'president'        => ['primary'=>'#065f46','secondary'=>'#f6d07f','gradient'=>'linear-gradient(135deg,#065f46,#047857,#059669)'],
        'vice-president'   => ['primary'=>'#14532d','secondary'=>'#f59e0b','gradient'=>'linear-gradient(135deg,#14532d,#166534,#15803d)'],
        'mla-candidate'    => ['primary'=>'#1c1c3a','secondary'=>'#f6d07f','gradient'=>'linear-gradient(135deg,#1c1c3a,#2d2d5e,#3d3d7a)'],
    ] );
}
function enx_poster_theme( $contest_slug ) {
    $themes = enx_poster_themes();
    $slug   = sanitize_title( $contest_slug );
    return $themes[$slug] ?? $themes['pradhan'];
}

/* ── Auto title on save ──────────────────────────────────────────────────── */
add_action( 'save_post_candidate', function( $post_id ) {
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision($post_id) ) return;
    remove_action( 'save_post_candidate', __FUNCTION__, 10 );
    $name     = trim( (string) get_post_meta($post_id,'candidate_name_text',true) );
    $position = trim( (string) get_post_meta($post_id,'contest_text',true) );
    $et       = trim( (string) get_post_meta($post_id,'election_type',true) );
    if ($et==='ulb')        $loc = trim( (string) get_post_meta($post_id,'ulb_text',true) );
    elseif ($et==='assembly') $loc = trim( (string) get_post_meta($post_id,'constituency_text',true) );
    else                    $loc = trim( (string) get_post_meta($post_id,'panchayat_text',true) );
    $title = trim( implode(' ', array_filter([$name,$position,$loc])) ) ?: ($name ?: 'Candidate');
    $cur   = get_post($post_id);
    if ( $title && $cur && $cur->post_title !== $title ) {
        wp_update_post(['ID'=>$post_id,'post_title'=>$title,'post_name'=>sanitize_title($title)]);
    }
    $att_id = (int) get_post_meta($post_id,'candidate_photo_id',true);
    if ( $att_id && get_post($att_id) ) {
        set_post_thumbnail($post_id,$att_id);
        enx_rename_candidate_photo($att_id,$post_id);
    }
    add_action( 'save_post_candidate', __FUNCTION__, 10 );
}, 10 );
