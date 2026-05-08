<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'the_content', function( $content ) {
    if ( ! is_singular('candidate') || ! in_the_loop() || ! is_main_query() ) return $content;
    global $post;
    if ( ! $post || $post->post_type !== 'candidate' ) return $content;
    return enx_render_profile( $post->ID );
}, 5 );

/* Disable wpautop / wptexturize for candidate singulars — our HTML is hand-crafted
   and wpautop will wrap stray whitespace in <p> tags which break grid layouts and
   split <a> cards into separate grid cells. */
add_action( 'wp', function() {
    if ( ! is_singular('candidate') ) return;
    remove_filter( 'the_content', 'wpautop' );
    remove_filter( 'the_content', 'wptexturize' );
    remove_filter( 'the_excerpt', 'wpautop' );
} );

/* ── YouTube / video URL → embed URL ────────────────────────────────────── */
function enx_video_embed_url( $url ) {
    $url = trim( (string) $url );
    if ( ! $url ) return '';
    // youtu.be/ID
    if ( preg_match( '~youtu\.be/([A-Za-z0-9_-]{6,})~', $url, $m ) ) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    // youtube.com/watch?v=ID
    if ( preg_match( '~youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{6,})~', $url, $m ) ) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    // youtube.com/shorts/ID
    if ( preg_match( '~youtube\.com/shorts/([A-Za-z0-9_-]{6,})~', $url, $m ) ) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    // youtube.com/embed/ID — already an embed URL
    if ( preg_match( '~youtube\.com/embed/([A-Za-z0-9_-]{6,})~', $url, $m ) ) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    // facebook video
    if ( preg_match( '~facebook\.com/.*?/videos/(\d+)~', $url, $m ) ) {
        return 'https://www.facebook.com/plugins/video.php?href=' . urlencode($url);
    }
    return ''; // not a recognised video host
}

/* ── Render Candidate Message + Interviews & Coverage section ───────────── */
function enx_render_video_section( $post_id, $lang = 'en' ) {
    $L = function( $en, $hi ) use ($lang) { return $lang === 'hi' ? $hi : $en; };

    $main_url = trim( (string) get_post_meta( $post_id, 'candidate_video_url', true ) );
    $main_embed = $main_url ? enx_video_embed_url( $main_url ) : '';

    $interviews_raw = (string) get_post_meta( $post_id, 'candidate_interviews_urls', true );
    $interview_urls = [];
    if ( $interviews_raw !== '' ) {
        // Accept comma- or newline-separated list
        $parts = preg_split( '/[\r\n,]+/', $interviews_raw );
        foreach ( $parts as $p ) {
            $p = trim($p);
            if ( ! $p ) continue;
            $emb = enx_video_embed_url( $p );
            if ( $emb ) $interview_urls[] = $emb;
        }
    }

    if ( ! $main_embed && empty($interview_urls) ) return '';

    // Build LEFT column (Candidate Message)
    $left_html = '';
    if ( $main_embed ) {
        $left_html .= '<div class="enx-card">';
        $left_html .=   '<div class="enx-card-head">' . esc_html( $L('Candidate Message','उम्मीदवार का संदेश') ) . '</div>';
        $left_html .=   '<div class="enx-card-body">';
        $left_html .=     '<div class="enx-video"><iframe src="' . esc_url($main_embed) . '" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
        $left_html .=   '</div>';
        $left_html .= '</div>';
    }

    // Build RIGHT column (Interviews & Coverage)
    $right_html = '';
    if ( ! empty($interview_urls) ) {
        $videos_html = '';
        foreach ( $interview_urls as $u ) {
            $videos_html .= '<div class="enx-video"><iframe src="' . esc_url($u) . '" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
        }
        $right_html .= '<div class="enx-card">';
        $right_html .=   '<div class="enx-card-head">' . esc_html( $L('Interviews &amp; Coverage','साक्षात्कार और कवरेज') ) . '</div>';
        $right_html .=   '<div class="enx-card-body">';
        $right_html .=     '<div class="enx-video-grid">' . $videos_html . '</div>';
        $right_html .=   '</div>';
        $right_html .= '</div>';
    }

    // Two-column layout (collapses on mobile via existing .enx-two-col rules).
    // If only one side has content, render it full-width with no two-col wrapper.
    if ( $left_html && $right_html ) {
        return '<div class="enx-two-col">' . $left_html . $right_html . '</div>';
    }
    if ( $left_html )  return $left_html;
    if ( $right_html ) return $right_html;
    return '';
}

/* ── Render related candidates carousel ─────────────────────────────────── */
function enx_render_related_candidates( $post_id, $lang = 'en' ) {
    $L = function( $en, $hi ) use ($lang) { return $lang === 'hi' ? $hi : $en; };

    $district     = get_post_meta($post_id,'district_slug',true);
    $block        = get_post_meta($post_id,'block_slug',true);
    $ulb          = get_post_meta($post_id,'ulb_slug',true);
    $constituency = get_post_meta($post_id,'constituency_slug',true);
    $election_type= get_post_meta($post_id,'election_type',true) ?: 'panchayat';

    $mq = [['key'=>'election_type','value'=>$election_type,'compare'=>'=']];
    if ( $election_type === 'assembly' && $constituency ) {
        $mq[] = ['key'=>'constituency_slug','value'=>$constituency,'compare'=>'='];
    } elseif ( $election_type === 'ulb' && $ulb ) {
        $mq[] = ['key'=>'ulb_slug','value'=>$ulb,'compare'=>'='];
    } elseif ( $block ) {
        $mq[] = ['key'=>'block_slug','value'=>$block,'compare'=>'='];
    } elseif ( $district ) {
        $mq[] = ['key'=>'district_slug','value'=>$district,'compare'=>'='];
    } else {
        return '';
    }

    $q = new WP_Query([
        'post_type'      => 'candidate',
        'post_status'    => 'publish',
        'posts_per_page' => 6,
        'post__not_in'   => [$post_id],
        'meta_query'     => $mq,
        'orderby'        => 'rand',
        'no_found_rows'  => true,
    ]);

    // Fallback: widen to district if block search returned nothing
    if ( ! $q->have_posts() && $district && $block ) {
        $q = new WP_Query([
            'post_type'      => 'candidate',
            'post_status'    => 'publish',
            'posts_per_page' => 6,
            'post__not_in'   => [$post_id],
            'meta_query'     => [
                ['key'=>'election_type','value'=>$election_type,'compare'=>'='],
                ['key'=>'district_slug','value'=>$district,'compare'=>'='],
            ],
            'orderby'        => 'rand',
            'no_found_rows'  => true,
        ]);
    }

    if ( ! $q->have_posts() ) return '';

    $items_html = '';
    while ( $q->have_posts() ) { $q->the_post();
        $rid    = get_the_ID();
        if ( $lang === 'hi' ) {
            $rname = get_post_meta($rid,'candidate_name_hi',true);
            if ( ! $rname ) $rname = get_post_meta($rid,'candidate_name_text',true);
            if ( ! $rname ) $rname = get_the_title($rid);
        } else {
            $rname = get_post_meta($rid,'candidate_name_text',true);
            if ( ! $rname ) $rname = get_the_title($rid);
        }
        $rphoto = enx_photo_url($rid,'medium');
        $rcontest = enx_get_contest_label_for_post($rid,$lang);
        $rloc_obj = enx_resolve_location($rid,$lang);
        $rcontest_slug = get_post_meta($rid,'contest_slug',true);
        $r_is_bdc = in_array($rcontest_slug,[
            'panchayat-samiti','panchayat-samiti-bdc','panchayat-samiti-member',
            'panchayat-samiti-member-bdc','bdc-member'
        ],true);
        $r_is_zp  = in_array($rcontest_slug,['zila-parishad','zila-parishad-member'],true);
        if ( $r_is_zp ) {
            $rloc = $rloc_obj['zp_ward'];
            if ( ! $rloc ) $rloc = $rloc_obj['district'];
        } elseif ( $r_is_bdc ) {
            $rloc = $rloc_obj['block'];
            if ( ! $rloc ) $rloc = $rloc_obj['district'];
        } elseif ( $election_type === 'assembly' ) {
            $rloc = $rloc_obj['constituency'];
            if ( ! $rloc ) $rloc = $rloc_obj['district'];
        } elseif ( $election_type === 'ulb' ) {
            $rloc = $rloc_obj['ulb'];
            if ( ! $rloc ) $rloc = $rloc_obj['district'];
        } else {
            $rloc = $rloc_obj['panchayat'];
            if ( ! $rloc ) $rloc = $rloc_obj['block'];
            if ( ! $rloc ) $rloc = $rloc_obj['district'];
        }

        $items_html .= '<a class="enx-related-card" href="' . esc_url(get_permalink()) . '">';
        $items_html .=   '<img src="' . esc_url($rphoto) . '" alt="' . esc_attr($rname) . '" loading="lazy">';
        $items_html .=   '<div class="enx-related-info">';
        $items_html .=     '<h4>' . esc_html($rname) . '</h4>';
        if ( $rcontest ) {
            $items_html .= '<span class="enx-related-role">' . esc_html($rcontest) . '</span>';
        }
        if ( $rloc ) {
            $items_html .= '<span class="enx-related-loc">' . esc_html($rloc) . '</span>';
        }
        $items_html .=   '</div>';
        $items_html .= '</a>';
    }
    wp_reset_postdata();

    if ( $items_html === '' ) return '';

    $head = $L('Other Candidates in this Area','इस क्षेत्र के अन्य उम्मीदवार');
    $out  = '<div class="enx-card">';
    $out .=   '<div class="enx-card-head">' . esc_html($head) . '</div>';
    $out .=   '<div class="enx-card-body">';
    $out .=     '<div class="enx-related-grid">' . $items_html . '</div>';
    $out .=   '</div>';
    $out .= '</div>';
    return $out;
}

/* ── Render election news grid ──────────────────────────────────────────── */
function enx_render_election_news( $news_category_slug, $lang = 'en' ) {
    $L = function( $en, $hi ) use ($lang) { return $lang === 'hi' ? $hi : $en; };
    if ( ! $news_category_slug ) return '';

    $cat = get_category_by_slug( $news_category_slug );
    if ( ! $cat ) return '';

    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 6,
        'cat'            => $cat->term_id,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ]);

    if ( ! $q->have_posts() ) return '';

    $items_html = '';
    while ( $q->have_posts() ) { $q->the_post();
        $thumb = get_the_post_thumbnail_url(get_the_ID(),'medium');
        if ( ! $thumb ) $thumb = enx_placeholder_url();
        $excerpt = wp_trim_words( wp_strip_all_tags(get_the_excerpt()), 24, '…' );
        $date = get_the_date( $lang === 'hi' ? 'j F Y' : 'F j, Y' );

        $items_html .= '<a class="enx-news-item" href="' . esc_url(get_permalink()) . '">';
        $items_html .=   '<div class="enx-news-thumb"><img src="' . esc_url($thumb) . '" alt="" loading="lazy"></div>';
        $items_html .=   '<div class="enx-news-text">';
        $items_html .=     '<div class="enx-news-title">' . esc_html(get_the_title()) . '</div>';
        if ( $excerpt ) {
            $items_html .= '<div class="enx-news-exc">' . esc_html($excerpt) . '</div>';
        }
        $items_html .=     '<div class="enx-news-date">' . esc_html($date) . '</div>';
        $items_html .=   '</div>';
        $items_html .= '</a>';
    }
    wp_reset_postdata();

    if ( $items_html === '' ) return '';

    $head = $L('Election News','चुनाव समाचार');
    $out  = '<div class="enx-card">';
    $out .=   '<div class="enx-card-head">' . esc_html($head) . '</div>';
    $out .=   '<div class="enx-card-body">';
    $out .=     '<div class="enx-news-grid">' . $items_html . '</div>';
    $out .=   '</div>';
    $out .= '</div>';
    return $out;
}

/* ── Main profile renderer ──────────────────────────────────────────────── */
function enx_render_profile( $id ) {
    $lang = ENX_IS_HI ? 'hi' : 'en';
    $L    = function( $en, $hi ) use ($lang) { return $lang === 'hi' ? $hi : $en; };

    $name = ENX_IS_HI
        ? ( get_post_meta($id,'candidate_name_hi',true) ?: get_post_meta($id,'candidate_name_text',true) )
        : get_post_meta($id,'candidate_name_text',true);
    $name = $name ?: get_the_title($id);

    $tier          = strtolower( get_post_meta($id,'profile_tier_text',true) ?: 'basic' );
    $election_type = get_post_meta($id,'election_type',true) ?: 'panchayat';
    $contest_slug  = get_post_meta($id,'contest_slug',true);
    $contest_label = enx_get_contest_label_for_post($id,$lang);
    if ( !$contest_label ) $contest_label = enx_contest_label($contest_slug,$lang) ?: get_post_meta($id,'contest_text',true);

    $photo_url = enx_photo_url($id,'large');
    $loc       = enx_resolve_location($id,$lang);
    $theme     = enx_poster_theme($contest_slug);
    $accent    = $theme['secondary'] ?? '#f59e0b';

    $is_zp  = in_array($contest_slug,['zila-parishad','zila-parishad-member'],true);
    $is_bdc = in_array($contest_slug,[
        'panchayat-samiti','panchayat-samiti-bdc','panchayat-samiti-member',
        'panchayat-samiti-member-bdc','bdc-member'
    ],true);

    // ── Resolve location display ─────────────────────────────────────── //
    $highlight        = '';
    $highlight_suffix = '';
    $location_line    = '';
    $facts_extra      = [];
    $details_extra    = [];
    $election_label   = '';
    $news_category    = '';
    $dir_url          = home_url('/elections/');

    if ( $election_type === 'assembly' ) {
        $highlight     = $loc['constituency'];
        $location_line = $loc['district'];
        $facts_extra   = [
            [$L('Constituency','विधानसभा क्षेत्र'),$loc['constituency']],
            [$L('District','जिला'),$loc['district']]
        ];
        $details_extra = [
            [$L('Name','नाम'),$name],
            [$L('Position','पद'),$contest_label],
            [$L('Constituency','विधानसभा क्षेत्र'),$loc['constituency']],
            [$L('District','जिला'),$loc['district']],
        ];
        $election_label= $L('HP Assembly Elections 2027','हिमाचल विधानसभा चुनाव 2027');
        $news_category = ENX_IS_HI?'vidhansabha-chunav-2027':'assembly-elections-2027';
        $dir_url       = home_url('/assembly-elections/');

    } elseif ( $election_type === 'ulb' || enx_is_ulb_contest($contest_slug) ) {
        $highlight     = $loc['ward'] ?: $loc['ulb'];
        $location_line = implode(' • ',array_filter([$loc['ulb'],$loc['district']]));
        $facts_extra   = [
            [$L('Ward','वार्ड'),$loc['ward']],
            [$L('ULB','नगर निकाय'),$loc['ulb']],
            [$L('District','जिला'),$loc['district']]
        ];
        $details_extra = [
            [$L('Name','नाम'),$name],
            [$L('Position','पद'),$contest_label],
            [$L('Ward','वार्ड'),$loc['ward']],
            [$L('ULB','नगर निकाय'),$loc['ulb']],
            [$L('District','जिला'),$loc['district']],
        ];
        $election_label= $L('HP ULB Elections 2026','हिमाचल नगर निकाय चुनाव 2026');
        $news_category = ENX_IS_HI?'ulb-chunav-2026':'ulb-elections-2026';
        $dir_url       = home_url('/ulb-elections/');

    } else {
        $zp_ward = $loc['zp_ward'] ?? get_post_meta($id,'zp_ward_text',true) ?? '';
        if ( $is_zp ) {
            $highlight     = $zp_ward ?: $loc['panchayat'];
            $location_line = $loc['district'];
            $facts_extra   = [
                [$L('ZP Ward','जिला परिषद वार्ड'),$zp_ward],
                [$L('District','जिला'),$loc['district']]
            ];
            $details_extra = [
                [$L('Name','नाम'),$name],
                [$L('Position','पद'),$contest_label],
                [$L('ZP Ward','जिला परिषद वार्ड'),$zp_ward],
                [$L('District','जिला'),$loc['district']],
            ];
        } elseif ( $is_bdc ) {
            $d_slug = get_post_meta($id,'district_slug',true);
            $b_slug = get_post_meta($id,'block_slug',true);
            $b_text = (string)($loc['block'] ?: get_post_meta($id,'block_text',true));
            $d_text = (string)($loc['district'] ?: get_post_meta($id,'district_text',true));
            $pan_slugs_raw = array_filter(explode(',', (string)get_post_meta($id,'panchayat_slugs',true)));
            $pan_labels = [];
            foreach ( $pan_slugs_raw as $ps ) {
                $ps = trim($ps);
                if (!$ps) continue;
                $pl = enx_panchayat_label($d_slug,$b_slug,$ps,$lang);
                if (!$pl) $pl = enx_labelize($ps);
                if ($pl) $pan_labels[] = $pl;
            }
            if ( !empty($pan_labels) ) {
                $bdc_ward = implode(', ',$pan_labels);
            } else {
                $bdc_ward_text = trim((string)get_post_meta($id,'bdc_ward_text',true));
                $pan_text      = trim((string)get_post_meta($id,'panchayat_text',true));
                $loc_pan       = trim((string)$loc['panchayat']);
                if      ($bdc_ward_text !== '') $bdc_ward = $bdc_ward_text;
                elseif  ($pan_text      !== '') $bdc_ward = $pan_text;
                elseif  ($loc_pan       !== '') $bdc_ward = $loc_pan;
                elseif  ($b_text        !== '') $bdc_ward = $b_text;
                else                            $bdc_ward = '';
            }
            $highlight     = $bdc_ward;
            $location_line = implode(' • ',array_filter([$b_text,$d_text]));
            $facts_extra   = [
                [$L('Panchayat Samiti Ward','पंचायत समिति वार्ड'),$bdc_ward],
                [$L('Block','ब्लॉक'),$b_text],
                [$L('District','जिला'),$d_text]
            ];
            $details_extra = [
                [$L('Name','नाम'),$name],
                [$L('Position','पद'),$contest_label],
                [$L('Panchayat Samiti Ward','पंचायत समिति वार्ड'),$bdc_ward],
                [$L('Block','ब्लॉक'),$b_text],
                [$L('District','जिला'),$d_text],
            ];
        } else {
            $highlight        = $loc['panchayat'];
            $highlight_suffix = $L(' Panchayat',' पंचायत');
            $location_line    = implode(' • ',array_filter([$loc['block'],$loc['district']]));
            $facts_extra      = [
                [$L('Block','ब्लॉक'),$loc['block']],
                [$L('District','जिला'),$loc['district']]
            ];
            $details_extra    = [
                [$L('Name','नाम'),$name],
                [$L('Position','पद'),$contest_label],
                [$L('Panchayat','पंचायत'),$loc['panchayat']],
                [$L('Block','ब्लॉक'),$loc['block']],
                [$L('District','जिला'),$loc['district']],
            ];
        }
        $election_label= $L('Panchayat Elections 2026','पंचायत चुनाव 2026');
        $news_category = ENX_IS_HI?'panchayat-chunav-2026':'panchayat-elections-2026';
        $dir_url       = home_url('/elections/'.($loc['district_slug']?$loc['district_slug'].'/':''));
    }

    // Add Election row to details
    $details_extra[] = [$L('Election','चुनाव'),$election_label];

    $news_url = home_url('/category/'.$news_category.'/');

    $age    = get_post_meta($id,'age_text',true);
    $gender = get_post_meta($id,'gender_text',true);
    $party  = get_post_meta($id,'party_affiliation_text',true);
    $bio_en = get_post_meta($id,'short_intro_en',true);
    $bio_hi = get_post_meta($id,'short_intro_hi',true);
    $bio    = ENX_IS_HI ? ($bio_hi ?: $bio_en) : $bio_en;

    // Localised gender label
    $gender_display = $gender;
    if ( $gender && $lang === 'hi' ) {
        if      ( $gender === 'Male' )   $gender_display = 'पुरुष';
        elseif  ( $gender === 'Female' ) $gender_display = 'महिला';
        else                             $gender_display = 'अन्य';
    }

    ob_start();
    ?>
<div class="enx-wrap"><div class="enx-profile">

<!-- HERO -->
<div class="enx-hero" style="background:<?php echo esc_attr($theme['gradient']??$theme['primary']??'linear-gradient(135deg,#112b4a,#173a63)') ?>">
    <div class="enx-hero-bg"></div>
    <div class="enx-hero-inner">
        <div class="enx-photo-card">
            <img src="<?php echo esc_url($photo_url) ?>" alt="<?php echo esc_attr($name) ?>">
        </div>
        <div class="enx-hero-text">
            <span class="enx-badge-election"><?php echo esc_html($election_label) ?></span>
            <h1 class="enx-name"><?php echo esc_html($name) ?></h1>
            <?php if($highlight): ?>
            <div class="enx-highlight" style="background:<?php echo esc_attr($accent) ?>"><?php echo esc_html($highlight.$highlight_suffix) ?></div>
            <?php endif ?>
            <div class="enx-contest"><?php echo esc_html($contest_label) ?></div>
            <?php if($location_line): ?><div class="enx-location"><?php echo esc_html($location_line) ?></div><?php endif ?>
            <?php if($party): ?><div class="enx-party"><?php echo esc_html($party) ?></div><?php endif ?>
            <div class="enx-actions">
                <button type="button" class="enx-share-btn" onclick="if(navigator.share){navigator.share({title:document.title,url:location.href});}else{navigator.clipboard&amp;&amp;navigator.clipboard.writeText(location.href).then(function(){alert('<?php echo esc_js($L('Link copied!','लिंक कॉपी हो गया!')) ?>')})}"><?php echo $L('Share Profile','प्रोफाइल शेयर करें') ?></button>
                <?php if($tier==='premium'&&is_user_logged_in()): ?>
                <a href="<?php echo esc_url(home_url('/candidate-poster/?candidate_id='.$id)) ?>" target="_blank" class="enx-poster-btn"><?php echo $L('View Poster','पोस्टर देखें') ?></a>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<!-- FACTS -->
<div class="enx-facts">
    <?php if($age): ?>
    <div class="enx-fact"><span class="enx-fact-label"><?php echo $L('Age','आयु') ?></span><span class="enx-fact-value"><?php echo esc_html($age) ?></span></div>
    <?php endif ?>
    <?php if($gender): ?>
    <div class="enx-fact"><span class="enx-fact-label"><?php echo $L('Gender','लिंग') ?></span><span class="enx-fact-value"><?php echo esc_html($gender_display) ?></span></div>
    <?php endif ?>
    <?php foreach($facts_extra as $f): if(!empty($f[1])): ?>
    <div class="enx-fact"><span class="enx-fact-label"><?php echo esc_html($f[0]) ?></span><span class="enx-fact-value"><?php echo esc_html($f[1]) ?></span></div>
    <?php endif; endforeach ?>
</div>

<!-- TWO COL: Bio + Sidebar (Explore) -->
<div class="enx-two-col">
    <div class="enx-main">
        <?php if($bio): ?>
        <div class="enx-card">
            <div class="enx-card-head"><?php echo $L('About Candidate','उम्मीदवार परिचय') ?></div>
            <div class="enx-card-body enx-bio-text"><?php echo wp_kses_post(nl2br($bio)) ?></div>
        </div>
        <?php endif ?>

        <!-- PROFILE DETAILS -->
        <?php if(!empty($details_extra)): ?>
        <div class="enx-card">
            <div class="enx-card-head"><?php echo $L('Profile Details','प्रोफ़ाइल विवरण') ?></div>
            <div class="enx-card-body">
                <div class="enx-details">
                    <?php foreach($details_extra as $d): if(!empty($d[1])): ?>
                    <div class="enx-detail"><strong><?php echo esc_html($d[0]) ?></strong><?php echo esc_html($d[1]) ?></div>
                    <?php endif; endforeach ?>
                </div>
            </div>
        </div>
        <?php endif ?>
    </div>

    <aside class="enx-sidebar">
        <?php if(is_active_sidebar('enx-candidate-sidebar')): dynamic_sidebar('enx-candidate-sidebar');
        else: ?>
        <div class="enx-card">
            <div class="enx-card-head"><?php echo $L('Explore Election','चुनाव देखें') ?></div>
            <div class="enx-card-body"><ul class="enx-explore">
                <li><a href="<?php echo esc_url($dir_url) ?>"><?php echo $L('All candidates in this area','इस क्षेत्र के सभी उम्मीदवार') ?></a></li>
                <li><a href="<?php echo esc_url($news_url) ?>"><?php echo $L('Election News','चुनाव समाचार') ?></a></li>
            </ul></div>
        </div>
        <?php endif ?>
    </aside>
</div>

<!-- CANDIDATE MESSAGE + INTERVIEWS & COVERAGE (only renders if at least one URL exists) -->
<?php echo enx_render_video_section($id, $lang); ?>

<!-- OTHER CANDIDATES IN THIS AREA -->
<?php echo enx_render_related_candidates($id, $lang); ?>

<!-- ELECTION NEWS -->
<?php echo enx_render_election_news($news_category, $lang); ?>

</div></div>
    <?php
    return ob_get_clean();
}
