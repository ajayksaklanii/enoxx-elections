<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Enqueue WP media uploader on our admin pages ───────────────────────── */
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'enx-add-candidate' ) {
        wp_enqueue_media();
    }
} );

/* ── Page: Add / Edit Candidate ─────────────────────────────────────────── */
function enx_page_add_candidate() {
    $is_admin  = current_user_can('administrator') || current_user_can('editor');
    $is_editor = current_user_can('administrator') || current_user_can('editor');
    $post_id  = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $post     = $post_id ? get_post($post_id) : null;
    $is_edit  = $post && $post->post_type === 'candidate';

    if ( $is_edit && ! $is_admin && get_post_status($post_id) === 'publish' )
        wp_die('You cannot edit a published candidate.');

    if ( isset($_POST['enx_save_candidate']) && wp_verify_nonce($_POST['_wpnonce'],'enx_save_candidate') ) {
        $result = enx_save_candidate_form();
        if ( $result && ! is_wp_error($result) ) {
            wp_redirect( admin_url('admin.php?page=enx-add-candidate&id='.$result.'&saved=1') );
            exit;
        }
    }

    if ( isset($_GET['saved']) ) {
        $st  = get_post_status($post_id);
        $msg = $is_admin ? 'Candidate saved.' : 'Submitted for review.';
        echo '<div class="notice notice-success" style="margin-bottom:14px"><p>'.$msg;
        if ( $is_admin && $st === 'publish' )
            echo ' <a href="'.esc_url(get_permalink($post_id)).'" target="_blank">View Profile ↗</a>';
        echo '</p></div>';
        $post = get_post($post_id); $is_edit = true;
    }

    $m = [];
    if ( $is_edit ) foreach ( enx_meta_fields() as $k ) $m[$k] = get_post_meta($post_id,$k,true);

    $et       = $m['election_type'] ?? 'panchayat';
    $contest  = $m['contest_text']  ?? '';
    $loc_data = enx_get_location_data();
    $ulb_data = enx_get_ulb_data();
    $asm_data = enx_get_assembly_constituencies();
    $photo_url= $is_edit ? enx_photo_url_raw($post_id,'medium') : '';
    $photo_id = $m['candidate_photo_id'] ?? 0;
    // Check any of the multi-key slots, or legacy single key
    $has_ai = ! empty( get_option('enx_ai_key_1') )
           || ! empty( get_option('enx_ai_key_2') )
           || ! empty( get_option('enx_ai_key_3') )
           || ! empty( get_option('enx_ai_key_4') )
           || ! empty( get_option('enx_openai_key') );

    $zp_pos  = ['Zila Parishad Member'];
    $bdc_pos = ['Panchayat Samiti Member (BDC)'];
    $gp_pos  = ['Pradhan','Up-Pradhan'];
    $mc_pos  = ['Mayor','Deputy Mayor','Councillor'];
    $cp_pos  = ['President','Vice President','Ward Member'];

    $saved_d  = $m['district_slug']  ?? '';
    $saved_b  = $m['block_slug']     ?? '';
    $saved_p  = $m['panchayat_slug'] ?? '';
    $saved_u  = $m['ulb_slug']       ?? '';
    $saved_w  = $m['ward_slug']      ?? '';
    $saved_uc = $m['ulb_category']   ?? '';
    ?>
    <div class="wrap enx-wrap">
    <h1 style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
        <?php echo $is_edit ? 'Edit Candidate' : 'Add New Candidate' ?>
        <a href="<?php echo admin_url('admin.php?page=enx-elections') ?>" class="enx-btn" style="margin-left:auto;background:#f0f0f0;color:#333;padding:6px 14px">← Back</a>
        <?php if($is_admin&&$is_edit&&get_post_status($post_id)==='publish'): ?>
        <a href="<?php echo esc_url(get_permalink($post_id)) ?>" target="_blank" class="enx-btn enx-btn-dark" style="padding:6px 14px">View ↗</a>
        <?php endif ?>
    </h1>

    <form method="post" id="enx-form">
    <?php wp_nonce_field('enx_save_candidate') ?>
    <?php if($is_edit): ?><input type="hidden" name="enx_post_id" value="<?php echo $post_id ?>"><?php endif ?>

    <div style="display:grid;grid-template-columns:1fr 310px;gap:18px;align-items:start">
    <div>

    <!-- BASIC INFO -->
    <div class="enx-panel">
        <h2>📋 Basic Information</h2>
        <div class="enx-grid-2">
            <div class="enx-f"><label>Name (English) *</label>
                <input type="text" name="candidate_name_text" value="<?php echo esc_attr($m['candidate_name_text']??'') ?>" required>
            </div>
            <div class="enx-f"><label>नाम (हिंदी) *</label>
                <input type="text" name="candidate_name_hi" value="<?php echo esc_attr($m['candidate_name_hi']??'') ?>" required>
            </div>
            <div class="enx-f"><label>Age *</label>
                <input type="number" name="age_text" value="<?php echo esc_attr($m['age_text']??'') ?>" min="18" max="99" required>
            </div>
            <div class="enx-f"><label>Gender *</label>
                <select name="gender_text" required>
                    <option value="">- Select -</option>
                    <?php foreach(['Male','Female','Other'] as $g): ?>
                    <option value="<?php echo $g ?>"<?php selected($m['gender_text']??'',$g) ?>><?php echo $g ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="enx-f"><label>📱 Phone Number *</label>
                <input type="text" name="phone_text" value="<?php echo esc_attr($m['phone_text']??'') ?>" placeholder="e.g. 9418XXXXXX" required>
            </div>
            <div class="enx-f"><label>WhatsApp Number <small style="font-weight:400">(if different from phone)</small></label>
                <input type="text" name="whatsapp_text" value="<?php echo esc_attr($m['whatsapp_text']??'') ?>" placeholder="Leave blank if same as phone">
            </div>
        </div>
    </div>

    <!-- ELECTION TYPE & POSITION -->
    <div class="enx-panel">
        <h2>🗳️ Election Type &amp; Position</h2>
        <div class="enx-grid-2" style="margin-bottom:16px">
            <div class="enx-f"><label>Election Type *</label>
                <select name="election_type" id="enx-et" required>
                    <option value="">- Select -</option>
                    <option value="panchayat"<?php selected($et,'panchayat') ?>>Panchayat Elections 2026</option>
                    <option value="ulb"<?php selected($et,'ulb') ?>>ULB Elections 2026</option>
                    <option value="assembly"<?php selected($et,'assembly') ?>>Assembly Elections 2027</option>
                </select>
            </div>
            <div class="enx-f"><label>Party Affiliation</label>
                <input type="text" name="party_affiliation_text" value="<?php echo esc_attr($m['party_affiliation_text']??'') ?>" placeholder="Independent, BJP, Congress...">
            </div>
        </div>

        <!-- PANCHAYAT -->
        <div id="pan-sec" style="<?php echo $et==='panchayat'?'':'display:none' ?>">
            <div class="enx-grid-2">
                <div class="enx-f"><label>District *</label>
                    <select name="district_slug" id="pan-district">
                        <option value="">- Select District -</option>
                        <?php foreach($loc_data as $s=>$d): ?>
                        <option value="<?php echo esc_attr($s) ?>"<?php selected($saved_d,$s) ?>><?php echo esc_html($d['label_en']??$s) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="enx-f"><label>Candidate Type *</label>
                    <!-- FIX: unique field name pan_contest_text avoids collision -->
                    <select name="pan_contest_text" id="pan-contest">
                        <option value="">- Select -</option>
                        <optgroup label="Zila Parishad">
                            <option value="Zila Parishad Member"<?php selected($contest,'Zila Parishad Member') ?>>Zila Parishad Member</option>
                        </optgroup>
                        <optgroup label="Panchayat Samiti">
                            <option value="Panchayat Samiti Member (BDC)"<?php selected($contest,'Panchayat Samiti Member (BDC)') ?>>Panchayat Samiti Member (BDC)</option>
                        </optgroup>
                        <optgroup label="Gram Panchayat">
                            <?php foreach($gp_pos as $gp): ?>
                            <option value="<?php echo esc_attr($gp) ?>"<?php selected($contest,$gp) ?>><?php echo esc_html($gp) ?></option>
                            <?php endforeach ?>
                        </optgroup>
                    </select>
                </div>
            </div>
            <!-- ZP ward -->
            <div id="zp-row" style="<?php echo in_array($contest,$zp_pos)?'':'display:none' ?>;margin-top:12px" class="enx-grid-2">
                <div class="enx-f"><label>Zila Parishad Ward</label>
                    <select name="zp_ward_slug" id="zp-ward-sel">
                        <option value="">- Select Ward -</option>
                        <?php
                        if ( $saved_d ) {
                            $loc_all = enx_get_location_data();
                            $zpw = isset($loc_all[$saved_d]['zp_wards']) ? $loc_all[$saved_d]['zp_wards'] : [];
                            foreach ( $zpw as $ws=>$w ) {
                                $wl=is_array($w)?($w['label_en']??$ws):$w;
                                echo '<option value="'.esc_attr($ws).'"'.selected($m['zp_ward_slug']??'',$ws,false).'>'.esc_html($wl).'</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="enx-f"><label>ZP Ward (text fallback)</label>
                    <input type="text" name="zp_ward_text" value="<?php echo esc_attr($m['zp_ward_text']??'') ?>">
                </div>
            </div>
            <!-- Admin: editable panchayat/ZP ward name override -->
            <?php if($is_admin): ?>
            <div id="name-override-row" style="margin-top:12px;background:#fff8f0;border:1px solid #fed7aa;border-radius:8px;padding:12px">
                <p style="font-size:11px;color:#92400e;margin:0 0 8px;font-weight:700">🔧 Admin: Override location name spellings (leave blank to use default)</p>
                <div class="enx-grid-2">
                    <div class="enx-f"><label style="font-size:11px">Panchayat Name Override</label>
                        <input type="text" name="panchayat_text_override" value="<?php echo esc_attr($m['panchayat_text']??'') ?>" placeholder="Corrected panchayat spelling...">
                    </div>
                    <div class="enx-f"><label style="font-size:11px">ZP Ward Name Override</label>
                        <input type="text" name="zp_ward_text_override" value="<?php echo esc_attr($m['zp_ward_text']??'') ?>" placeholder="Corrected ZP ward spelling...">
                    </div>
                </div>
            </div>
            <?php endif ?>
            <!-- Block -->
            <div id="block-row" style="<?php echo in_array($contest,array_merge($bdc_pos,$gp_pos))?'':'display:none' ?>;margin-top:12px" class="enx-grid-2">
                <div class="enx-f"><label>Block / Panchayat Samiti</label>
                    <select name="block_slug" id="pan-block">
                        <option value="">- Select Block -</option>
                        <?php
                        if ( $saved_d && isset($loc_data[$saved_d]['blocks']) ) {
                            foreach ( $loc_data[$saved_d]['blocks'] as $bs=>$bk )
                                echo '<option value="'.esc_attr($bs).'"'.selected($saved_b,$bs,false).'>'.esc_html($bk['label_en']??$bs).'</option>';
                        }
                        ?>
                    </select>
                </div>
                <!-- Pradhan/Up-Pradhan: single panchayat select -->
                <div id="pan-row" style="<?php echo in_array($contest,$gp_pos)?'':'display:none' ?>">
                    <div class="enx-f"><label>Gram Panchayat</label>
                        <select name="panchayat_slug" id="pan-panchayat">
                            <option value="">- Select Panchayat -</option>
                            <?php
                            if ( $saved_d&&$saved_b&&isset($loc_data[$saved_d]['blocks'][$saved_b]['panchayats']) ) {
                                foreach ( $loc_data[$saved_d]['blocks'][$saved_b]['panchayats'] as $ps=>$pp ) {
                                    $pl=is_array($pp)?($pp['label_en']??$ps):$pp;
                                    echo '<option value="'.esc_attr($ps).'"'.selected($saved_p,$ps,false).'>'.esc_html($pl).'</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            <!-- BDC: Multi-Panchayat Checkbox Selection -->
            <div id="bdc-ward-row" style="<?php echo in_array($contest,$bdc_pos)?'':'display:none' ?>;margin-top:12px">
                <div class="enx-f" style="grid-column:1/-1">
                    <label style="font-weight:700;display:block;margin-bottom:8px">
                        ☑️ Panchayats in this Panchayat Samiti Ward
                        <small style="font-weight:400;color:#888"> — Select 2 to 6 panchayats</small>
                    </label>
                    <div id="bdc-checkbox-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;max-height:320px;overflow-y:auto;padding:10px;border:1px solid #ddd;border-radius:8px;background:#fafafa">
                        <?php
                        $saved_pans_for_grid = array_filter(explode(',', (string)($m['panchayat_slugs']??'')));
                        if ( $saved_d && $saved_b && isset($loc_data[$saved_d]['blocks'][$saved_b]['panchayats']) ) {
                            $pans_for_grid = $loc_data[$saved_d]['blocks'][$saved_b]['panchayats'];
                            foreach ( $pans_for_grid as $ps=>$pv ) {
                                $pl = is_array($pv)?($pv['label_en']??$ps):$pv;
                                $checked = in_array($ps,$saved_pans_for_grid,true) ? 'checked' : '';
                                echo '<label style="display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid #e0e0e0;border-radius:8px;background:#fff;cursor:pointer;font-size:13px">';
                                echo '<input type="checkbox" name="panchayat_slugs[]" value="'.esc_attr($ps).'" '.$checked.' style="width:15px;height:15px;accent-color:#1e3a5f">';
                                echo '<span>'.esc_html($pl).'</span>';
                                echo '</label>';
                            }
                        } else {
                            echo '<p style="color:#888;grid-column:1/-1;margin:0">Select a District and Block first to see panchayats.</p>';
                        }
                        ?>
                    </div>
                    <small style="color:#888;margin-top:6px;display:block" id="bdc-check-count"></small>
                </div>
                <div class="enx-f" style="margin-top:10px">
                    <label>Hindi panchayat names <small style="font-weight:400">(comma-separated, for Hindi site)</small></label>
                    <input type="text" name="panchayat_slugs_hi" value="<?php echo esc_attr($m['panchayat_slugs_hi']??'') ?>" placeholder="e.g. ग्राम पंचायत 1, ग्राम पंचायत 2">
                </div>
            </div>
        </div>

        <!-- ULB -->
        <div id="ulb-sec" style="<?php echo $et==='ulb'?'':'display:none' ?>">
            <div class="enx-grid-2">
                <div class="enx-f"><label>District *</label>
                    <select name="ulb_district_slug" id="ulb-district">
                        <option value="">- Select -</option>
                        <?php foreach(array_keys($ulb_data) as $ds): ?>
                        <option value="<?php echo esc_attr($ds) ?>"<?php selected($saved_d,$ds) ?>><?php echo esc_html(enx_district_label($ds)?:enx_labelize($ds)) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="enx-f"><label>ULB Category *</label>
                    <select name="ulb_category" id="ulb-cat">
                        <option value="">- Select -</option>
                        <option value="municipal_corporation"<?php selected($saved_uc,'municipal_corporation') ?>>Municipal Corporation</option>
                        <option value="municipal_council"<?php selected($saved_uc,'municipal_council') ?>>Municipal Council</option>
                        <option value="nagar_panchayat"<?php selected($saved_uc,'nagar_panchayat') ?>>Nagar Panchayat</option>
                    </select>
                </div>
                <div class="enx-f"><label>Local Body Name</label>
                    <select name="ulb_slug" id="ulb-name">
                        <option value="">- Select ULB -</option>
                        <?php
                        if ( $saved_d && isset($ulb_data[$saved_d]['ulbs']) ) {
                            foreach ( $ulb_data[$saved_d]['ulbs'] as $us=>$ud ) {
                                if ( $saved_uc && ($ud['ulb_type']??'')!==$saved_uc ) continue;
                                echo '<option value="'.esc_attr($us).'" data-type="'.esc_attr($ud['ulb_type']??'').'"'.selected($saved_u,$us,false).'>'.esc_html($ud['label_en']??$us).'</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="enx-f"><label>Ward</label>
                    <select name="ward_slug" id="ulb-ward">
                        <option value="">- Select Ward -</option>
                        <?php
                        if ( $saved_d&&$saved_u&&isset($ulb_data[$saved_d]['ulbs'][$saved_u]['wards']) ) {
                            foreach ( $ulb_data[$saved_d]['ulbs'][$saved_u]['wards'] as $ws=>$wd ) {
                                $wl=is_array($wd)?($wd['label_en']??$ws):$wd;
                                echo '<option value="'.esc_attr($ws).'"'.selected($saved_w,$ws,false).'>'.esc_html($wl).'</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div style="margin-top:12px;background:#f9f8f5;border:1px solid #eee7d9;border-radius:8px;padding:12px">
                <div class="enx-f" style="max-width:300px;margin-bottom:10px"><label>Did the candidate win?</label>
                    <div style="display:flex;gap:16px;margin-top:5px">
                        <label style="cursor:pointer;font-size:13px;display:flex;align-items:center;gap:5px"><input type="radio" name="candidate_won" value="yes"<?php checked($m['candidate_won']??'','yes') ?> id="won-yes"> Yes, won</label>
                        <label style="cursor:pointer;font-size:13px;display:flex;align-items:center;gap:5px"><input type="radio" name="candidate_won" value="no"<?php checked($m['candidate_won']??'no','no') ?> id="won-no"> No</label>
                    </div>
                </div>
                <div id="desg-row" style="<?php echo ($m['candidate_won']??'')==='yes'?'':'display:none' ?>">
                    <div class="enx-f" style="max-width:280px"><label>Special Designation</label>
                        <!-- FIX: unique field name ulb_contest_text -->
                        <select name="ulb_contest_text" id="ulb-contest">
                            <option value="">- Select -</option>
                            <optgroup label="Municipal Corporation" id="mc-opts">
                                <?php foreach($mc_pos as $p): ?><option value="<?php echo esc_attr($p) ?>"<?php selected($contest,$p) ?>><?php echo esc_html($p) ?></option><?php endforeach ?>
                            </optgroup>
                            <optgroup label="Council / Nagar Panchayat" id="co-opts">
                                <?php foreach($cp_pos as $p): ?><option value="<?php echo esc_attr($p) ?>"<?php selected($contest,$p) ?>><?php echo esc_html($p) ?></option><?php endforeach ?>
                            </optgroup>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ASSEMBLY -->
        <div id="asm-sec" style="<?php echo $et==='assembly'?'':'display:none' ?>">
            <div class="enx-f" style="max-width:340px">
                <label>Assembly Constituency *</label>
                <select name="constituency_slug">
                    <option value="">- Select Constituency -</option>
                    <?php foreach($asm_data as $cs=>$c): ?>
                    <option value="<?php echo esc_attr($cs) ?>"<?php selected($m['constituency_slug']??'',$cs) ?>><?php echo esc_html($c['label_en'].' ('.$c['district'].')') ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
    </div>

    <!-- TELECALLER NOTES -->
    <div class="enx-panel">
        <?php if($is_admin): ?>
        <div class="enx-panel" style="border-left:4px solid #f59e0b">
            <h2>🖼️ Poster Template Override <small style="font-weight:400;font-size:12px;color:#888">(overrides global position template for this candidate only)</small></h2>
            <div class="enx-grid-2">
                <div class="enx-f"><label>English Poster Template URL</label>
                    <input type="url" name="enx_post_poster_tpl_en" value="<?php echo esc_attr(get_post_meta($post_id,'enx_post_poster_tpl_en',true)) ?>" placeholder="https://... (leave blank to use global setting)">
                </div>
                <div class="enx-f"><label>Hindi Poster Template URL</label>
                    <input type="url" name="enx_post_poster_tpl_hi" value="<?php echo esc_attr(get_post_meta($post_id,'enx_post_poster_tpl_hi',true)) ?>" placeholder="https://... (leave blank to use global setting)">
                </div>
            </div>
        </div>
        <?php endif ?>
        <h2>📝 Telecaller Notes</h2>
        <div class="enx-f">
            <label>Notes / Information Collected</label>
            <textarea name="notes_text" rows="4" placeholder="Candidate background, achievements, promises, local issues — collected during the call. Used for AI bio generation."><?php echo esc_textarea($m['notes_text']??'') ?></textarea>
            <small style="color:#888;font-size:12px">Only visible in admin. AI uses this to generate candidate biography.</small>
        </div>
    </div>

    <!-- BIO (admin only) -->
    <?php if($is_admin): ?>
    <div class="enx-panel">
        <h2>📄 Biography
            <?php if($has_ai): ?>
            <button type="button" id="enx-gen-bio" class="enx-btn enx-btn-primary" style="margin-left:12px;font-size:12px" <?php echo $is_edit?'':'disabled title="Save the candidate first"' ?>>✨ Generate Bio with AI</button>
            <span id="enx-bio-status" style="font-size:12px;margin-left:8px;color:#888"></span>
            <?php else: ?>
            <small style="font-weight:400;color:#888;margin-left:10px">Set OpenAI key in Settings → AI Bio to enable</small>
            <?php endif ?>
        </h2>
        <div class="enx-grid-2">
            <div class="enx-f enx-full"><label>Bio (English)</label>
                <textarea name="short_intro_en" rows="5" placeholder="Candidate biography in English..."><?php echo esc_textarea($m['short_intro_en']??'') ?></textarea>
            </div>
            <div class="enx-f enx-full"><label>परिचय (हिंदी)</label>
                <textarea name="short_intro_hi" rows="5" placeholder="हिंदी में परिचय..."><?php echo esc_textarea($m['short_intro_hi']??'') ?></textarea>
            </div>
        </div>
    </div>
    <?php endif ?>

    <!-- VIDEOS & SOCIAL MEDIA -->
    <div class="enx-panel">
        <h2>📹 Videos &amp; Social Media</h2>
        <div class="enx-grid-2">

            <div class="enx-f"><label>Video Message URL (YouTube/Facebook)</label>
                <input type="url" name="candidate_video_url" value="<?php echo esc_attr($m['candidate_video_url']??'') ?>" placeholder="https://youtube.com/...">
            </div>
            <div class="enx-f"><label>Interview / Coverage URLs <small>(one per line)</small></label>
                <textarea name="candidate_interviews_urls" rows="3"><?php echo esc_textarea($m['candidate_interviews_urls']??'') ?></textarea>
            </div>
            <div class="enx-f"><label>Facebook URL</label>
                <input type="url" name="facebook_url" value="<?php echo esc_attr($m['facebook_url']??'') ?>">
            </div>
            <div class="enx-f"><label>Instagram URL</label>
                <input type="url" name="instagram_url" value="<?php echo esc_attr($m['instagram_url']??'') ?>">
            </div>
            <div class="enx-f"><label>YouTube Channel URL</label>
                <input type="url" name="youtube_url" value="<?php echo esc_attr($m['youtube_url']??'') ?>">
            </div>
        </div>
    </div>

    </div><!-- /main col -->

    <!-- SIDEBAR -->
    <div>
    <div class="enx-panel">
        <h2>📷 Photo</h2>
        <div style="text-align:center;min-height:80px">
            <?php if($photo_url): ?>
            <img src="<?php echo esc_url($photo_url) ?>" id="enx-photo-img" style="max-width:130px;max-height:160px;border-radius:10px;border:2px solid #eee;display:block;margin:0 auto 8px">
            <?php else: ?>
            <div id="enx-photo-placeholder" style="width:90px;height:110px;margin:0 auto 8px;border-radius:10px;border:2px dashed #ccc;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:36px">📷</div>
            <img id="enx-photo-img" src="" style="max-width:130px;max-height:160px;border-radius:10px;border:2px solid #eee;display:none;margin:0 auto 8px">
            <?php endif ?>
        </div>
        <input type="hidden" name="candidate_photo_id" id="enx-photo-id" value="<?php echo esc_attr($photo_id) ?>">
        <button type="button" id="enx-upload-photo" class="enx-btn enx-btn-dark" style="width:100%;justify-content:center;margin-top:8px"><?php echo $photo_url?'Change Photo':'Upload Photo' ?></button>
        <?php if($photo_url): ?>
        <button type="button" id="enx-remove-photo" class="enx-btn" style="width:100%;margin-top:6px;background:#fee2e2;color:#dc2626;font-weight:700;justify-content:center">Remove</button>
        <?php endif ?>
        <p style="font-size:11px;color:#888;margin:8px 0 0;text-align:center">Auto-renamed: Name - Position - Location</p>
    </div>

    <div class="enx-panel">
        <h2>⭐ Profile Tier</h2>
        <div class="enx-tier-toggle">
            <input type="radio" name="profile_tier_text" id="tier-basic" value="basic"<?php checked($m['profile_tier_text']??'basic','basic') ?>>
            <label for="tier-basic">Basic</label>
            <input type="radio" name="profile_tier_text" id="tier-premium" value="premium"<?php checked($m['profile_tier_text']??'basic','premium') ?>>
            <label for="tier-premium">⭐ Premium</label>
        </div>
        <p style="font-size:11px;color:#888;margin-top:8px"><strong>Basic:</strong> Placeholder photo shown on frontend.<br><strong>Premium:</strong> Real photo + poster + priority listing.</p>
    </div>

    <div class="enx-panel">
        <h2>💾 Save</h2>
        <?php if($is_edit): ?>
        <p style="font-size:12px;color:#666;margin:0 0 10px">Status: <strong><?php echo get_post_status($post_id)==='publish'?'Published':'Pending Review' ?></strong></p>
        <?php endif ?>
        <?php if(!$is_admin): ?>
        <p style="font-size:11px;color:#888;margin-bottom:8px">Submission will be reviewed before publishing.</p>
        <button type="submit" name="enx_save_candidate" value="draft" class="enx-btn" style="width:100%;justify-content:center;padding:12px;font-size:14px;background:#6b7280;color:#fff;border:none;margin-bottom:8px">
            📋 Save as Draft
        </button>
        <?php else: ?>
        <button type="submit" name="enx_save_candidate" value="draft" class="enx-btn" style="width:100%;justify-content:center;padding:11px;font-size:13px;background:#6b7280;color:#fff;border:none;margin-bottom:8px">
            📋 Save as Draft
        </button>
        <?php if($is_editor||$is_admin): ?>
        <button type="submit" name="enx_save_candidate" value="publish" class="enx-btn enx-btn-primary" style="width:100%;justify-content:center;padding:12px;font-size:14px">
            💾 Save &amp; Publish
        </button>
        <?php endif ?>
        <?php endif ?>
    </div>
    </div><!-- /sidebar -->
    </div><!-- /grid -->

    <?php if($is_edit) enx_render_seo_comm_panels($post_id, $is_admin); ?>

    </form>
    </div>

    <script>
    (function(){
        var locData  = <?php echo json_encode($loc_data) ?>;
        var ulbData  = <?php echo json_encode($ulb_data) ?>;
        var ZP  = <?php echo json_encode($zp_pos) ?>;
        var BDC = <?php echo json_encode($bdc_pos) ?>;
        var GP  = <?php echo json_encode($gp_pos) ?>;

        var etSel   = document.getElementById('enx-et');
        var panSec  = document.getElementById('pan-sec');
        var ulbSec  = document.getElementById('ulb-sec');
        var asmSec  = document.getElementById('asm-sec');
        var dSel    = document.getElementById('pan-district');
        var bSel    = document.getElementById('pan-block');
        var pSel    = document.getElementById('pan-panchayat');
        var cSel    = document.getElementById('pan-contest');
        var zpRow   = document.getElementById('zp-row');
        var bkRow   = document.getElementById('block-row');
        var panRow  = document.getElementById('pan-row');
        var bdcRow  = document.getElementById('bdc-ward-row');
        var zpSel   = document.getElementById('zp-ward-sel');
        var bdcSel  = document.getElementById('bdc-ward-sel');
        var udSel   = document.getElementById('ulb-district');
        var ucSel   = document.getElementById('ulb-cat');
        var uSel    = document.getElementById('ulb-name');
        var wSel    = document.getElementById('ulb-ward');
        var desgRow = document.getElementById('desg-row');
        var mcOpts  = document.getElementById('mc-opts');
        var coOpts  = document.getElementById('co-opts');

        function reset(sel, ph){ if(sel){ sel.innerHTML = '<option value="">' + ph + '</option>'; } }

        function applyET(){
            var v = etSel.value;
            panSec.style.display = v==='panchayat' ? '' : 'none';
            ulbSec.style.display = v==='ulb'       ? '' : 'none';
            asmSec.style.display = v==='assembly'  ? '' : 'none';
        }
        etSel.addEventListener('change', applyET);

        // ── Live count under BDC checkbox grid ──
        function updateBdcCount(){
            var grid = document.getElementById('bdc-checkbox-grid');
            var out  = document.getElementById('bdc-check-count');
            if (!grid || !out) return;
            var n = grid.querySelectorAll('input[type="checkbox"]:checked').length;
            if (n === 0)      { out.textContent = ''; out.style.color = '#888'; }
            else if (n < 2)   { out.textContent = 'Selected: ' + n + ' — please select at least 2'; out.style.color = '#c2410c'; }
            else if (n > 6)   { out.textContent = 'Selected: ' + n + ' — maximum 6 allowed'; out.style.color = '#c2410c'; }
            else              { out.textContent = 'Selected: ' + n + ' panchayats ✓'; out.style.color = '#15803d'; }
        }
        // Hook count to any pre-rendered checkboxes (server-side render path)
        document.querySelectorAll('#bdc-checkbox-grid input[type="checkbox"]').forEach(function(cb){
            cb.addEventListener('change', updateBdcCount);
        });
        updateBdcCount();

        // ── Populate BDC checkbox grid for current district + block ──
        function populateBdcGrid() {
            var cbGrid = document.getElementById('bdc-checkbox-grid');
            if (!cbGrid) return;
            // Preserve any already-checked slugs so we can restore them
            var prevChecked = {};
            cbGrid.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb){ prevChecked[cb.value] = true; });
            cbGrid.innerHTML = '';
            var d = dSel ? dSel.value : '';
            var b = bSel ? bSel.value : '';
            var blk = (locData[d] && locData[d].blocks) ? locData[d].blocks[b] : null;
            var pans = blk ? blk.panchayats : null;
            if (!pans || !Object.keys(pans).length) {
                cbGrid.innerHTML = '<p style="color:#888;grid-column:1/-1;margin:0">'
                    + (b ? 'No panchayats found for selected block.' : 'Select a District and Block first to see panchayats.')
                    + '</p>';
                if (typeof updateBdcCount === 'function') updateBdcCount();
                return;
            }
            Object.keys(pans).sort(function(a,b2){
                var an = (typeof pans[a]==='object') ? (pans[a].label_en||a) : a;
                var bn = (typeof pans[b2]==='object') ? (pans[b2].label_en||b2) : b2;
                return String(an).localeCompare(String(bn));
            }).forEach(function(s){
                var p = pans[s];
                var lbl = (typeof p==='object') ? (p.label_en||s) : p;
                var wrap = document.createElement('label');
                wrap.style.cssText = 'display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid #e0e0e0;border-radius:8px;background:#fff;cursor:pointer;font-size:13px';
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.name = 'panchayat_slugs[]';
                cb.value = s;
                cb.style.cssText = 'width:15px;height:15px;accent-color:#1e3a5f';
                if (prevChecked[s]) cb.checked = true;
                cb.addEventListener('change', function(){ if (typeof updateBdcCount === 'function') updateBdcCount(); });
                var span = document.createElement('span');
                span.textContent = lbl;
                wrap.appendChild(cb);
                wrap.appendChild(span);
                cbGrid.appendChild(wrap);
            });
            if (typeof updateBdcCount === 'function') updateBdcCount();
        }

        function applyContest(){
            var v = cSel ? cSel.value : '';
            if(zpRow)  zpRow.style.display  = (ZP.indexOf(v)>=0)  ? '' : 'none';
            if(bkRow)  bkRow.style.display  = (BDC.indexOf(v)>=0 || GP.indexOf(v)>=0) ? '' : 'none';
            if(panRow) panRow.style.display = (GP.indexOf(v)>=0)  ? '' : 'none';
            if(bdcRow) bdcRow.style.display = (BDC.indexOf(v)>=0) ? '' : 'none';
            // When switching to BDC, refresh the checkbox grid for the currently selected block
            if (BDC.indexOf(v) >= 0) populateBdcGrid();
        }
        if(cSel) cSel.addEventListener('change', applyContest);

        // District → Blocks + ZP wards
        if(dSel) dSel.addEventListener('change', function(){
            var d = this.value;
            reset(bSel,'- Select Block -'); reset(pSel,'- Select Block First -'); reset(zpSel,'- Select Ward -');
            if(!locData[d]) { populateBdcGrid(); return; }
            Object.keys(locData[d].blocks||{}).sort(function(a,b){
                return ((locData[d].blocks[a]||{}).label_en||'').localeCompare((locData[d].blocks[b]||{}).label_en||'');
            }).forEach(function(s){ bSel.appendChild(new Option((locData[d].blocks[s]||{}).label_en||s, s)); });
            var zpw = locData[d].zp_wards || {};
            Object.keys(zpw).forEach(function(s){
                var w=zpw[s], lbl=(typeof w==='object')?(w.label_en||s):w;
                zpSel.appendChild(new Option(lbl, s));
            });
            populateBdcGrid();
        });

        if(bSel) bSel.addEventListener('change', function(){
            reset(pSel,'- Select Panchayat -');
            if(bdcSel) reset(bdcSel,'- Select BDC Ward -');
            var d=dSel.value, b=this.value;
            var blk=(locData[d]&&locData[d].blocks)?locData[d].blocks[b]:null;
            // Panchayats single dropdown (for Pradhan / Up-Pradhan)
            var ps=blk?blk.panchayats:null;
            if(ps) Object.keys(ps).sort(function(a,b2){
                var an=(typeof ps[a]==='object')?(ps[a].label_en||a):a;
                var bn=(typeof ps[b2]==='object')?(ps[b2].label_en||b2):b2;
                return an.localeCompare(bn);
            }).forEach(function(s){
                var p=ps[s]; pSel.appendChild(new Option((typeof p==='object')?(p.label_en||s):p,s));
            });
            // BDC: repopulate checkbox grid for selected block (always — even when grid is hidden)
            populateBdcGrid();
        });

        // ULB chain
        function filterUlbs(){
            reset(uSel,'- Select ULB -'); reset(wSel,'- Select ULB First -');
            var d=udSel?udSel.value:'', cat=ucSel?ucSel.value:'';
            if(!d||!ulbData[d]) return;
            if(mcOpts) mcOpts.style.display=(!cat||cat==='municipal_corporation')?'':'none';
            if(coOpts) coOpts.style.display=(!cat||cat!=='municipal_corporation')?'':'none';
            Object.keys(ulbData[d].ulbs||{}).sort(function(a,b){
                return ((ulbData[d].ulbs[a]||{}).label_en||'').localeCompare((ulbData[d].ulbs[b]||{}).label_en||'');
            }).forEach(function(s){
                var u=ulbData[d].ulbs[s];
                if(cat&&u.ulb_type!==cat) return;
                var o=new Option(u.label_en||s,s); o.dataset.type=u.ulb_type||''; uSel.appendChild(o);
            });
        }
        if(udSel) udSel.addEventListener('change', filterUlbs);
        if(ucSel) ucSel.addEventListener('change', filterUlbs);
        if(uSel) uSel.addEventListener('change', function(){
            reset(wSel,'- Select Ward -');
            var d=udSel.value, u=this.value;
            var wards=(ulbData[d]&&ulbData[d].ulbs&&ulbData[d].ulbs[u])?ulbData[d].ulbs[u].wards:null;
            if(!wards) return;
            Object.keys(wards).forEach(function(s){
                var w=wards[s]; wSel.appendChild(new Option((typeof w==='object')?(w.label_en||s):w,s));
            });
        });

        [document.getElementById('won-yes'),document.getElementById('won-no')].forEach(function(r){
            if(r) r.addEventListener('change',function(){ if(desgRow) desgRow.style.display=this.value==='yes'?'':'none'; });
        });

        applyContest();

        // ── Photo uploader (requires wp_enqueue_media() called above) ──
        var upBtn = document.getElementById('enx-upload-photo');
        var rmBtn = document.getElementById('enx-remove-photo');
        var phId  = document.getElementById('enx-photo-id');
        var phImg = document.getElementById('enx-photo-img');
        var phPh  = document.getElementById('enx-photo-placeholder');
        var frame;

        if(upBtn) upBtn.addEventListener('click', function(e){
            e.preventDefault();
            if(!window.wp || !wp.media){ alert('Media library not loaded. Please refresh and try again.'); return; }
            if(frame){ frame.open(); return; }
            frame = wp.media({ title:'Select Candidate Photo', button:{text:'Use this photo'}, multiple:false, library:{type:'image'} });
            frame.on('select', function(){
                var att = frame.state().get('selection').first().toJSON();
                phId.value = att.id;
                var src = (att.sizes&&att.sizes.medium) ? att.sizes.medium.url : att.url;
                phImg.src = src;
                phImg.style.display = 'block';
                if(phPh) phPh.style.display = 'none';
                upBtn.textContent = 'Change Photo';
            });
            frame.open();
        });
        if(rmBtn) rmBtn.addEventListener('click', function(){
            phId.value=''; phImg.src=''; phImg.style.display='none';
            if(phPh) phPh.style.display='flex';
        });

        // ── AI Bio ──
        var genBtn = document.getElementById('enx-gen-bio');
        if(genBtn) genBtn.addEventListener('click', function(){
            var status = document.getElementById('enx-bio-status');
            genBtn.disabled = true; status.textContent = 'Generating...';
            fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:new URLSearchParams({action:'enx_gen_bio',post_id:'<?php echo (int)$post_id ?>',nonce:'<?php echo wp_create_nonce("enx_admin") ?>'})
            }).then(function(r){return r.json();}).then(function(d){
                if(d.success){
                    document.querySelector('[name="short_intro_en"]').value=d.data.en||'';
                    document.querySelector('[name="short_intro_hi"]').value=d.data.hi||'';
                    status.textContent='✅ Bio generated!'; status.style.color='#059669';
                }else{ status.textContent='❌ '+d.data; status.style.color='#dc2626'; }
                genBtn.disabled=false;
            }).catch(function(){ status.textContent='Error'; genBtn.disabled=false; });
        });
    })();
    </script>
    <?php
}

/* ── Save handler ───────────────────────────────────────────────────────── */
function enx_save_candidate_form() {
    $post_id  = absint( $_POST['enx_post_id'] ?? 0 );
    $is_admin  = current_user_can('administrator') || current_user_can('editor');
    $is_editor = current_user_can('administrator') || current_user_can('editor');
    $et       = sanitize_text_field( $_POST['election_type'] ?? 'panchayat' );

    // Contest from the CORRECT unique field per election type
    if ( $et === 'assembly' ) {
        $contest = 'MLA Candidate';
    } elseif ( $et === 'ulb' ) {
        $won     = sanitize_text_field( $_POST['candidate_won'] ?? 'no' );
        $contest = ( $won === 'yes' )
            ? sanitize_text_field( $_POST['ulb_contest_text'] ?? 'Councillor' )
            : 'Councillor';
    } else {
        $contest = sanitize_text_field( $_POST['pan_contest_text'] ?? '' );
    }

    $name_en   = sanitize_text_field( $_POST['candidate_name_text'] ?? '' );
    $loc_label = '';
    if ( $et === 'assembly' )   $loc_label = sanitize_text_field( $_POST['constituency_slug'] ?? '' );
    elseif ( $et === 'ulb' )    $loc_label = sanitize_text_field( $_POST['ward_slug'] ?? '' ) ?: sanitize_text_field( $_POST['ulb_slug'] ?? '' );
    else                        $loc_label = sanitize_text_field( $_POST['panchayat_slug'] ?? '' ) ?: sanitize_text_field( $_POST['block_slug'] ?? '' );

    $title  = trim( implode(' ', array_filter([$name_en,$contest,$loc_label])) ) ?: ( $name_en ?: 'Candidate' );
    // Button value: 'publish' or 'draft'; non-admins always pending
    $submit_action = sanitize_key( $_POST['enx_save_candidate'] ?? 'publish' );
    if ( ! $is_admin ) {
        $status = 'pending';
    } elseif ( $submit_action === 'draft' ) {
        $status = 'draft';
    } else {
        $status = 'publish';
    }

    $pdata = ['post_type'=>'candidate','post_title'=>$title,'post_name'=>sanitize_title($title),'post_status'=>$status];
    if ( $post_id ) {
        $pdata['ID'] = $post_id;
        if ( $is_admin && get_post_status($post_id) === 'publish' ) $pdata['post_status'] = 'publish';
        wp_update_post($pdata);
    } else {
        $post_id = wp_insert_post($pdata,true);
        if ( is_wp_error($post_id) ) return $post_id;
    }

    $user = wp_get_current_user();
    update_post_meta($post_id,'submitted_by',$user->display_name ?: $user->user_login);

    $text_fields = [
        'candidate_name_text','candidate_name_hi','age_text','gender_text',
        'party_affiliation_text','profile_tier_text','election_type',
        'district_slug','block_slug','panchayat_slug','zp_ward_slug','zp_ward_text','bdc_ward_slug','bdc_ward_text','panchayat_slugs_hi',
        'ulb_category','ulb_slug','ward_slug','constituency_slug',
        'candidate_video_url','candidate_interviews_urls',
        'phone_text','whatsapp_text','facebook_url','instagram_url','youtube_url',
        'candidate_won','notes_text',
    ];
    foreach ( $text_fields as $k )
        update_post_meta($post_id,$k,sanitize_text_field($_POST[$k]??''));
    // Multi-panchayat for BDC Samiti Ward
    $pan_slugs_raw = array_map('sanitize_title', (array)($_POST['panchayat_slugs']??[]));
    $pan_slugs_str = implode(',',$pan_slugs_raw);
    update_post_meta($post_id,'panchayat_slugs',$pan_slugs_str);
    if(!empty($pan_slugs_raw)) update_post_meta($post_id,'panchayat_slug',$pan_slugs_raw[0]);
    // Per-post poster template override (admin only)
    if ( $is_admin ) {
        update_post_meta($post_id,'enx_post_poster_tpl_en',esc_url_raw($_POST['enx_post_poster_tpl_en']??''));
        update_post_meta($post_id,'enx_post_poster_tpl_hi',esc_url_raw($_POST['enx_post_poster_tpl_hi']??''));
    }
    update_post_meta($post_id,'short_intro_en',sanitize_textarea_field($_POST['short_intro_en']??''));
    update_post_meta($post_id,'short_intro_hi', sanitize_textarea_field($_POST['short_intro_hi'] ??''));

    update_post_meta($post_id,'contest_text',$contest);
    update_post_meta($post_id,'contest_slug',sanitize_title($contest));

    $eslug=['panchayat'=>'panchayat-elections-2026','ulb'=>'ulb-elections-2026','assembly'=>'assembly-elections-2027'];
    $etxt =['panchayat'=>'Panchayat Elections 2026','ulb'=>'ULB Elections 2026','assembly'=>'Assembly Elections 2027'];
    update_post_meta($post_id,'election_slug',$eslug[$et]??'');
    update_post_meta($post_id,'election_text',$etxt[$et] ??'');

    if ( $et === 'ulb' ) {
        $ud = sanitize_text_field($_POST['ulb_district_slug']??'');
        update_post_meta($post_id,'district_slug',$ud);
        update_post_meta($post_id,'district_text',enx_district_label($ud)?:$ud);
        $us = sanitize_text_field($_POST['ulb_slug']??'');
        update_post_meta($post_id,'ulb_text',enx_ulb_label($ud,$us)?:$us);
        $udata = enx_get_ulb_data();
        update_post_meta($post_id,'ulb_type',$udata[$ud]['ulbs'][$us]['ulb_type']??'');
        $ws = sanitize_text_field($_POST['ward_slug']??'');
        update_post_meta($post_id,'ward_text',enx_ward_label($ud,$us,$ws)?:$ws);
    } elseif ( $et === 'assembly' ) {
        $cs = sanitize_text_field($_POST['constituency_slug']??'');
        update_post_meta($post_id,'constituency_text',enx_constituency_label($cs)?:$cs);
        update_post_meta($post_id,'district_slug','');
    } else {
        $d = sanitize_text_field($_POST['district_slug']??'');
        $b = sanitize_text_field($_POST['block_slug']??'');
        $p = sanitize_text_field($_POST['panchayat_slug']??'');
        update_post_meta($post_id,'district_text', enx_district_label($d)?:$d);
        update_post_meta($post_id,'block_text',    enx_block_label($d,$b)?:$b);
        // Admin override for panchayat text spelling
        $pan_override = $is_admin ? sanitize_text_field($_POST['panchayat_text_override']??'') : '';
        if ( $pan_override ) {
            update_post_meta($post_id,'panchayat_text',$pan_override);
        } else {
            update_post_meta($post_id,'panchayat_text',enx_panchayat_label($d,$b,$p)?:$p);
        }
        $zp = sanitize_text_field($_POST['zp_ward_slug']??'');
        $zp_override = $is_admin ? sanitize_text_field($_POST['zp_ward_text_override']??'') : '';
        if ( $zp_override ) {
            update_post_meta($post_id,'zp_ward_text',$zp_override);
        } elseif ( $zp ) {
            $loc = enx_get_location_data();
            $zw  = isset($loc[$d]['zp_wards'][$zp]) ? $loc[$d]['zp_wards'][$zp] : null;
            $zwtx= $zw ? (is_array($zw)?($zw['label_en']??$zp):$zw) : $zp;
            update_post_meta($post_id,'zp_ward_text',$zwtx);
        }
    }

    $photo_id = absint($_POST['candidate_photo_id']??0);
    update_post_meta($post_id,'candidate_photo_id',$photo_id);
    if ( $photo_id && get_post($photo_id) ) {
        set_post_thumbnail($post_id,$photo_id);
        update_post_meta($post_id,'candidate_photo_upload',$photo_id);
        enx_rename_candidate_photo($photo_id,$post_id);
    }

    return $post_id;
}

/* ── AJAX: Generate Bio ─────────────────────────────────────────────────── */
add_action('wp_ajax_enx_gen_bio',function(){
    if(!wp_verify_nonce($_POST['nonce']??'','enx_admin')) wp_send_json_error('nonce');
    if(!current_user_can('administrator')&&!current_user_can('editor')) wp_send_json_error('permission');
    $pid=absint($_POST['post_id']??0);
    if(!$pid||get_post_type($pid)!=='candidate') wp_send_json_error('invalid');
    // Multi-key AI fallback: try each configured key in order
    $key_slots = [
        ['key'=>get_option('enx_ai_key_1'),'provider'=>get_option('enx_ai_provider_1','openai')],
        ['key'=>get_option('enx_ai_key_2'),'provider'=>get_option('enx_ai_provider_2','openai')],
        ['key'=>get_option('enx_ai_key_3'),'provider'=>get_option('enx_ai_provider_3','openai')],
        ['key'=>get_option('enx_ai_key_4'),'provider'=>get_option('enx_ai_provider_4','openai')],
        ['key'=>get_option('enx_ai_key_5'),'provider'=>get_option('enx_ai_provider_5','openai')],
    ];
    $legacy = get_option('enx_openai_key');
    if ( $legacy && ! get_option('enx_ai_key_1') ) $key_slots = [['key'=>$legacy,'provider'=>'openai']];
    $active_keys = array_values(array_filter($key_slots, function($s){ return !empty($s['key']); }));
    if ( empty($active_keys) ) wp_send_json_error('No AI API key set. Go to ENOXX Settings → AI Bio.');
    $name=get_post_meta($pid,'candidate_name_text',true);
    $name_hi=get_post_meta($pid,'candidate_name_hi',true);
    $contest=get_post_meta($pid,'contest_text',true);
    $et=get_post_meta($pid,'election_type',true);
    $age=get_post_meta($pid,'age_text',true);
    $gender=get_post_meta($pid,'gender_text',true);
    $party=get_post_meta($pid,'party_affiliation_text',true);
    $notes=get_post_meta($pid,'notes_text',true);
    $loc=enx_resolve_location($pid,'en');
    if($et==='assembly')    $ld='Assembly Constituency: '.$loc['constituency'];
    elseif($et==='ulb')     $ld=$loc['ward'].', '.$loc['ulb'].', '.$loc['district'];
    else                    $ld=$loc['panchayat'].' Panchayat, '.$loc['block'].' Block, '.$loc['district'];
    $election=$et==='assembly'?'HP Assembly 2027':($et==='ulb'?'HP ULB 2026':'HP Panchayat 2026');
    $prompt = "Write a 2-3 sentence candidate biography in English and Hindi.\n"
            . "Name: $name ($name_hi)\n"
            . "Age: $age | Gender: $gender\n"
            . "Party: " . ($party ?: 'Independent') . "\n"
            . "Position: $contest\n"
            . "Location: $ld\n"
            . "Election: $election\n"
            . "Notes: $notes\n\n"
            . "Respond with ONLY a JSON object — no markdown fences, no commentary, no explanation. "
            . "Format exactly: {\"en\":\"English bio here\",\"hi\":\"Hindi bio in Devanagari script here\"}";
    $prov_urls   = ['openai'=>'https://api.openai.com/v1/chat/completions','groq'=>'https://api.groq.com/openai/v1/chat/completions','mistral'=>'https://api.mistral.ai/v1/chat/completions','openrouter'=>'https://openrouter.ai/api/v1/chat/completions'];
    // Provider-specific default models (used only when provider !== 'openai',
    // since the enx_openai_model setting was historically OpenAI-specific).
    $prov_default_models = [
        'openai'     => get_option('enx_openai_model','gpt-4o-mini'),
        'groq'       => 'llama-3.1-8b-instant',
        'mistral'    => 'mistral-small-latest',
        'openrouter' => 'meta-llama/llama-3.1-8b-instruct:free',
    ];
    $resp = null; $resp_text = null; $last_error = 'No key';
    foreach ( $active_keys as $slot ) {
        $k = $slot['key']; $prov = $slot['provider'];
        if ( $prov === 'gemini' ) {
            $gem_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key='.$k;
            $r = wp_remote_post($gem_url,['headers'=>['Content-Type'=>'application/json'],'body'=>wp_json_encode(['contents'=>[['parts'=>[['text'=>$prompt]]]]]),'timeout'=>30]);
            if(is_wp_error($r)){$last_error='Gemini: '.$r->get_error_message();continue;}
            $code=wp_remote_retrieve_response_code($r);
            if($code!==200){
                $body=wp_remote_retrieve_body($r);
                $last_error='Gemini HTTP '.$code.' — '.substr($body,0,150);
                continue;
            }
            $gd=json_decode(wp_remote_retrieve_body($r),true);
            $resp_text=$gd['candidates'][0]['content']['parts'][0]['text']??'';
            if($resp_text) break; else { $last_error='Gemini returned empty text'; continue; }
        }
        $endpoint = $prov_urls[$prov] ?? $prov_urls['openai'];
        $model    = $prov_default_models[$prov] ?? 'gpt-4o-mini';
        $hdrs     = ['Authorization'=>'Bearer '.$k,'Content-Type'=>'application/json'];
        if($prov==='openrouter') $hdrs['HTTP-Referer']='https://enoxxnews.com';

        // Build payload. response_format=json_object is OpenAI-specific and
        // returns HTTP 400 on Mistral / older Groq models / many OpenRouter
        // models, so only send it to OpenAI itself.
        $payload = [
            'model'      => $model,
            'max_tokens' => 500,
            'messages'   => [[ 'role' => 'user', 'content' => $prompt ]],
        ];
        if ( $prov === 'openai' ) {
            $payload['response_format'] = ['type' => 'json_object'];
        }
        $r = wp_remote_post($endpoint,['headers'=>$hdrs,'body'=>wp_json_encode($payload),'timeout'=>30]);
        if(is_wp_error($r)){$last_error=strtoupper($prov).': '.$r->get_error_message();continue;}
        $code=wp_remote_retrieve_response_code($r);
        if($code!==200){
            // Capture provider error message and TRY THE NEXT KEY (don't break).
            // Old code broke on any non-200 except 429/5xx, which prevented
            // fallback when one provider rejected the request shape.
            $body=wp_remote_retrieve_body($r);
            $err_msg=$body;
            $body_json=json_decode($body,true);
            if(is_array($body_json)){
                if(isset($body_json['error']['message'])) $err_msg=$body_json['error']['message'];
                elseif(isset($body_json['message']))      $err_msg=$body_json['message'];
                elseif(isset($body_json['error']))        $err_msg=is_string($body_json['error'])?$body_json['error']:wp_json_encode($body_json['error']);
            }
            $last_error=strtoupper($prov).' HTTP '.$code.' — '.substr((string)$err_msg,0,180);
            continue;
        }
        $resp=$r; break;
    }
    if(!$resp&&!$resp_text) wp_send_json_error($last_error);

    // Determine the raw text returned by the model
    if ( $resp_text ) {
        $raw_content = $resp_text; // Gemini path
    } else {
        $body        = json_decode(wp_remote_retrieve_body($resp),true);
        $raw_content = $body['choices'][0]['message']['content'] ?? '';
    }

    // Extract a JSON object from the raw content. Models that don't have a
    // strict JSON mode often wrap the answer in ```json ... ``` fences or
    // add a sentence before/after. Strip fences and find the first {...} block.
    $json_text = trim( (string) $raw_content );
    // Remove markdown code fences if present
    $json_text = preg_replace('/^```(?:json)?\s*/i', '', $json_text);
    $json_text = preg_replace('/```\s*$/', '', $json_text);
    $parsed = json_decode( trim($json_text), true );

    // If still not valid JSON, try to extract the first {...} block
    if ( ! is_array($parsed) ) {
        if ( preg_match('/\{.*\}/s', $json_text, $m) ) {
            $parsed = json_decode($m[0], true);
        }
    }

    if ( empty($parsed['en']) ) {
        wp_send_json_error('Could not parse AI response. Raw: ' . substr( (string) $raw_content, 0, 250 ));
    }
    update_post_meta($pid,'short_intro_en',$parsed['en']);
    update_post_meta($pid,'short_intro_hi',$parsed['hi']??'');
    wp_send_json_success($parsed);
});


/* ═══════════════════════════════════════════════════════════════════════
   SEO META FIELDS — RankMath integration
   ═══════════════════════════════════════════════════════════════════════ */
// Named function (not closure) to avoid PHP serialization issues
function enx_seo_save_post_candidate( $post_id ) {
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce( $_POST['_wpnonce'], 'enx_save_candidate' ) ) return;
    if ( ! current_user_can('administrator') && ! current_user_can('editor') ) return;

    $seo_title = sanitize_text_field( $_POST['enx_seo_title'] ?? '' );
    $seo_desc  = sanitize_textarea_field( $_POST['enx_seo_desc']  ?? '' );

    if ( $seo_title ) {
        update_post_meta( $post_id, 'rank_math_title',    $seo_title );
        update_post_meta( $post_id, '_yoast_wpseo_title', $seo_title );
    }
    if ( $seo_desc ) {
        update_post_meta( $post_id, 'rank_math_description', $seo_desc );
        update_post_meta( $post_id, '_yoast_wpseo_metadesc', $seo_desc );
    }
    update_post_meta( $post_id, 'enx_seo_title', $seo_title );
    update_post_meta( $post_id, 'enx_seo_desc',  $seo_desc  );
}
add_action( 'save_post_candidate', 'enx_seo_save_post_candidate', 20 );

/* ═══════════════════════════════════════════════════════════════════════
   TELECALLER COMMUNICATION STATUS
   ═══════════════════════════════════════════════════════════════════════ */

// AJAX: Save communication log entry
add_action( 'wp_ajax_enx_save_comm_log', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'enx_admin' ) ) wp_send_json_error('nonce');
    if ( ! current_user_can('edit_posts') ) wp_send_json_error('permission');

    $post_id = absint( $_POST['post_id'] ?? 0 );
    if ( ! $post_id ) wp_send_json_error('invalid');

    $action   = sanitize_text_field( $_POST['comm_action']    ?? 'called' );
    $note     = sanitize_textarea_field( $_POST['comm_note']  ?? '' );
    $schedule = sanitize_text_field( $_POST['comm_schedule']  ?? '' );
    $status   = sanitize_text_field( $_POST['comm_status']    ?? 'contacted' );
    $user     = wp_get_current_user();

    $entry = [
        'user'     => $user->display_name ?: $user->user_login,
        'user_id'  => $user->ID,
        'action'   => $action,
        'status'   => $status,
        'note'     => $note,
        'schedule' => $schedule,
        'time'     => current_time('mysql'),
        'date'     => date('Y-m-d'),
    ];

    // Get existing log
    $log = get_post_meta( $post_id, 'enx_comm_log', true );
    $log = is_array($log) ? $log : [];
    array_unshift( $log, $entry ); // newest first
    $log = array_slice( $log, 0, 50 ); // keep last 50 entries

    update_post_meta( $post_id, 'enx_comm_log',    $log );
    update_post_meta( $post_id, 'enx_comm_status', $status );
    update_post_meta( $post_id, 'enx_comm_date',   date('Y-m-d') );

    if ( $schedule ) update_post_meta( $post_id, 'enx_comm_schedule', $schedule );

    wp_send_json_success([
        'entry'   => $entry,
        'message' => 'Logged successfully',
    ]);
});

// AJAX: Get communication log
add_action( 'wp_ajax_enx_get_comm_log', function() {
    if ( ! wp_verify_nonce( $_GET['nonce'] ?? '', 'enx_admin' ) ) wp_send_json_error('nonce');
    $post_id = absint( $_GET['post_id'] ?? 0 );
    $log     = get_post_meta( $post_id, 'enx_comm_log', true );
    wp_send_json_success( is_array($log) ? $log : [] );
});

/* ── Render SEO + Communication panels (appended to form) ─────────────── */
function enx_render_seo_comm_panels( $post_id, $is_admin ) {
    if ( ! $post_id ) return;
    $is_edit = $post_id && get_post_type($post_id) === 'candidate';
    if ( ! $is_edit ) return;

    $seo_title_saved = get_post_meta( $post_id, 'enx_seo_title', true );
    $seo_desc_saved  = get_post_meta( $post_id, 'enx_seo_desc',  true );
    $auto_title      = function_exists('enx_build_candidate_seo_title')      ? enx_build_candidate_seo_title($post_id)      : '';
    $auto_desc       = function_exists('enx_build_candidate_meta_description') ? enx_build_candidate_meta_description($post_id) : '';
    $disp_title      = $seo_title_saved ?: $auto_title;
    $disp_desc       = $seo_desc_saved  ?: $auto_desc;

    $comm_status   = get_post_meta( $post_id, 'enx_comm_status',   true ) ?: '';
    $comm_schedule = get_post_meta( $post_id, 'enx_comm_schedule', true ) ?: '';
    $comm_log      = get_post_meta( $post_id, 'enx_comm_log',      true );
    $comm_log      = is_array($comm_log) ? $comm_log : [];

    $status_labels = [
        ''             => '— Not contacted —',
        'to_call'      => '📞 To Call',
        'called'       => '✅ Called',
        'no_answer'    => '🔇 No Answer',
        'scheduled'    => '📅 Call Scheduled',
        'converted'    => '⭐ Converted to Premium',
        'not_interested'=> '❌ Not Interested',
        'call_back'    => '🔁 Call Back Later',
    ];
    $status_colors = [
        ''             => '#6b7280',
        'to_call'      => '#f59e0b',
        'called'       => '#059669',
        'no_answer'    => '#dc2626',
        'scheduled'    => '#3b82f6',
        'converted'    => '#d97706',
        'not_interested'=> '#9ca3af',
        'call_back'    => '#8b5cf6',
    ];
    $nonce = wp_create_nonce('enx_admin');
    ?>

    <?php if ($is_admin): ?>
    <!-- SEO Panel -->
    <div class="enx-panel" style="grid-column:1/-1">
        <h2>🔍 SEO (RankMath / Yoast)
            <small style="font-weight:400;font-size:12px;color:#888;margin-left:8px">Overrides auto-generated SEO for this candidate</small>
        </h2>
        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;color:#0369a1">
            <strong>Auto-generated SEO:</strong><br>
            Title: <em><?php echo esc_html($auto_title) ?></em><br>
            Desc: <em><?php echo esc_html($auto_desc) ?></em>
        </div>
        <div class="enx-grid-2">
            <div class="enx-f enx-full">
                <label>SEO Title <small style="font-weight:400">(leave blank to use auto-generated)</small></label>
                <input type="text" name="enx_seo_title" value="<?php echo esc_attr($disp_title) ?>"
                       placeholder="<?php echo esc_attr($auto_title) ?>"
                       style="font-size:13px"
                       maxlength="70">
                <small id="seo-title-count" style="color:#888"></small>
            </div>
            <div class="enx-f enx-full">
                <label>Meta Description <small style="font-weight:400">(leave blank to use auto-generated)</small></label>
                <textarea name="enx_seo_desc" rows="3"
                          placeholder="<?php echo esc_attr($auto_desc) ?>"
                          maxlength="160"><?php echo esc_textarea($disp_desc) ?></textarea>
                <small id="seo-desc-count" style="color:#888"></small>
            </div>
        </div>
        <script>
        (function(){
            var tf=document.querySelector('[name="enx_seo_title"]'),tc=document.getElementById('seo-title-count');
            var df=document.querySelector('[name="enx_seo_desc"]'),dc=document.getElementById('seo-desc-count');
            function upd(f,c,max){ if(f&&c){ var n=f.value.length; c.textContent=n+'/'+max+' chars'; c.style.color=n>max?'#dc2626':'#888'; } }
            if(tf)tf.addEventListener('input',function(){ upd(tf,tc,70); }); upd(tf,tc,70);
            if(df)df.addEventListener('input',function(){ upd(df,dc,160); }); upd(df,dc,160);
        })();
        </script>
    </div>
    <?php endif ?>

    <!-- Communication Log Panel -->
    <div class="enx-panel" style="grid-column:1/-1">
        <h2>📞 Telecaller Communication Status</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end;margin-bottom:14px;background:#f9f8f5;border:1px solid #eee7d9;border-radius:8px;padding:14px">
            <div class="enx-f">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase">Status</label>
                <select id="comm-status-sel" style="padding:8px;border:1px solid #ddd;border-radius:6px;font-size:13px">
                    <?php foreach($status_labels as $v=>$l): ?>
                    <option value="<?php echo esc_attr($v) ?>"<?php selected($comm_status,$v) ?> style="color:<?php echo $status_colors[$v]??'#333' ?>">
                        <?php echo esc_html($l) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="enx-f">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase">Schedule Call Date</label>
                <input type="date" id="comm-schedule-inp" value="<?php echo esc_attr($comm_schedule) ?>" min="<?php echo date('Y-m-d') ?>" style="padding:8px;border:1px solid #ddd;border-radius:6px;font-size:13px">
            </div>
            <div class="enx-f">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;opacity:0">Log</label>
                <button type="button" id="comm-save-btn" class="enx-btn enx-btn-primary" data-id="<?php echo $post_id ?>" data-nonce="<?php echo $nonce ?>" style="white-space:nowrap">
                    + Log Status
                </button>
            </div>
        </div>
        <div class="enx-f" style="margin-bottom:14px">
            <label style="font-size:11px;font-weight:700;text-transform:uppercase">Call Note / Outcome</label>
            <textarea id="comm-note-inp" rows="2" placeholder="What was discussed, candidate's response, follow-up required..." style="padding:8px;border:1px solid #ddd;border-radius:6px;font-size:13px;width:100%;box-sizing:border-box"></textarea>
        </div>

        <?php if (!empty($comm_log)): ?>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#888;margin-bottom:8px">Recent Communication Log</div>
        <div id="comm-log-list" style="max-height:280px;overflow-y:auto;border:1px solid #eee;border-radius:8px">
            <?php foreach(array_slice($comm_log,0,10) as $entry):
                $st=$entry['status']??'';
                $sc=$status_colors[$st]??'#6b7280';
            ?>
            <div style="padding:10px 14px;border-bottom:1px solid #f5f5f5;display:flex;gap:12px;align-items:flex-start">
                <span style="background:<?php echo esc_attr($sc) ?>;color:#fff;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap"><?php echo esc_html($status_labels[$st]??$st) ?></span>
                <div style="flex:1">
                    <div style="font-size:12px;font-weight:700"><?php echo esc_html($entry['user']??'') ?></div>
                    <?php if(!empty($entry['note'])): ?><div style="font-size:12px;color:#555;margin-top:2px"><?php echo esc_html($entry['note']) ?></div><?php endif ?>
                    <?php if(!empty($entry['schedule'])): ?><div style="font-size:11px;color:#3b82f6">📅 Scheduled: <?php echo esc_html($entry['schedule']) ?></div><?php endif ?>
                </div>
                <span style="font-size:11px;color:#999;white-space:nowrap"><?php echo esc_html(date('j M, g:ia', strtotime($entry['time']??'now'))) ?></span>
            </div>
            <?php endforeach ?>
        </div>
        <?php else: ?>
        <div style="color:#999;font-size:13px;font-style:italic;padding:8px 0" id="comm-log-list">No communication logged yet.</div>
        <?php endif ?>

        <script>
        document.getElementById('comm-save-btn').addEventListener('click',function(){
            var btn=this;
            btn.disabled=true; btn.textContent='Saving...';
            var data=new URLSearchParams({
                action:'enx_save_comm_log',
                post_id:btn.dataset.id,
                nonce:btn.dataset.nonce,
                comm_status:document.getElementById('comm-status-sel').value,
                comm_schedule:document.getElementById('comm-schedule-inp').value,
                comm_note:document.getElementById('comm-note-inp').value,
                comm_action:'manual'
            });
            fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data})
            .then(function(r){return r.json();}).then(function(d){
                if(d.success){
                    btn.textContent='✅ Logged';
                    setTimeout(function(){btn.disabled=false;btn.textContent='+ Log Status';},2000);
                    document.getElementById('comm-note-inp').value='';
                    location.reload();
                }else{btn.textContent='Error';btn.disabled=false;}
            }).catch(function(){btn.textContent='Error';btn.disabled=false;});
        });
        </script>
    </div>
    <?php
}
