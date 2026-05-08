<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function() {
    add_submenu_page(
        'enx-elections', 'Settings', 'Settings',
        'manage_options', 'enx-settings', 'enx_page_settings'
    );
}, 20 );

/* Enqueue settings JS as a real file so it always loads, regardless of any
   theme/plugin filtering of inline <script> blocks in the admin area. */
add_action( 'admin_enqueue_scripts', function( $hook ) {
    // Only on our Settings page (slug: enx-settings)
    if ( strpos( (string) $hook, 'enx-settings' ) === false ) return;
    wp_enqueue_script(
        'enx-settings-js',
        ENX_URL . 'admin/settings.js',
        [ 'jquery' ],
        ENX_VERSION,
        true // load in footer, after DOM
    );
    wp_localize_script( 'enx-settings-js', 'ENXSettings', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'enx_admin' ),
    ] );
} );

function enx_page_settings() {
    if ( ! current_user_can('manage_options') ) wp_die('No permission');

    if ( isset($_POST['enx_save_settings']) && wp_verify_nonce($_POST['_wpnonce'],'enx_settings') ) {
        $saved_count = 0;
        $cleared_count = 0;

        // ── Plain text fields (always save, even if empty) ──────────────────
        $plain_fields = [
            'enx_hi_site_url','enx_api_key','enx_placeholder_url',
            'enx_fb_page_id','enx_upi_id','enx_upi_name','enx_upi_qr_url',
            'enx_upi_id_2','enx_upi_name_2','enx_upi_qr_url_2',
            'enx_openai_model',
            'enx_ai_provider_1','enx_ai_provider_2','enx_ai_provider_3','enx_ai_provider_4','enx_ai_provider_5',
            'enx_poster_template_en','enx_poster_template_hi',
        ];
        foreach ( $plain_fields as $k ) {
            $v = isset($_POST[$k]) ? sanitize_text_field( wp_unslash($_POST[$k]) ) : '';
            update_option( $k, $v );
            if ( $v !== '' ) $saved_count++;
        }

        // ── API key / token fields ──────────────────────────────────────────
        // Save when a value is submitted. If the field exists but is empty,
        // CLEAR the saved option (so users can remove a key by clearing the
        // input and saving). Only skip when the field is entirely missing.
        $api_key_fields = ['enx_openai_key','enx_ai_key_1','enx_ai_key_2','enx_ai_key_3','enx_ai_key_4','enx_ai_key_5','enx_fb_page_token'];
        foreach ( $api_key_fields as $k ) {
            if ( ! array_key_exists( $k, $_POST ) ) continue; // field not submitted at all
            $v = sanitize_text_field( wp_unslash($_POST[$k]) );
            update_option( $k, $v );
            if ( $v !== '' ) $saved_count++; else $cleared_count++;
        }

        // ── Poster per-position templates ───────────────────────────────────
        $positions = enx_all_position_slugs();
        foreach ( $positions as $pos ) {
            update_option( 'enx_poster_tpl_en_'.$pos, esc_url_raw( wp_unslash($_POST['enx_poster_tpl_en_'.$pos] ?? '') ) );
            update_option( 'enx_poster_tpl_hi_'.$pos, esc_url_raw( wp_unslash($_POST['enx_poster_tpl_hi_'.$pos] ?? '') ) );
        }

        // ── Poster font / size settings ─────────────────────────────────────
        $font_keys = ['enx_poster_font_name','enx_poster_font_contest','enx_poster_font_area','enx_poster_font_loc',
                      'enx_poster_size_name','enx_poster_size_contest','enx_poster_size_area','enx_poster_size_loc'];
        foreach ( $font_keys as $k ) {
            update_option( $k, sanitize_text_field( wp_unslash($_POST[$k] ?? '') ) );
        }

        // ── Permissions (checkboxes) ────────────────────────────────────────
        $perm_keys = ['enx_perm_editor_edit','enx_perm_editor_delete','enx_perm_contributor_edit','enx_perm_contributor_delete'];
        foreach ( $perm_keys as $k ) {
            update_option( $k, isset($_POST[$k]) ? '1' : '0' );
        }

        echo '<div class="notice notice-success is-dismissible"><p><strong>Settings saved.</strong> '
            . esc_html( $saved_count ) . ' field(s) updated'
            . ( $cleared_count > 0 ? ', ' . esc_html( $cleared_count ) . ' cleared' : '' )
            . '.</p></div>';
    }

    $positions  = enx_all_position_slugs();
    $pos_labels = enx_contest_labels();
    $fonts = [
        'inherit'                       => 'Default (Theme Font)',
        // Latin fonts
        'Arial, sans-serif'             => 'Arial',
        'Georgia, serif'                => 'Georgia',
        'Verdana, sans-serif'           => 'Verdana',
        'Tahoma, sans-serif'            => 'Tahoma',
        'Times New Roman, serif'        => 'Times New Roman',
        // Google Fonts - Latin
        "'Roboto', sans-serif"          => 'Roboto',
        "'Poppins', sans-serif"         => 'Poppins',
        // Hindi / Devanagari Google Fonts
        "'Noto Sans Devanagari', sans-serif"    => 'Noto Sans Devanagari (Hindi ✓)',
        "'Tiro Devanagari Hindi', serif"        => 'Tiro Devanagari Hindi (Hindi ✓)',
        "'Baloo 2', cursive"                    => 'Baloo 2 (Hindi ✓)',
        "'Hind', sans-serif"                    => 'Hind (Hindi ✓)',
        "'Mukta', sans-serif"                   => 'Mukta (Hindi ✓)',
        "'Rajdhani', sans-serif"                => 'Rajdhani (Hindi ✓)',
        "'Laila', serif"                        => 'Laila (Hindi ✓)',
        "'Yatra One', cursive"                  => 'Yatra One (Hindi - Display ✓)',
        "'Rozha One', serif"                    => 'Rozha One (Hindi - Bold ✓)',
        "'Karma', serif"                        => 'Karma (Hindi ✓)',
        "'Eczar', serif"                        => 'Eczar (Hindi ✓)',
        "'Shobhika', serif"                     => 'Shobhika (Hindi - Traditional ✓)',
    ];
    ?>
    <div class="wrap" style="max-width:900px">
        <h1>⚙️ ENOXX Elections Settings</h1>
        <form method="post">
            <?php wp_nonce_field('enx_settings') ?>

            <!-- TAB NAVIGATION -->
            <div id="enx-settings-tabs" style="display:flex;gap:0;margin-bottom:24px;border-bottom:2px solid #e0e0e0">
                <?php foreach(['sync'=>'🔄 Sync','ai'=>'🤖 AI Bio','poster'=>'🖼 Poster Templates','fonts'=>'🔤 Poster Fonts','permissions'=>'🔒 Permissions'] as $tid=>$tlabel):
                    $is_active_tab = ($tid === 'sync');
                    $bb = $is_active_tab ? '#f59e0b' : 'transparent';
                    $fc = $is_active_tab ? '#d97706' : '#333';
                    ?>
                <button type="button" class="enx-tab-btn" data-tab="<?php echo esc_attr($tid) ?>" style="padding:10px 18px;border:none;border-bottom:3px solid <?php echo $bb ?>;background:transparent;color:<?php echo $fc ?>;font-weight:700;font-size:13px;cursor:pointer"><?php echo $tlabel ?></button>
                <?php endforeach ?>
            </div>

            <?php $sp = function($k,$d='') { return esc_attr(get_option($k,$d)); }; ?>

            <!-- SYNC TAB -->
            <div class="enx-tab-panel" id="tab-sync">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div style="display:flex;flex-direction:column;gap:5px">
                        <label style="font-size:12px;font-weight:700;text-transform:uppercase">Hindi Site URL</label>
                        <input type="url" name="enx_hi_site_url" value="<?php echo $sp('enx_hi_site_url','https://enoxxnews.in') ?>" style="padding:8px;border:1px solid #ddd;border-radius:6px">
                        <small style="color:#888">For demo: https://demo.enoxxnews.in</small>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:5px;grid-column:1/-1;margin-top:4px">
                        <label style="font-size:12px;font-weight:700;text-transform:uppercase">Custom Placeholder Photo URL <small style="text-transform:none;font-weight:400">(shown when no photo / basic tier)</small></label>
                        <input type="url" name="enx_placeholder_url" value="<?php echo $sp('enx_placeholder_url') ?>" placeholder="https://yourdomain.com/wp-content/uploads/placeholder.png" style="padding:8px;border:1px solid #ddd;border-radius:6px">
                        <small style="color:#888">Leave blank to use a built-in SVG placeholder. Recommended: upload a 300×375px PNG with your brand.</small>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:5px">
                        <label style="font-size:12px;font-weight:700;text-transform:uppercase">Sync API Key</label>
                        <input type="text" name="enx_api_key" value="<?php echo $sp('enx_api_key','enoxx-secret-key') ?>" style="padding:8px;border:1px solid #ddd;border-radius:6px">
                    </div>
                </div>
            </div>

            <!-- FACEBOOK SECTION (inside Sync tab) -->
            <div style="border-top:2px solid #1877f2;padding-top:20px;margin-top:20px">
                <h3 style="margin:0 0 14px;color:#1877f2;font-size:14px">📘 Facebook Page Integration (for Poster Studio)</h3>
                <div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:8px;padding:12px;margin-bottom:14px;font-size:12px">
                    <strong>How to get your Page Token:</strong>
                    1. Go to <a href="https://developers.facebook.com/tools/explorer" target="_blank">Graph API Explorer</a>
                    → Get User Token with <code>pages_manage_posts</code> + <code>pages_read_engagement</code> permissions
                    → Select your page → Get Page Access Token → paste below. The Page ID is visible in your Facebook Page settings.
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <div style="display:flex;flex-direction:column;gap:5px">
                        <label style="font-size:12px;font-weight:700;text-transform:uppercase">Facebook Page ID</label>
                        <input type="text" name="enx_fb_page_id" value="<?php echo $sp('enx_fb_page_id') ?>" placeholder="123456789012345" style="padding:8px;border:1px solid #ddd;border-radius:6px">
                    </div>
                    <div style="display:flex;flex-direction:column;gap:5px">
                        <label style="font-size:12px;font-weight:700;text-transform:uppercase">Facebook Page Access Token</label>
                        <input type="text" name="enx_fb_page_token" value="<?php echo $sp('enx_fb_page_token') ?>" placeholder="EAABx..." autocomplete="off" style="padding:8px;border:1px solid #ddd;border-radius:6px;font-family:monospace">
                    </div>
                </div>
            </div>

            <!-- AI TAB -->
            <div class="enx-tab-panel" id="tab-ai" style="display:none">
                <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:14px;margin-bottom:20px;font-size:13px">
                    <strong>How AI Bio works:</strong> Telecallers fill a "Notes" field. In the admin form, you click "Generate Bio with AI" — it sends all candidate data + notes to OpenAI and generates an EN and HI bio automatically.
                </div>
                <div style="background:#dbeafe;border:1px solid #93c5fd;border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px">
                    💡 Add up to 4 API keys. The system tries each in order, automatically falling back if one hits its rate limit or fails.
                    Free keys available: <a href="https://platform.openai.com" target="_blank">OpenAI</a> (pay-as-you-go) |
                    <a href="https://console.groq.com" target="_blank">Groq</a> (free tier) |
                    <a href="https://makersuite.google.com" target="_blank">Google Gemini</a> (free tier) |
                    <a href="https://openrouter.ai" target="_blank">Mistral AI</a> (free tier) | <a href="https://console.mistral.ai" target="_blank">OpenRouter</a> (free models)
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px">
                <?php
                $key_configs = [
                    ['key'=>'enx_ai_key_1','provider'=>'enx_ai_provider_1','label'=>'API Key #1 (Primary)','default_provider'=>'openai'],
                    ['key'=>'enx_ai_key_2','provider'=>'enx_ai_provider_2','label'=>'API Key #2 (Fallback)','default_provider'=>'groq'],
                    ['key'=>'enx_ai_key_3','provider'=>'enx_ai_provider_3','label'=>'API Key #3 (Fallback)','default_provider'=>'mistral'],
                    ['key'=>'enx_ai_key_4','provider'=>'enx_ai_provider_4','label'=>'API Key #4 (Fallback)','default_provider'=>'gemini'],
                    ['key'=>'enx_ai_key_5','provider'=>'enx_ai_provider_5','label'=>'API Key #5 (Fallback)','default_provider'=>'openrouter'],
                ];
                $providers = [
                    'openai'     => ['label'=>'OpenAI (GPT-4o-mini)','url'=>'https://api.openai.com/v1/chat/completions','bearer'=>true],
                    'groq'       => ['label'=>'Groq (free, fast)','url'=>'https://api.groq.com/openai/v1/chat/completions','bearer'=>true],
                    'mistral'    => ['label'=>'Mistral AI (free tier)','url'=>'https://api.mistral.ai/v1/chat/completions','bearer'=>true],
                    'gemini'     => ['label'=>'Google Gemini (free)','url'=>'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent','bearer'=>false],
                    'openrouter' => ['label'=>'OpenRouter (free models)','url'=>'https://openrouter.ai/api/v1/chat/completions','bearer'=>true],
                ];
                foreach($key_configs as $kc):
                ?>
                <div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;padding:12px">
                    <label style="font-size:11px;font-weight:700;text-transform:uppercase;color:#555;display:block;margin-bottom:6px"><?php echo $kc['label'] ?></label>
                    <select name="<?php echo $kc['provider'] ?>" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:6px;margin-bottom:6px;font-size:12px">
                        <option value=""><?php echo $kc['label']==='API Key #1 (Primary)'?'— Disabled —':'— Skip —' ?></option>
                        <?php foreach($providers as $pv=>$pl): ?>
                        <option value="<?php echo $pv ?>"<?php selected($sp($kc['provider'],$kc['default_provider']),$pv) ?>><?php echo $pl['label'] ?></option>
                        <?php endforeach ?>
                    </select>
                    <input type="text" name="<?php echo $kc['key'] ?>" value="<?php echo $sp($kc['key']) ?>" placeholder="Paste API key..." autocomplete="off" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:6px;font-size:12px;box-sizing:border-box;font-family:monospace">
                </div>
                <?php endforeach ?>
                </div>
                <div style="display:flex;align-items:center;gap:12px">
                    <label style="font-size:12px;font-weight:700;text-transform:uppercase">Model (for OpenAI/Groq)</label>
                    <select name="enx_openai_model" style="padding:8px;border:1px solid #ddd;border-radius:6px">
                        <?php foreach(['gpt-4o-mini'=>'GPT-4o Mini (Fast & Cheap)','gpt-4o'=>'GPT-4o','llama-3.1-70b-versatile'=>'Groq: Llama-3.1 70B','mixtral-8x7b-32768'=>'Groq: Mixtral 8x7B','gpt-3.5-turbo'=>'GPT-3.5 Turbo (Cheapest)'] as $v=>$l): ?>
                        <option value="<?php echo $v ?>"<?php selected($sp('enx_openai_model','gpt-4o-mini'),$v) ?>><?php echo $l ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div style="margin-top:14px;padding:12px;background:#f8f8f8;border-radius:8px;font-size:13px">
                    <strong>Test:</strong>
                    <button type="button" id="enx-test-ai" class="button" style="margin-left:8px">Test AI Connection</button>
                    <span id="enx-ai-test-result" style="margin-left:10px;font-size:13px"></span>
                </div>
            </div>

            <!-- POSTER TEMPLATES TAB -->
            <div class="enx-tab-panel" id="tab-poster" style="display:none">
                <p style="color:#555;font-size:13px;margin-top:0">Upload blank poster template images and paste their URLs. Per-position templates override the base template.</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
                    <div style="display:flex;flex-direction:column;gap:5px">
                        <label style="font-size:12px;font-weight:700;text-transform:uppercase">Default Template — English Site</label>
                        <input type="url" name="enx_poster_template_en" value="<?php echo $sp('enx_poster_template_en') ?>" placeholder="https://..." style="padding:8px;border:1px solid #ddd;border-radius:6px">
                    </div>
                    <div style="display:flex;flex-direction:column;gap:5px">
                        <label style="font-size:12px;font-weight:700;text-transform:uppercase">Default Template — Hindi Site</label>
                        <input type="url" name="enx_poster_template_hi" value="<?php echo $sp('enx_poster_template_hi') ?>" placeholder="https://..." style="padding:8px;border:1px solid #ddd;border-radius:6px">
                    </div>
                </div>

                <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;border-bottom:1px solid #eee;padding-bottom:8px">Per-Position Templates (override default)</h3>
                <?php foreach($positions as $pos):
                    $label = $pos_labels[$pos]['en'] ?? enx_labelize($pos);
                ?>
                <div style="display:grid;grid-template-columns:140px 1fr 1fr;gap:10px;align-items:center;margin-bottom:10px;padding:10px;background:#f9f9f9;border-radius:8px">
                    <strong style="font-size:13px"><?php echo esc_html($label) ?></strong>
                    <input type="url" name="enx_poster_tpl_en_<?php echo $pos ?>" value="<?php echo esc_attr(get_option('enx_poster_tpl_en_'.$pos)) ?>" placeholder="EN template URL (optional)" style="padding:7px;border:1px solid #ddd;border-radius:6px;font-size:12px">
                    <input type="url" name="enx_poster_tpl_hi_<?php echo $pos ?>" value="<?php echo esc_attr(get_option('enx_poster_tpl_hi_'.$pos)) ?>" placeholder="HI template URL (optional)" style="padding:7px;border:1px solid #ddd;border-radius:6px;font-size:12px">
                </div>
                <?php endforeach ?>
            </div>

            <!-- POSTER FONTS TAB -->
            <div class="enx-tab-panel" id="tab-fonts" style="display:none">
                <p style="color:#555;font-size:13px;margin-top:0">Customize fonts and sizes for each poster text element. These apply globally to all posters.</p>
                <table style="width:100%;border-collapse:collapse">
                    <tr style="background:#f4f4f4">
                        <th style="padding:10px;text-align:left;font-size:12px;text-transform:uppercase">Element</th>
                        <th style="padding:10px;text-align:left;font-size:12px;text-transform:uppercase">Font Family</th>
                        <th style="padding:10px;text-align:left;font-size:12px;text-transform:uppercase">Font Size (px)</th>
                    </tr>
                    <?php foreach([
                        ['enx_poster_font_name','enx_poster_size_name','Candidate Name','80','90'],
                        ['enx_poster_font_contest','enx_poster_size_contest','Contest / Position','34','90'],
                        ['enx_poster_font_area','enx_poster_size_area','Panchayat / Area','36','90'],
                        ['enx_poster_font_loc','enx_poster_size_loc','Location Line','26','90'],
                    ] as [$fk,$sk,$label,$def_size,$max_size]): ?>
                    <tr style="border-bottom:1px solid #eee">
                        <td style="padding:10px;font-weight:600;font-size:13px"><?php echo $label ?></td>
                        <td style="padding:10px">
                            <select name="<?php echo $fk ?>" style="padding:7px;border:1px solid #ddd;border-radius:6px;font-size:13px;width:100%">
                                <?php foreach($fonts as $fv=>$fl): ?>
                                <option value="<?php echo esc_attr($fv) ?>"<?php selected(get_option($fk,'inherit'),$fv) ?>><?php echo esc_html($fl) ?></option>
                                <?php endforeach ?>
                            </select>
                        </td>
                        <td style="padding:10px">
                            <input type="number" name="<?php echo $sk ?>" value="<?php echo esc_attr(get_option($sk,$def_size)) ?>" min="12" max="<?php echo $max_size ?>" style="padding:7px;border:1px solid #ddd;border-radius:6px;font-size:13px;width:80px">
                        </td>
                    </tr>
                    <?php endforeach ?>
                </table>
                <div style="margin-top:16px;padding:12px;background:#f0f9ff;border-radius:8px;font-size:13px;color:#0369a1">
                    💡 Changes take effect immediately for all new poster views. Font sizes auto-shrink if text is too long — these are maximum sizes.
                </div>
            </div>

            <!-- PERMISSIONS TAB -->
            <div class="enx-tab-panel" id="tab-permissions" style="display:none">
                <p style="color:#555;font-size:13px;margin-top:0">Control what non-admin roles can do with candidate profiles.</p>
                <table style="width:100%;border-collapse:collapse">
                    <tr style="background:#f4f4f4">
                        <th style="padding:10px;text-align:left;font-size:12px;text-transform:uppercase">Role</th>
                        <th style="padding:10px;text-align:center;font-size:12px;text-transform:uppercase">Can Edit</th>
                        <th style="padding:10px;text-align:center;font-size:12px;text-transform:uppercase">Can Delete</th>
                        <th style="padding:10px;text-align:left;font-size:12px;text-transform:uppercase">Notes</th>
                    </tr>
                    <tr style="background:#d1fae5">
                        <td style="padding:10px;font-weight:700">Administrator</td>
                        <td style="padding:10px;text-align:center">✅ Always</td>
                        <td style="padding:10px;text-align:center">✅ Always</td>
                        <td style="padding:10px;font-size:12px;color:#555">Full control, can publish & sync</td>
                    </tr>
                    <tr style="border-bottom:1px solid #eee">
                        <td style="padding:10px;font-weight:700">Editor</td>
                        <td style="padding:10px;text-align:center"><label><input type="checkbox" name="enx_perm_editor_edit" <?php checked(get_option('enx_perm_editor_edit','1'),'1') ?>></label></td>
                        <td style="padding:10px;text-align:center"><label><input type="checkbox" name="enx_perm_editor_delete" <?php checked(get_option('enx_perm_editor_delete','0'),'1') ?>></label></td>
                        <td style="padding:10px;font-size:12px;color:#555">Can see all candidates but cannot publish/sync (admin only)</td>
                    </tr>
                    <tr>
                        <td style="padding:10px;font-weight:700">Contributor (Telecaller)</td>
                        <td style="padding:10px;text-align:center"><label><input type="checkbox" name="enx_perm_contributor_edit" <?php checked(get_option('enx_perm_contributor_edit','1'),'1') ?>></label></td>
                        <td style="padding:10px;text-align:center"><label><input type="checkbox" name="enx_perm_contributor_delete" <?php checked(get_option('enx_perm_contributor_delete','0'),'1') ?>></label></td>
                        <td style="padding:10px;font-size:12px;color:#555">Can only edit/delete their own pending submissions. Cannot publish or sync.</td>
                    </tr>
                </table>
            </div>

            <div style="margin-top:24px">
                <button type="submit" name="enx_save_settings" class="button button-primary button-large">Save All Settings</button>
            </div>
        </form>
    </div>

    <?php
}

// AJAX: Test AI connection
add_action('wp_ajax_enx_test_ai', function() {
    if (!wp_verify_nonce($_POST['nonce']??'','enx_admin')) wp_send_json_error('nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('permission');
    $key  = sanitize_text_field($_POST['key']??'');
    $prov = sanitize_key($_POST['provider']??'openai');
    if (!$key) wp_send_json_error('No API key provided');

    $endpoints = [
        'openai'     => 'https://api.openai.com/v1/chat/completions',
        'groq'       => 'https://api.groq.com/openai/v1/chat/completions',
        'mistral' => 'https://api.mistral.ai/v1/chat/completions',
        'openrouter' => 'https://openrouter.ai/api/v1/chat/completions',
    ];
    $models = [
        'openai'     => 'gpt-4o-mini',
        'groq'       => 'llama-3.1-8b-instant',
        'mistral' => 'mistral-small-latest',
        'openrouter' => 'openai/gpt-3.5-turbo',
    ];

    if ($prov === 'gemini') {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key='.$key;
        $r = wp_remote_post($url,['headers'=>['Content-Type'=>'application/json'],'body'=>wp_json_encode(['contents'=>[['parts'=>[['text'=>'Hi']]]]]),'timeout'=>15]);
        if (is_wp_error($r)) { wp_send_json_error($r->get_error_message()); return; }
        $code = wp_remote_retrieve_response_code($r);
        if ($code===200) wp_send_json_success('Google Gemini key is valid ✓');
        else wp_send_json_error('Gemini HTTP '.$code);
        return;
    }

    $url  = $endpoints[$prov] ?? $endpoints['openai'];
    $model= $models[$prov] ?? 'gpt-4o-mini';
    $hdrs = ['Authorization'=>'Bearer '.$key,'Content-Type'=>'application/json'];
    if ($prov==='openrouter') $hdrs['HTTP-Referer']='https://enoxxnews.com';
    $response = wp_remote_post($url,[
        'headers' => $hdrs,
        'body'    => wp_json_encode(['model'=>$model,'max_tokens'=>5,'messages'=>[['role'=>'user','content'=>'Hi']]]),
        'timeout' => 15,
    ]);
    if (is_wp_error($response)) { wp_send_json_error($response->get_error_message()); return; }
    $code = wp_remote_retrieve_response_code($response);
    if ($code === 200) wp_send_json_success(strtoupper($prov).' key is valid ✓');
    elseif ($code === 401) wp_send_json_error('Invalid API key (401 Unauthorized)');
    elseif ($code === 429) wp_send_json_error('Rate limited (429) — key is valid but quota exceeded');
    else wp_send_json_error('HTTP '.$code.' from '.$prov);
});

function enx_all_position_slugs() {
    return ['pradhan','up-pradhan','ward-member','zila-parishad','panchayat-samiti',
            'councillor','mayor','deputy-mayor','president','vice-president','mla-candidate'];
}

// AJAX: Save poster settings from the live poster editor
add_action('wp_ajax_enx_save_poster_settings', function(){
    if ( ! wp_verify_nonce($_POST['nonce']??'','enx_admin') ) wp_send_json_error('nonce');
    if ( ! current_user_can('administrator') ) wp_send_json_error('permission');
    $keys = ['enx_poster_size_name','enx_poster_size_contest','enx_poster_size_area','enx_poster_size_loc',
             'enx_poster_lh_name','enx_poster_lh_contest','enx_poster_lh_area',
             'enx_poster_pos_name_top','enx_poster_pos_name_left','enx_poster_pos_name_width',
             'enx_poster_pos_contest_top','enx_poster_pos_contest_left',
             'enx_poster_pos_area_top','enx_poster_pos_area_left',
             'enx_poster_pos_loc_top','enx_poster_pos_loc_left',
             'enx_poster_font_name','enx_poster_font_contest','enx_poster_font_area','enx_poster_font_loc'];
    foreach ( $keys as $k ) {
        if ( isset($_POST[$k]) ) update_option($k, sanitize_text_field($_POST[$k]));
    }
    wp_send_json_success('Saved');
});

