<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Panchayat location data ───────────────────────────────────────────────── */
function enx_get_location_data() {
    static $cache = null;
    if ( $cache !== null ) return $cache;
    $cache = [];
    $files = [
        'district-kangra.php','district-chamba.php',
        'district-hamirpur.php','district-shimla.php',
        'district-mandi.php','district-kullu.php',
        'district-sirmaur.php','district-solan.php',
        'district-una.php','district-bilaspur.php',
        'district-kinnaur.php','district-lahaul-spiti.php',
    ];
    foreach ( $files as $f ) {
        $path = ENX_DIR . 'location/data/' . $f;
        if ( file_exists($path) ) {
            $d = include $path;
            if ( is_array($d) ) $cache = array_merge($cache,$d);
        }
    }
    // Allow addenda (e.g. ZP wards) to inject data
    $cache = apply_filters( 'enx_location_data_loaded', $cache );
    return $cache;
}

/* ── ULB data ──────────────────────────────────────────────────────────────── */
function enx_get_ulb_data() {
    static $cache = null;
    if ( $cache !== null ) return $cache;
    $path = ENX_DIR . 'location/data/ulb-data.php';
    $cache = file_exists($path) ? ( include $path ) : [];
    return is_array($cache) ? $cache : [];
}

/* ── Assembly data ─────────────────────────────────────────────────────────── */
function enx_get_assembly_constituencies() {
    static $cache = null;
    if ( $cache !== null ) return $cache;
    $path = ENX_DIR . 'location/data/assembly-data.php';
    if ( file_exists($path) ) {
        // Use return-value include (works whether file returns array or declares function)
        $result = include $path;
        if ( is_array($result) ) {
            $cache = $result; // modern pure-return format
        } elseif ( function_exists('enx_get_assembly_data') ) {
            $cache = enx_get_assembly_data(); // legacy function-declaring format
        } else {
            $cache = [];
        }
    } else {
        $cache = [];
    }
    return $cache;
}

/* ── Label helpers ─────────────────────────────────────────────────────────── */
function enx_district_label( $slug, $lang = 'en' ) {
    $data = enx_get_location_data();
    if ( ! isset($data[$slug]) ) return '';
    return $lang === 'hi'
        ? ($data[$slug]['label_hi'] ?? $data[$slug]['label_en'] ?? '')
        : ($data[$slug]['label_en'] ?? '');
}

function enx_block_label( $district_slug, $block_slug, $lang = 'en' ) {
    $data = enx_get_location_data();
    $b = $data[$district_slug]['blocks'][$block_slug] ?? null;
    if ( !$b ) return '';
    return $lang === 'hi' ? ($b['label_hi'] ?? $b['label_en'] ?? '') : ($b['label_en'] ?? '');
}

function enx_panchayat_label( $district_slug, $block_slug, $panchayat_slug, $lang = 'en' ) {
    $data = enx_get_location_data();
    $p = $data[$district_slug]['blocks'][$block_slug]['panchayats'][$panchayat_slug] ?? null;
    if ( !$p ) return '';
    if ( is_array($p) ) return $lang === 'hi' ? ($p['label_hi'] ?? $p['label_en'] ?? '') : ($p['label_en'] ?? '');
    return $p;
}

function enx_ulb_label( $district_slug, $ulb_slug, $lang = 'en' ) {
    $data = enx_get_ulb_data();
    $u = $data[$district_slug]['ulbs'][$ulb_slug] ?? null;
    if ( !$u ) return '';
    return $lang === 'hi' ? ($u['label_hi'] ?? $u['label_en'] ?? '') : ($u['label_en'] ?? '');
}

function enx_ulb_name( $district_slug, $ulb_slug, $lang = 'en' ) {
    return enx_ulb_label($district_slug, $ulb_slug, $lang);
}

function enx_ward_label( $district_slug, $ulb_slug, $ward_slug, $lang = 'en' ) {
    $data = enx_get_ulb_data();
    $w = $data[$district_slug]['ulbs'][$ulb_slug]['wards'][$ward_slug] ?? null;
    if ( !$w ) return '';
    return $lang === 'hi' ? ($w['label_hi'] ?? $w['label_en'] ?? '') : ($w['label_en'] ?? '');
}

function enx_constituency_label( $slug, $lang = 'en' ) {
    $data = enx_get_assembly_constituencies();
    if ( !isset($data[$slug]) ) return enx_labelize($slug);
    return $lang === 'hi' ? ($data[$slug]['label_hi'] ?? $data[$slug]['label_en'] ?? '') : ($data[$slug]['label_en'] ?? '');
}

/* ── Resolve all location labels for a candidate ───────────────────────────── */
function enx_resolve_location( $post_id, $lang = 'en' ) {
    $district_slug    = trim((string) get_post_meta($post_id,'district_slug',true));
    $block_slug       = trim((string) get_post_meta($post_id,'block_slug',true));
    $panchayat_slug   = trim((string) get_post_meta($post_id,'panchayat_slug',true));
    $ulb_slug         = trim((string) get_post_meta($post_id,'ulb_slug',true));
    $ward_slug        = trim((string) get_post_meta($post_id,'ward_slug',true));
    $constituency_slug= trim((string) get_post_meta($post_id,'constituency_slug',true));
    $zp_ward_slug     = trim((string) get_post_meta($post_id,'zp_ward_slug',true));

    return [
        'district'           => enx_district_label($district_slug,$lang) ?: get_post_meta($post_id,'district_text',true),
        'block'              => enx_block_label($district_slug,$block_slug,$lang) ?: get_post_meta($post_id,'block_text',true),
        'panchayat'          => enx_panchayat_label($district_slug,$block_slug,$panchayat_slug,$lang) ?: get_post_meta($post_id,'panchayat_text',true),
        'ulb'                => enx_ulb_label($district_slug,$ulb_slug,$lang) ?: get_post_meta($post_id,'ulb_text',true),
        'ward'               => enx_ward_label($district_slug,$ulb_slug,$ward_slug,$lang) ?: get_post_meta($post_id,'ward_text',true),
        'constituency'       => enx_constituency_label($constituency_slug,$lang) ?: get_post_meta($post_id,'constituency_text',true),
        'zp_ward'            => get_post_meta($post_id,'zp_ward_text',true),
        'district_slug'      => $district_slug,
        'block_slug'         => $block_slug,
        'panchayat_slug'     => $panchayat_slug,
        'ulb_slug'           => $ulb_slug,
        'ward_slug'          => $ward_slug,
        'constituency_slug'  => $constituency_slug,
        'zp_ward_slug'       => $zp_ward_slug,
    ];
}
