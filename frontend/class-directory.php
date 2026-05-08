<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Rewrite rules ─────────────────────────────────────────────────────── */
add_action( 'init', function() {
    // Panchayat
    add_rewrite_rule('^elections/?$',                             'index.php?enx_elections_home=1',                                                     'top');
    add_rewrite_rule('^elections/([^/]+)/?$',                    'index.php?enx_district=$matches[1]',                                                  'top');
    add_rewrite_rule('^elections/([^/]+)/([^/]+)/?$',            'index.php?enx_district=$matches[1]&enx_block=$matches[2]',                            'top');
    add_rewrite_rule('^elections/([^/]+)/([^/]+)/([^/]+)/?$',    'index.php?enx_district=$matches[1]&enx_block=$matches[2]&enx_panchayat=$matches[3]',  'top');
    // ULB
    add_rewrite_rule('^ulb-elections/?$',                                   'index.php?enx_ulb_home=1',                                                                'top');
    add_rewrite_rule('^ulb-elections/([^/]+)/?$',                           'index.php?enx_district=$matches[1]&enx_type=ulb',                                         'top');
    add_rewrite_rule('^ulb-elections/([^/]+)/([^/]+)/?$',                   'index.php?enx_district=$matches[1]&enx_ulb=$matches[2]&enx_type=ulb',                     'top');
    add_rewrite_rule('^ulb-elections/([^/]+)/([^/]+)/([^/]+)/?$',           'index.php?enx_district=$matches[1]&enx_ulb=$matches[2]&enx_ward=$matches[3]&enx_type=ulb','top');
    // Assembly
    add_rewrite_rule('^assembly-elections/?$',                              'index.php?enx_asm_home=1',                                                                'top');
    add_rewrite_rule('^assembly-elections/([^/]+)/?$',                      'index.php?enx_constituency=$matches[1]&enx_type=assembly',                                'top');
} );

add_filter( 'query_vars', function($v) {
    foreach(['enx_elections_home','enx_ulb_home','enx_asm_home','enx_district','enx_block','enx_panchayat','enx_ulb','enx_ward','enx_constituency','enx_type','enx_zp_ward','enx_bdc_ward'] as $k) $v[]=$k;
    return $v;
} );

/* ── Template redirect — intercepts requests BEFORE page template loads ── */
/* This fixes the issue where /elections/ page was showing news/category content */
add_action( 'template_redirect', function() {
    $request = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
    $segments = explode( '/', $request );
    $first    = $segments[0] ?? '';

    if ( ! in_array( $first, ['elections', 'ulb-elections', 'assembly-elections'], true ) ) return;

    // Fix WP query so SmartMag doesn't apply 404/single-post boxed layout
    global $wp_query, $wp;
    status_header(200); // Prevent 404 response header
    if ( $wp_query ) {
        $wp_query->is_404        = false;
        $wp_query->is_singular   = false;
        $wp_query->is_page       = true;  // treat as a regular page = wide layout
        $wp_query->is_home       = false;
        $wp_query->is_archive    = false;
    }

    // Layout filters BEFORE get_header()
    add_filter( 'body_class', function( $classes ) {
        $classes[] = 'enx-directory-page';
        $classes[] = 'page-template-full-width';
        return $classes;
    } );
    add_filter( 'bunyad_layout',         function() { return 'wide'; }, 99 );
    add_filter( 'ts_layout',             function() { return 'wide'; }, 99 );
    add_filter( 'smartmag/layout',       function() { return 'wide'; }, 99 );
    add_filter( 'bunyad_sidebar_enabled',function() { return false;  }, 99 );
    add_filter( 'smartmag/sidebar/display', function() { return false; }, 99 );

    // Serve our custom directory template
    $tpl = ENX_DIR . 'templates/directory-page.php';
    if ( file_exists($tpl) ) {
        include $tpl;
        exit;
    }
}, 1 );

/* ── Widget areas + SmartMag ───────────────────────────────────────────── */
add_action( 'widgets_init', function() {
    foreach([
        ['Election Directory Sidebar','enx-directory-sidebar','Sidebar on election directory pages'],
        ['Election Directory Top','enx-directory-top','Banner above directory content'],
        ['Candidate Profile Bottom','enx-candidate-bottom','Below candidate profiles'],
        ['Candidate Profile Sidebar','enx-candidate-sidebar','Sidebar on candidate profile pages'],
    ] as [$n,$id,$d]) {
        register_sidebar(['name'=>$n,'id'=>$id,'description'=>$d,
            'before_widget'=>'<div id="%1$s" class="enx-widget %2$s">','after_widget'=>'</div>',
            'before_title'=>'<h3 class="enx-widget-title">','after_title'=>'</h3>']);
    }
});
foreach(['smartmag/sidebar/display','bunyad_sidebar_enabled'] as $h) add_filter($h,function($v){ return is_singular('candidate')?false:$v; });
foreach(['smartmag/breadcrumbs/display','smartmag/related_posts/display','smartmag/post/show_tags','smartmag/post/show_meta'] as $h) add_filter($h,function($v){ return is_singular('candidate')?false:$v; });
foreach(['bunyad_layout','ts_layout'] as $h) add_filter($h,function($v){ return is_singular('candidate')?'wide':$v; });
// Candidate Profile Bottom rendered in single-candidate.php template

/* ── Directory CSS (loaded on directory pages) ─────────────────────────── */
function enx_directory_css() { ?>
<style>
.enx-dir-wrap{max-width:1200px;margin:0 auto;padding:24px 18px 50px;font-family:inherit}
.enx-dir-wrap h1{font-size:28px;color:#112b4a;margin:0 0 8px;font-weight:800}
.enx-dir-wrap h2{font-size:19px;color:#1e293b;margin:22px 0 12px;font-weight:700}
.enx-dir-wrap p{font-size:15px;color:#555;margin-bottom:18px}
.enx-home-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin:22px 0}
.enx-home-card{display:block;padding:26px 20px;border-radius:18px;text-decoration:none;font-weight:700;font-size:16px;border:2px solid;transition:transform .18s,box-shadow .18s;text-align:center;line-height:1.4}
.enx-home-card:hover{transform:translateY(-3px);box-shadow:0 14px 32px rgba(0,0,0,.10)}
.enx-home-card .icon{font-size:30px;display:block;margin-bottom:8px}
.enx-home-card-pan{background:linear-gradient(135deg,#eff6ff,#dbeafe);border-color:#bfdbfe;color:#1e40af}
.enx-home-card-ulb{background:linear-gradient(135deg,#fdf4ff,#fae8ff);border-color:#e9d5ff;color:#7e22ce}
.enx-home-card-asm{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border-color:#bbf7d0;color:#15803d}
.enx-cat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:14px 0 26px}
.enx-cat-card{display:block;padding:16px;border-radius:12px;text-decoration:none;font-weight:700;font-size:14px;text-align:center;border:1px solid #e8e3d7;background:#fff;transition:all .18s;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.enx-cat-card:hover{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border-color:transparent}
.enx-dir-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin:12px 0 24px}
.enx-dir-card{display:flex;align-items:center;justify-content:center;text-align:center;background:#fff;padding:11px 10px;border-radius:11px;text-decoration:none;font-weight:700;font-size:13px;color:#112b4a;border:1px solid #e8e3d7;box-shadow:0 2px 8px rgba(0,0,0,.04);transition:all .18s;min-height:44px;line-height:1.3}
.enx-dir-card:hover{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border-color:transparent;transform:translateY(-2px)}
.enx-dir-card small{font-weight:400;font-size:10px;display:block;color:#888}
.enx-dir-card:hover small{color:rgba(255,255,255,.8)}
.enx-cand-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;margin-top:18px;margin-bottom:18px}
.enx-cand-card{background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,.07);border:1px solid #eee;position:relative;transition:transform .2s,box-shadow .2s}
.enx-cand-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--accent,#d97706)}
.enx-cand-card:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(0,0,0,.10)}
.enx-cand-card img{width:100%;height:190px;object-fit:cover;object-position:center top;display:block}
.enx-cand-body{padding:11px 11px 11px 15px}
.enx-cand-pos{display:inline-block;font-size:10px;font-weight:700;background:#f2f4f8;color:#555;padding:2px 8px;border-radius:999px;margin-bottom:6px;text-transform:uppercase}
.enx-cand-name{font-size:14px;font-weight:700;margin:0 0 6px;line-height:1.25}
.enx-cand-name a{color:#112b4a;text-decoration:none}
.enx-cand-name a:hover{color:#d97706}
.enx-cand-meta{font-size:11px;color:#666;margin-bottom:9px}
.enx-cand-btn{display:inline-block;background:#112b4a;color:#fff;padding:5px 12px;border-radius:7px;text-decoration:none;font-size:11px;font-weight:700}
.enx-cand-btn:hover{opacity:.85;color:#fff}
.enx-pager{display:flex;justify-content:center;gap:7px;margin:22px 0 8px;flex-wrap:wrap}
.enx-pager a,.enx-pager span{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:7px;font-weight:700;font-size:12px;text-decoration:none;border:1px solid #e8e3d7;background:#fff;color:#112b4a;transition:all .18s}
.enx-pager .cur,.enx-pager a:hover{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border-color:transparent}
.enx-breadcrumb{display:flex;align-items:center;gap:7px;margin-bottom:14px;font-size:12px;flex-wrap:wrap}
.enx-breadcrumb a{color:#112b4a;text-decoration:none;padding:4px 11px;background:#f8f5ef;border:1px solid #eee7d9;border-radius:7px;font-weight:700;transition:all .18s}
.enx-breadcrumb a:hover{background:#112b4a;color:#fff}
.enx-breadcrumb span{color:#999}
.enx-section-tag{display:inline-block;padding:3px 11px;border-radius:999px;font-size:11px;font-weight:700;margin-bottom:10px}
.enx-tag-pan{background:#dbeafe;color:#1e40af}
.enx-tag-ulb{background:#fae8ff;color:#7e22ce}
.enx-tag-asm{background:#dcfce7;color:#15803d}
.enx-no-cand{color:#888;font-style:italic;padding:14px 0;font-size:14px}
.enx-search-bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:18px;padding:14px 16px;background:#fff;border:1px solid #e8e3d7;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.enx-search-bar input[type=text]{flex:1;min-width:180px;padding:9px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;outline:none}
.enx-search-bar input[type=text]:focus{border-color:#f59e0b;box-shadow:0 0 0 2px rgba(245,158,11,.15)}
.enx-search-bar select{padding:9px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px;background:#fff}
.enx-search-bar button{padding:9px 18px;border:none;border-radius:8px;background:#112b4a;color:#fff;font-weight:700;font-size:13px;cursor:pointer;white-space:nowrap}
.enx-search-bar button:hover{background:#0e2338}
.enx-result-count{font-size:13px;color:#888;margin-bottom:10px}
@media(max-width:1024px){.enx-cand-grid{grid-template-columns:repeat(4,1fr)}}
@media(max-width:768px){.enx-cand-grid{grid-template-columns:repeat(2,1fr)}.enx-home-grid{grid-template-columns:1fr}.enx-cat-grid{grid-template-columns:1fr}.enx-dir-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:480px){.enx-cand-grid{grid-template-columns:repeat(2,1fr)}.enx-dir-grid{grid-template-columns:repeat(2,1fr)}}
</style>
<?php
}

/* ── Directory content renderer ────────────────────────────────────────── */
function enx_render_directory_content() {
    $request  = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
    $segments = array_filter( explode( '/', $request ) );
    $segments = array_values( $segments );

    $first = $segments[0] ?? '';

    if ( $first === 'elections' ) {
        enx_render_panchayat_dir( $segments );
    } elseif ( $first === 'ulb-elections' ) {
        enx_render_ulb_dir( $segments );
    } elseif ( $first === 'assembly-elections' ) {
        enx_render_assembly_dir( $segments );
    }
}

/* ── Panchayat directory ───────────────────────────────────────────────── */
function enx_render_panchayat_dir( $segments ) {
    $lang  = ENX_IS_HI ? 'hi' : 'en';
    $base  = home_url('/elections/');
    $d      = sanitize_title( $segments[1] ?? '' );
    $seg2   = sanitize_title( $segments[2] ?? '' );
    $seg3   = sanitize_title( $segments[3] ?? '' );

    // Detect ZP ward page: /elections/{district}/zp/{ward}/
    $is_zp_page  = ($seg2 === 'zp' && $seg3 !== '');
    $zp_ward_slug= $is_zp_page ? $seg3 : '';

    // Detect BDC ward page: /elections/{district}/{block}/bdc/{ward}/
    $seg4        = sanitize_title( $segments[4] ?? '' );
    $is_bdc_page = ($seg3 === 'bdc' && $seg4 !== '');
    $bdc_ward_slug= $is_bdc_page ? $seg4 : '';

    // Normal block/panchayat
    $b     = ( ! $is_zp_page && $seg3 !== 'bdc' ) ? $seg2 : '';
    $p     = ( ! $is_zp_page && ! $is_bdc_page && $seg3 !== '' ) ? $seg3 : '';
    // If BDC page, block is still seg2
    if ( $is_bdc_page ) $b = $seg2;

    $data  = enx_get_location_data();

    echo '<div class="enx-dir-wrap">';

    if ( ! $d ) {
        echo '<span class="enx-section-tag enx-tag-pan">🏘️ Panchayat Elections 2026</span>';
        echo '<h1>' . (ENX_IS_HI ? 'हिमाचल प्रदेश पंचायत चुनाव 2026' : 'Himachal Pradesh Panchayat Elections 2026') . '</h1>';
        echo '<p>' . (ENX_IS_HI ? 'जिला चुनें:' : 'Select a district:') . '</p>';
        echo '<div class="enx-dir-grid">';
        foreach ( $data as $slug => $dd ) {
            // Skip zp_wards — it's not a district
            if ( $slug === 'zp_wards' ) continue;
            $label = $lang==='hi' ? ($dd['label_hi']??$dd['label_en']??$slug) : ($dd['label_en']??$slug);
            echo '<a class="enx-dir-card" href="'.esc_url($base.$slug.'/').'">'.esc_html($label).'</a>';
        }
        echo '</div>';

        // ── Cross-election navigation buttons ──────────────────────────
        $btn_lbl = ENX_IS_HI ? [
            'ulb'  => '🏙️ नगर निकाय चुनाव 2026',
            'asm'  => '🏛️ विधानसभा चुनाव 2027',
        ] : [
            'ulb'  => '🏙️ HP ULB Elections 2026',
            'asm'  => '🏛️ HP Assembly Elections 2027',
        ];
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:32px 0 8px">';
        echo '<a href="'.esc_url(home_url('/ulb-elections/')).'" style="display:flex;align-items:center;justify-content:center;padding:18px;background:linear-gradient(135deg,#4c1d95,#7c3aed);color:#fff;border-radius:14px;font-weight:800;font-size:16px;text-decoration:none;text-align:center;gap:8px">'.esc_html($btn_lbl['ulb']).'</a>';
        echo '<a href="'.esc_url(home_url('/assembly-elections/')).'" style="display:flex;align-items:center;justify-content:center;padding:18px;background:linear-gradient(135deg,#065f46,#059669);color:#fff;border-radius:14px;font-weight:800;font-size:16px;text-decoration:none;text-align:center;gap:8px">'.esc_html($btn_lbl['asm']).'</a>';
        echo '</div>';

    } elseif ( $is_zp_page && $zp_ward_slug ) {
        // ZP Ward page
        $dl  = enx_district_label($d,$lang) ?: enx_labelize($d);
        $zpw = $data[$d]['zp_wards'][$zp_ward_slug] ?? [];
        $wl  = $lang==='hi' ? ($zpw['label_hi']??$zpw['label_en']??enx_labelize($zp_ward_slug)) : ($zpw['label_en']??enx_labelize($zp_ward_slug));
        echo '<div class="enx-breadcrumb"><a href="'.esc_url($base).'">'.($lang==='hi'?'जिले':'Districts').'</a><span>›</span><a href="'.esc_url($base.$d.'/').'">'.esc_html($dl).'</a><span>›</span><span>'.esc_html($wl).'</span></div>';
        echo '<span class="enx-section-tag enx-tag-pan">🗳️ '.($lang==='hi'?'जिला परिषद वार्ड':'Zila Parishad Ward').'</span>';
        echo '<h1>'.esc_html($wl).'</h1>';
        echo '<h2>'.(ENX_IS_HI?'उम्मीदवार':'All Candidates').'</h2>';
        enx_candidate_grid(['district'=>$d,'election_type'=>'panchayat','zp_ward'=>$zp_ward_slug],$lang);

    } elseif ( $is_bdc_page && $bdc_ward_slug ) {
        // BDC Ward page
        $dl  = enx_district_label($d,$lang) ?: enx_labelize($d);
        $bl2 = enx_block_label($d,$b,$lang) ?: enx_labelize($b);
        $bw  = $data[$d]['blocks'][$b]['bdc_wards'][$bdc_ward_slug] ?? [];
        $wl  = $lang==='hi' ? ($bw['label_hi']??$bw['label_en']??enx_labelize($bdc_ward_slug)) : ($bw['label_en']??enx_labelize($bdc_ward_slug));
        echo '<div class="enx-breadcrumb"><a href="'.esc_url($base.$d.'/').'">'.esc_html($dl).'</a><span>›</span><a href="'.esc_url($base.$d.'/'.$b.'/').'">'.esc_html($bl2).'</a><span>›</span><span>'.esc_html($wl).'</span></div>';
        echo '<span class="enx-section-tag enx-tag-pan">🏛️ '.($lang==='hi'?'पंचायत समिति वार्ड':'BDC Ward').'</span>';
        echo '<h1>'.esc_html($wl).'</h1>';
        echo '<h2>'.(ENX_IS_HI?'उम्मीदवार':'All Candidates').'</h2>';
        enx_candidate_grid(['district'=>$d,'block'=>$b,'bdc_ward'=>$bdc_ward_slug,'election_type'=>'panchayat'],$lang);

    } elseif ( $d && ! $b ) {
        $dl = enx_district_label($d,$lang) ?: enx_labelize($d);
        echo '<div class="enx-breadcrumb"><a href="'.esc_url($base).'">'.($lang==='hi'?'जिले':'Districts').'</a><span>›</span><span>'.esc_html($dl).'</span></div>';
        echo '<span class="enx-section-tag enx-tag-pan">🏘️ Panchayat Elections 2026</span>';
        echo '<h1>'.esc_html($dl).'</h1>';
        if ( isset($data[$d]['blocks']) ) {
            echo '<h2>' . (ENX_IS_HI?'ब्लॉक / पंचायत समिति चुनें':'Browse by Block / Panchayat Samiti') . '</h2>';
            echo '<div class="enx-dir-grid">';
            foreach ( $data[$d]['blocks'] as $bs => $bk ) {
                $bl    = $lang==='hi' ? ($bk['label_hi']??$bk['label_en']??$bs) : ($bk['label_en']??$bs);
                $count = count($bk['panchayats']??[]);
                echo '<a class="enx-dir-card" href="'.esc_url($base.$d.'/'.$bs.'/').'">'.esc_html($bl).'</a>';
            }
            echo '</div>';
        }
        // ZP wards browse section
        $zp_wards_d = $data[$d]['zp_wards'] ?? [];
        if ( ! empty($zp_wards_d) ) {
            echo '<h2>'.(ENX_IS_HI?'जिला परिषद् वार्ड चुनें':'Browse by Zila Parishad Ward').'</h2>';
            echo '<div class="enx-dir-grid">';
            foreach ( $zp_wards_d as $ws => $wv ) {
                $wl = $lang==='hi' ? (is_array($wv)?($wv['label_hi']??$wv['label_en']??$ws):$wv) : (is_array($wv)?($wv['label_en']??$ws):$wv);
                echo '<a class="enx-dir-card" href="'.esc_url($base.$d.'/zp/'.$ws.'/').'">'.esc_html($wl).'</a>';
            }
            echo '</div>';
        }
        echo '<h2>' . (ENX_IS_HI?'सभी उम्मीदवार':'All Candidates in '.esc_html($dl)) . '</h2>';
        enx_candidate_grid(['district'=>$d,'election_type'=>'panchayat'],$lang);

    } elseif ( $d && $b && ! $p ) {
        $dl = enx_district_label($d,$lang) ?: enx_labelize($d);
        $bl = enx_block_label($d,$b,$lang) ?: enx_labelize($b);
        echo '<div class="enx-breadcrumb"><a href="'.esc_url($base).'">'.($lang==='hi'?'जिले':'Districts').'</a><span>›</span><a href="'.esc_url($base.$d.'/').'">'.esc_html($dl).'</a><span>›</span><span>'.esc_html($bl).'</span></div>';
        echo '<span class="enx-section-tag enx-tag-pan">🏘️ Panchayat Elections 2026</span>';
        echo '<h1>'.esc_html($bl).' '.(ENX_IS_HI?'ब्लॉक':'Block').'</h1>';
        $pans = $data[$d]['blocks'][$b]['panchayats'] ?? [];
        if ( ! empty($pans) ) {
            echo '<h2>'.(ENX_IS_HI?'ग्राम पंचायत चुनें':'Browse by Gram Panchayat').'</h2>';
            echo '<div class="enx-dir-grid">';
            foreach ( $pans as $ps => $pp ) {
                $pl = $lang==='hi' ? (is_array($pp)?($pp['label_hi']??$pp['label_en']??$ps):$ps) : (is_array($pp)?($pp['label_en']??$ps):$pp);
                echo '<a class="enx-dir-card" href="'.esc_url($base.$d.'/'.$b.'/'.$ps.'/').'">'.esc_html($pl).'</a>';
            }
            echo '</div>';
        }
        // BDC / Panchayat Samiti wards
        $bdc_wards_b = $data[$d]['blocks'][$b]['bdc_wards'] ?? [];
        if ( ! empty($bdc_wards_b) ) {
            echo '<h2>'.(ENX_IS_HI?'पंचायत समिति वार्ड चुनें':'Browse by BDC / Panchayat Samiti Ward').'</h2>';
            echo '<div class="enx-dir-grid">';
            foreach ( $bdc_wards_b as $ws => $wv ) {
                $wl = $lang==='hi' ? (is_array($wv)?($wv['label_hi']??$wv['label_en']??$ws):$wv) : (is_array($wv)?($wv['label_en']??$ws):$wv);
                echo '<a class="enx-dir-card" href="'.esc_url($base.$d.'/'.$b.'/bdc/'.$ws.'/').'">'.esc_html($wl).'</a>';
            }
            echo '</div>';
        }
        echo '<h2>'.(ENX_IS_HI?'सभी उम्मीदवार':'All Candidates in '.esc_html($bl)).'</h2>';
        enx_candidate_grid(['district'=>$d,'block'=>$b,'election_type'=>'panchayat'],$lang);

    } else {
        $dl = enx_district_label($d,$lang)?:enx_labelize($d);
        $bl = enx_block_label($d,$b,$lang)?:enx_labelize($b);
        $pl = enx_panchayat_label($d,$b,$p,$lang)?:enx_labelize($p);
        echo '<div class="enx-breadcrumb"><a href="'.esc_url($base.$d.'/').'">'.esc_html($dl).'</a><span>›</span><a href="'.esc_url($base.$d.'/'.$b.'/').'">'.esc_html($bl).'</a><span>›</span><span>'.esc_html($pl).'</span></div>';
        echo '<span class="enx-section-tag enx-tag-pan">🏘️ Panchayat Elections 2026</span>';
        echo '<h1>'.esc_html($pl).' '.(ENX_IS_HI?'पंचायत':'Panchayat').'</h1>';
        enx_candidate_grid(['district'=>$d,'block'=>$b,'panchayat'=>$p,'election_type'=>'panchayat'],$lang);
    }
    echo '</div>';
}

/* ── ULB directory ─────────────────────────────────────────────────────── */
function enx_render_ulb_dir( $segments ) {
    $lang  = ENX_IS_HI ? 'hi' : 'en';
    $base  = home_url('/ulb-elections/');
    $d     = sanitize_title( $segments[1] ?? '' );
    $u     = sanitize_title( $segments[2] ?? '' );
    $w     = sanitize_title( $segments[3] ?? '' );
    $udata = enx_get_ulb_data();

    echo '<div class="enx-dir-wrap">';

    if ( ! $d ) {
        echo '<span class="enx-section-tag enx-tag-ulb">🏙️ ULB Elections 2026</span>';
        echo '<h1>'.(ENX_IS_HI?'हिमाचल प्रदेश नगर निकाय चुनाव 2026':'HP Urban Local Body Elections 2026').'</h1>';
        echo '<h2>'.(ENX_IS_HI?'नगर निकाय प्रकार':'Browse by Category').'</h2>';
        echo '<div class="enx-cat-grid">';
        echo '<a class="enx-cat-card" href="'.esc_url($base.'?cat=municipal_corporation').'">'.(ENX_IS_HI?'🏙️ नगर निगम':'🏙️ Municipal Corporations').'</a>';
        echo '<a class="enx-cat-card" href="'.esc_url($base.'?cat=municipal_council').'">'.(ENX_IS_HI?'🏛️ नगर परिषद':'🏛️ Municipal Councils').'</a>';
        echo '<a class="enx-cat-card" href="'.esc_url($base.'?cat=nagar_panchayat').'">'.(ENX_IS_HI?'🏠 नगर पंचायत':'🏠 Nagar Panchayats').'</a>';
        echo '</div>';
        echo '<h2>'.(ENX_IS_HI?'जिला चुनें':'Browse by District').'</h2>';
        echo '<div class="enx-dir-grid">';
        foreach ( array_keys($udata) as $ds ) {
            $cnt = count($udata[$ds]['ulbs']??[]); if(!$cnt) continue;
            $dl  = enx_district_label($ds,$lang)?:enx_labelize($ds);
            echo '<a class="enx-dir-card" href="'.esc_url($base.$ds.'/').'">'.esc_html($dl).'</a>';
        }
        echo '</div>';

        // Cross-election nav
        $lbl_pan = ENX_IS_HI ? '🏘️ पंचायत चुनाव 2026'   : '🏘️ HP Panchayat Elections 2026';
        $lbl_asm = ENX_IS_HI ? '🏛️ विधानसभा चुनाव 2027' : '🏛️ HP Assembly Elections 2027';
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:32px 0 8px">';
        echo '<a href="'.esc_url(home_url('/elections/')).'" style="display:flex;align-items:center;justify-content:center;padding:18px;background:linear-gradient(135deg,#0e3368,#2b6cb0);color:#fff;border-radius:14px;font-weight:800;font-size:16px;text-decoration:none;text-align:center">'.esc_html($lbl_pan).'</a>';
        echo '<a href="'.esc_url(home_url('/assembly-elections/')).'" style="display:flex;align-items:center;justify-content:center;padding:18px;background:linear-gradient(135deg,#065f46,#059669);color:#fff;border-radius:14px;font-weight:800;font-size:16px;text-decoration:none;text-align:center">'.esc_html($lbl_asm).'</a>';
        echo '</div>';

    } elseif ( $d && ! $u ) {
        $dl = enx_district_label($d,$lang)?:enx_labelize($d);
        echo '<div class="enx-breadcrumb"><a href="'.esc_url($base).'">ULB</a><span>›</span><span>'.esc_html($dl).'</span></div>';
        echo '<span class="enx-section-tag enx-tag-ulb">🏙️ ULB Elections 2026</span>';
        echo '<h1>'.esc_html($dl).'</h1>';
        $cats = ['municipal_corporation'=>(ENX_IS_HI?'नगर निगम':'Municipal Corporations'),'municipal_council'=>(ENX_IS_HI?'नगर परिषद':'Municipal Councils'),'nagar_panchayat'=>(ENX_IS_HI?'नगर पंचायत':'Nagar Panchayats')];
        foreach ( $cats as $cat => $cat_label ) {
            $filtered = array_filter(isset($udata[$d]['ulbs'])?$udata[$d]['ulbs']:[], function($u) use($cat){ return $u['ulb_type']===$cat; });
            if(empty($filtered)) continue;
            echo '<h2>'.esc_html($cat_label).'</h2><div class="enx-dir-grid">';
            foreach($filtered as $us=>$ud){
                $ul=$lang==='hi'?($ud['label_hi']??$ud['label_en']??$us):($ud['label_en']??$us);
                echo '<a class="enx-dir-card" href="'.esc_url($base.$d.'/'.$us.'/').'">'.esc_html($ul).'</a>';
            }
            echo '</div>';
        }

    } elseif ( $d && $u && ! $w ) {
        $dl = enx_district_label($d,$lang)?:enx_labelize($d);
        $ul = enx_ulb_label($d,$u,$lang)?:enx_labelize($u);
        echo '<div class="enx-breadcrumb"><a href="'.esc_url($base).'">ULB</a><span>›</span><a href="'.esc_url($base.$d.'/').'">'.esc_html($dl).'</a><span>›</span><span>'.esc_html($ul).'</span></div>';
        echo '<span class="enx-section-tag enx-tag-ulb">🏙️ ULB Elections 2026</span>';
        echo '<h1>'.esc_html($ul).'</h1>';
        $wards = $udata[$d]['ulbs'][$u]['wards']??[];
        if(!empty($wards)){
            echo '<h2>'.(ENX_IS_HI?'वार्ड चुनें':'Browse by Ward').'</h2><div class="enx-dir-grid">';
            foreach($wards as $ws=>$wd){
                $wl=$lang==='hi'?($wd['label_hi']??$wd['label_en']??$ws):($wd['label_en']??$ws);
                echo '<a class="enx-dir-card" href="'.esc_url($base.$d.'/'.$u.'/'.$ws.'/').'">'.esc_html($wl).'</a>';
            }
            echo '</div>';
        }
        echo '<h2>'.(ENX_IS_HI?'सभी उम्मीदवार':'All Candidates').'</h2>';
        enx_candidate_grid(['ulb'=>$u,'election_type'=>'ulb'],$lang);

    } else {
        $ul_raw = enx_ulb_label($d,$u,$lang);
        $ul = $lang==='hi' ? ($ul_raw ?: enx_labelize($u)) : ($ul_raw ?: enx_labelize($u));
        $wl=enx_ward_label($d,$u,$w,$lang)?:enx_labelize($w);
        echo '<div class="enx-breadcrumb"><a href="'.esc_url($base.$d.'/'.$u.'/').'">'.esc_html($ul).'</a><span>›</span><span>'.esc_html($wl).'</span></div>';
        echo '<span class="enx-section-tag enx-tag-ulb">🏙️ ULB Elections 2026</span>';
        echo '<h1>'.esc_html($wl).', '.esc_html($ul).'</h1>';
        enx_candidate_grid(['ulb'=>$u,'ward'=>$w,'election_type'=>'ulb'],$lang);
    }
    echo '</div>';
}

/* ── Assembly directory ────────────────────────────────────────────────── */
function enx_render_assembly_dir( $segments ) {
    $lang = ENX_IS_HI ? 'hi' : 'en';
    $base = home_url('/assembly-elections/');
    $c    = sanitize_title( $segments[1] ?? '' );
    $asm  = enx_get_assembly_constituencies();

    echo '<div class="enx-dir-wrap">';
    if ( ! $c ) {
        echo '<span class="enx-section-tag enx-tag-asm">🏛️ Assembly Elections 2027</span>';
        echo '<h1>'.(ENX_IS_HI?'हिमाचल प्रदेश विधानसभा चुनाव 2027':'HP Assembly Elections 2027').'</h1>';
        $by_d=[];
        foreach($asm as $slug=>$con) $by_d[$con['district']][$slug]=$con;
        foreach($by_d as $dist=>$cons){
            $dl=enx_district_label($dist,$lang)?:enx_labelize($dist);
            echo '<h2>'.esc_html($dl).'</h2><div class="enx-dir-grid">';
            foreach($cons as $slug=>$con){
                $label=$lang==='hi'?($con['label_hi']??$con['label_en']??$slug):($con['label_en']??$slug);
                echo '<a class="enx-dir-card" href="'.esc_url($base.$slug.'/').'">'.esc_html($label).'</a>';
            }
            echo '</div>';
        }
        // Cross-election nav
        $lbl_pan2 = ENX_IS_HI ? '🏘️ पंचायत चुनाव 2026'      : '🏘️ HP Panchayat Elections 2026';
        $lbl_ulb2 = ENX_IS_HI ? '🏙️ नगर निकाय चुनाव 2026'   : '🏙️ HP ULB Elections 2026';
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:32px 0 8px">';
        echo '<a href="'.esc_url(home_url('/elections/')).'" style="display:flex;align-items:center;justify-content:center;padding:18px;background:linear-gradient(135deg,#0e3368,#2b6cb0);color:#fff;border-radius:14px;font-weight:800;font-size:16px;text-decoration:none;text-align:center">'.esc_html($lbl_pan2).'</a>';
        echo '<a href="'.esc_url(home_url('/ulb-elections/')).'" style="display:flex;align-items:center;justify-content:center;padding:18px;background:linear-gradient(135deg,#4c1d95,#7c3aed);color:#fff;border-radius:14px;font-weight:800;font-size:16px;text-decoration:none;text-align:center">'.esc_html($lbl_ulb2).'</a>';
        echo '</div>';
    } else {
        $cl=enx_constituency_label($c,$lang)?:enx_labelize($c);
        echo '<div class="enx-breadcrumb"><a href="'.esc_url($base).'">'.($lang==='hi'?'सभी क्षेत्र':'All Constituencies').'</a><span>›</span><span>'.esc_html($cl).'</span></div>';
        echo '<span class="enx-section-tag enx-tag-asm">🏛️ Assembly Elections 2027</span>';
        echo '<h1>'.esc_html($cl).'</h1>';
        enx_candidate_grid(['constituency'=>$c,'election_type'=>'assembly'],$lang);
    }
    echo '</div>';
}

/* ── Candidate grid with pagination ────────────────────────────────────── */
function enx_candidate_grid( $filters, $lang = 'en', $per_page = 16 ) {
    // Search & filter from GET params
    $search_q    = sanitize_text_field( $_GET['cand_s']    ?? '' );
    
    $filter_type = sanitize_text_field( $_GET['cand_type'] ?? '' );

    $mq = ['relation'=>'AND'];
    if (!empty($filters['election_type'])) $mq[]=['key'=>'election_type','value'=>$filters['election_type'],'compare'=>'='];
    if (!empty($filters['district']))      $mq[]=['key'=>'district_slug','value'=>$filters['district'],'compare'=>'='];
    if (!empty($filters['block']))         $mq[]=['key'=>'block_slug','value'=>$filters['block'],'compare'=>'='];
    if (!empty($filters['panchayat']))     $mq[]=['key'=>'panchayat_slug','value'=>$filters['panchayat'],'compare'=>'='];
    if (!empty($filters['ulb']))           $mq[]=['key'=>'ulb_slug','value'=>$filters['ulb'],'compare'=>'='];
    if (!empty($filters['ward']))          $mq[]=['key'=>'ward_slug','value'=>$filters['ward'],'compare'=>'='];
    if (!empty($filters['constituency']))  $mq[]=['key'=>'constituency_slug','value'=>$filters['constituency'],'compare'=>'='];
    if (!empty($filters['zp_ward']))       $mq[]=['key'=>'zp_ward_slug','value'=>$filters['zp_ward'],'compare'=>'='];
    if (!empty($filters['bdc_ward']))      $mq[]=['key'=>'bdc_ward_slug','value'=>$filters['bdc_ward'],'compare'=>'='];

    // Candidate type filter
    if ( $filter_type === 'photo' )        $mq[]=['key'=>'profile_tier_text','value'=>'premium','compare'=>'='];
    elseif ( $filter_type && $filter_type !== 'photo' )
                                           $mq[]=['key'=>'contest_slug','value'=>$filter_type,'compare'=>'LIKE'];

    $paged_qv = get_query_var('paged');
    if ( ! $paged_qv ) $paged_qv = get_query_var('page');
    if ( ! $paged_qv ) $paged_qv = isset($_GET['paged']) ? (int)$_GET['paged'] : 1;
    $paged = max(1,(int)$paged_qv);

    $q_args = ['post_type'=>'candidate','post_status'=>'publish','posts_per_page'=>$per_page,'paged'=>$paged,'meta_query'=>$mq,'orderby'=>'date','order'=>'DESC'];
    if ( $search_q ) $q_args['s'] = $search_q;

    $q = new WP_Query($q_args);

    // Render search bar
    $cur_url = strtok($_SERVER['REQUEST_URI'],'?');
    $lbl_search = ENX_IS_HI ? 'उम्मीदवार खोजें...' : 'Search candidates...';

    $lbl_btn    = ENX_IS_HI ? 'खोजें' : 'Search';
    echo '<form class="enx-search-bar" method="get" action="'.esc_url($cur_url).'">';
    // Pass through all existing GET params except search ones
    foreach ( $_GET as $k => $v ) {
        if ( in_array($k, ['cand_s','cand_tier','cand_type','paged']) ) continue;
        echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">';
    }
    echo '<input type="text" name="cand_s" placeholder="'.esc_attr($lbl_search).'" value="'.esc_attr($search_q).'">';
    $type_options = ENX_IS_HI ? [
        '' => 'सभी',
        'photo' => 'फोटो सहित',
        'pradhan' => 'प्रधान',
        'up-pradhan' => 'उप-प्रधान',
        'zila-parishad' => 'जिला परिषद',
        'panchayat-samiti' => 'पंचायत समिति (BDC)',
    ] : [
        '' => 'All',
        'photo' => 'Profiles with Photo',
        'pradhan' => 'Pradhan',
        'up-pradhan' => 'Up-Pradhan',
        'zila-parishad' => 'Zila Parishad',
        'panchayat-samiti' => 'BDC Members',
    ];

    echo '<select name="cand_type">';
    foreach ( $type_options as $tv => $tl ) {
        echo '<option value="'.esc_attr($tv).'"'.selected($filter_type,$tv,false).'>'.esc_html($tl).'</option>';
    }
    echo '</select>';
    echo '<button type="submit">🔍 '.esc_html($lbl_btn).'</button>';
    if ( $search_q || $filter_type ) echo '<a href="'.esc_url($cur_url).'" style="font-size:12px;color:#888;white-space:nowrap">✕ '.($lang==='hi'?'हटाएं':'Clear').'</a>';
    echo '</form>';

    if (!$q->have_posts()) { echo '<p class="enx-no-cand">'.(ENX_IS_HI?'इस क्षेत्र में अभी कोई उम्मीदवार नहीं।':'No candidates found in this area yet.').'</p>'; return; }

    $placeholder = enx_placeholder_url();
    echo '<div class="enx-cand-grid">';
    while($q->have_posts()){$q->the_post();$pid=get_the_ID();
        $name  = ENX_IS_HI ? (get_post_meta($pid,'candidate_name_hi',true)?:get_post_meta($pid,'candidate_name_text',true)) : get_post_meta($pid,'candidate_name_text',true);
        $name  = $name ?: get_the_title($pid);
        $et    = get_post_meta($pid,'election_type',true)?:'panchayat';
        $tier  = get_post_meta($pid,'profile_tier_text',true)?:'basic';
        $loc   = enx_resolve_location($pid,$lang);
        $c_slug_card = get_post_meta($pid,'contest_slug',true);
        $is_zp_card  = in_array($c_slug_card,['zila-parishad','zila-parishad-member'],true);
        $is_bdc_card = in_array($c_slug_card,['panchayat-samiti','panchayat-samiti-bdc','panchayat-samiti-member','panchayat-samiti-member-bdc','bdc-member'],true);
        if($et==='assembly')    $area=$loc['constituency']?:enx_labelize($loc['constituency_slug']);
        elseif($et==='ulb')     $area=implode(', ',array_filter([$loc['ward'],$loc['ulb']]));
        elseif($is_zp_card)     $area=get_post_meta($pid,'zp_ward_text',true)?:$loc['panchayat'];
        elseif($is_bdc_card) {
            $bdc_area = '';
            if ( ($va=trim((string)get_post_meta($pid,'bdc_ward_text',true))) !== '' )     $bdc_area=$va;
            elseif ( ($va=trim((string)get_post_meta($pid,'panchayat_text',true))) !== '' ) $bdc_area=$va;
            elseif ( ($va=trim((string)$loc['panchayat'])) !== '' )                         $bdc_area=$va;
            elseif ( ($va=trim((string)$loc['block'])) !== '' )                             $bdc_area=$va;
            $area = $bdc_area;
        }
        else                    $area=$loc['panchayat']?:'';
        $contest = enx_get_contest_label_for_post($pid,$lang);
        $photo   = ($tier==='premium') ? enx_photo_url_raw($pid,'medium') : $placeholder;
        if(!$photo) $photo=$placeholder;
        $cs    = get_post_meta($pid,'contest_slug',true);
        $theme = enx_poster_theme($cs);
        echo '<div class="enx-cand-card" style="--accent:'.esc_attr($theme['secondary']).'">
            <a href="'.esc_url(get_permalink($pid)).'"><img src="'.esc_url($photo).'" alt="'.esc_attr($name).'" loading="lazy"></a>
            <div class="enx-cand-body">';
        if($contest) echo '<span class="enx-cand-pos">'.esc_html($contest).'</span>';
        echo '<h3 class="enx-cand-name"><a href="'.esc_url(get_permalink($pid)).'">'.esc_html($name).'</a></h3>';
        if($area) echo '<div class="enx-cand-meta">'.esc_html($area).'</div>';
        echo '<a class="enx-cand-btn" href="'.esc_url(get_permalink($pid)).'">'.(ENX_IS_HI?'प्रोफ़ाइल देखें':'View Profile').'</a>';
        echo '</div></div>';
    }
    echo '</div>';
    if($q->max_num_pages>1){
        echo '<div class="enx-pager">';
        if($paged>1) echo '<a href="'.esc_url(add_query_arg('paged',$paged-1,$cur_url)).'">‹</a>';
        for($i=1;$i<=$q->max_num_pages;$i++){
            if($i===$paged) echo '<span class="cur">'.$i.'</span>';
            else echo '<a href="'.esc_url(add_query_arg('paged',$i,$cur_url)).'">'.$i.'</a>';
        }
        if($paged<$q->max_num_pages) echo '<a href="'.esc_url(add_query_arg('paged',$paged+1,$cur_url)).'">›</a>';
        echo '</div>';
    }
    wp_reset_postdata();
}

/* ── Shortcodes (backward compat + page-based usage) ─────────────────── */
add_shortcode('enx_elections_home', function(){
    ob_start(); ?>
    <div class="enx-dir-wrap">
        <h1>HP Elections</h1>
        <p>Browse candidate profiles for all elections in Himachal Pradesh.</p>
        <div class="enx-home-grid">
            <a href="<?php echo esc_url(home_url('/elections/')) ?>" class="enx-home-card enx-home-card-pan"><span class="icon">🏘️</span>Panchayat Elections 2026</a>
            <a href="<?php echo esc_url(home_url('/ulb-elections/')) ?>" class="enx-home-card enx-home-card-ulb"><span class="icon">🏙️</span>Urban Local Body Elections 2026</a>
            <a href="<?php echo esc_url(home_url('/assembly-elections/')) ?>" class="enx-home-card enx-home-card-asm"><span class="icon">🏛️</span>Assembly Elections 2027</a>
        </div>
    </div>
    <?php return ob_get_clean();
});

// These shortcodes also work via template redirect — shortcodes provided for flexibility
add_shortcode('enx_dynamic_elections',function(){ ob_start(); enx_render_panchayat_dir(array_values(array_filter(explode('/',trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'))))); return ob_get_clean(); });
add_shortcode('enx_dynamic_ulb_elections',function(){ ob_start(); enx_render_ulb_dir(array_values(array_filter(explode('/',trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'))))); return ob_get_clean(); });
add_shortcode('enx_dynamic_assembly',function(){ ob_start(); enx_render_assembly_dir(array_values(array_filter(explode('/',trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'))))); return ob_get_clean(); });
add_shortcode('candidate_list',function($atts){ $atts=shortcode_atts(['district'=>'','block'=>'','panchayat'=>'','ulb'=>'','ward'=>'','constituency'=>'','election_type'=>'','per_page'=>8],$atts); ob_start(); enx_candidate_grid(array_filter($atts),ENX_IS_HI?'hi':'en',$atts['per_page']); return ob_get_clean(); });
