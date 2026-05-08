<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Menu ─────────────────────────────────────────────────────────────── */
add_action( 'admin_menu', function() {
    add_submenu_page(
        'enx-elections', '🖼️ Poster Studio', '🖼️ Poster Studio',
        'manage_options', 'enx-poster-studio', 'enx_page_poster_studio'
    );
}, 22 );

/* ── AJAX: get poster image data URL for admin ──────────────────────────── */
add_action( 'wp_ajax_enx_poster_preview', function() {
    if ( ! wp_verify_nonce($_POST['nonce']??'','enx_admin') ) wp_send_json_error('nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('permission');
    $id = absint($_POST['candidate_id']??0);
    if ( ! $id ) wp_send_json_error('No candidate');
    $poster_url = home_url('/candidate-poster/?candidate_id='.$id);
    wp_send_json_success(['poster_url'=>$poster_url]);
} );

/* ── AJAX: publish to Facebook ──────────────────────────────────────────── */
add_action( 'wp_ajax_enx_fb_publish', function() {
    if ( ! wp_verify_nonce($_POST['nonce']??'','enx_admin') ) wp_send_json_error('nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('permission');

    $page_id    = get_option('enx_fb_page_id');
    $page_token = get_option('enx_fb_page_token');
    if ( ! $page_id || ! $page_token ) wp_send_json_error('Facebook Page ID and Token not configured in Settings → Sync tab.');

    $image_url  = sanitize_url( $_POST['image_url']??'' );
    $caption    = sanitize_textarea_field( $_POST['caption']??'' );
    $link       = sanitize_url( $_POST['profile_url']??'' );

    if ( ! $image_url ) wp_send_json_error('No image URL provided.');

    // Upload photo to Facebook page
    $fb_upload = wp_remote_post( "https://graph.facebook.com/v19.0/{$page_id}/photos", [
        'timeout' => 30,
        'body'    => [
            'url'          => $image_url,
            'caption'      => $caption . ( $link ? "\n\n🔗 " . $link : '' ),
            'access_token' => $page_token,
            'published'    => 'true',
        ],
    ] );

    if ( is_wp_error($fb_upload) ) wp_send_json_error( $fb_upload->get_error_message() );
    $resp_code = wp_remote_retrieve_response_code($fb_upload);
    $resp_body = json_decode( wp_remote_retrieve_body($fb_upload), true );

    if ( $resp_code !== 200 ) {
        $err = $resp_body['error']['message'] ?? 'Facebook API error '.$resp_code;
        wp_send_json_error( $err );
    }
    wp_send_json_success([
        'post_id'  => $resp_body['post_id'] ?? $resp_body['id'] ?? '',
        'message'  => 'Posted successfully to Facebook!',
    ]);
} );

/* ── AJAX: Search candidates ────────────────────────────────────────────── */
add_action( 'wp_ajax_enx_poster_search', function() {
    if ( ! wp_verify_nonce($_POST['nonce']??'','enx_admin') ) wp_send_json_error('nonce');

    $mq = ['relation'=>'AND'];
    $d  = sanitize_text_field($_POST['district']??'');
    $b  = sanitize_text_field($_POST['block']??'');
    $p  = sanitize_text_field($_POST['panchayat']??'');
    $s  = sanitize_text_field($_POST['search']??'');
    $tier = sanitize_text_field($_POST['tier']??'premium');

    if ( $d ) $mq[] = ['key'=>'district_slug','value'=>$d,'compare'=>'='];
    if ( $b ) $mq[] = ['key'=>'block_slug','value'=>$b,'compare'=>'='];
    if ( $p ) $mq[] = ['key'=>'panchayat_slug','value'=>$p,'compare'=>'='];
    if ( $tier ) $mq[] = ['key'=>'profile_tier_text','value'=>$tier,'compare'=>'='];

    $args = [
        'post_type'      => 'candidate',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'meta_query'     => $mq,
    ];
    if ( $s ) $args['s'] = $s;

    $posts = get_posts($args);
    $results = [];
    foreach ( $posts as $post ) {
        $results[] = [
            'id'       => $post->ID,
            'name'     => get_post_meta($post->ID,'candidate_name_text',true) ?: $post->post_title,
            'contest'  => enx_get_contest_label_for_post($post->ID,'en'),
            'district' => get_post_meta($post->ID,'district_text',true),
            'block'    => get_post_meta($post->ID,'block_text',true),
            'panchayat'=> get_post_meta($post->ID,'panchayat_text',true),
            'photo'    => enx_photo_url_raw($post->ID,'thumbnail'),
            'profile'  => get_permalink($post->ID),
        ];
    }
    wp_send_json_success($results);
} );

/* ── Admin page ─────────────────────────────────────────────────────────── */
function enx_page_poster_studio() {
    $fb_connected = get_option('enx_fb_page_token') && get_option('enx_fb_page_id');
    $loc_data     = enx_get_location_data();
    $districts    = array_filter(array_keys($loc_data), function($k){ return $k !== 'zp_wards'; });
    $nonce        = wp_create_nonce('enx_admin');
    ?>
    <div class="wrap" style="max-width:1100px">
        <h1 style="display:flex;align-items:center;gap:10px">🖼️ Poster Studio
            <?php if($fb_connected): ?>
            <span style="font-size:12px;background:#1877f2;color:#fff;padding:3px 10px;border-radius:999px;font-weight:600">✅ Facebook Connected</span>
            <?php else: ?>
            <a href="<?php echo admin_url('admin.php?page=enx-settings') ?>" style="font-size:12px;background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:999px;font-weight:600;text-decoration:none">⚠️ Facebook not connected — click to setup</a>
            <?php endif ?>
        </h1>

        <div style="display:grid;grid-template-columns:320px 1fr;gap:24px;align-items:start">

        <!-- LEFT: Candidate selector -->
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:20px">
            <h2 style="margin:0 0 14px;font-size:15px;border-bottom:2px solid #f59e0b;padding-bottom:8px">🔍 Find Candidate</h2>

            <div style="display:flex;flex-direction:column;gap:10px">
                <input type="text" id="ps-search" placeholder="Search by name..." style="padding:9px;border:1px solid #ddd;border-radius:8px;font-size:13px">

                <select id="ps-district" style="padding:9px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                    <option value="">All Districts</option>
                    <?php foreach($districts as $dk): ?>
                    <option value="<?php echo esc_attr($dk) ?>"><?php echo esc_html(enx_district_label($dk)?:enx_labelize($dk)) ?></option>
                    <?php endforeach ?>
                </select>

                <select id="ps-block" style="padding:9px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                    <option value="">All Blocks</option>
                </select>

                <select id="ps-panchayat" style="padding:9px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                    <option value="">All Panchayats</option>
                </select>

                <select id="ps-tier" style="padding:9px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                    <option value="premium">Premium Only (with photo)</option>
                    <option value="">All Tiers</option>
                </select>

                <button id="ps-search-btn" class="button button-primary">🔍 Search</button>
            </div>

            <div id="ps-results" style="margin-top:14px;max-height:460px;overflow-y:auto"></div>
        </div>

        <!-- RIGHT: Poster preview + actions -->
        <div>
            <!-- Poster preview -->
            <div id="ps-preview-wrap" style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:20px;margin-bottom:16px;min-height:200px;display:flex;align-items:center;justify-content:center">
                <div id="ps-placeholder" style="text-align:center;color:#bbb">
                    <div style="font-size:48px">🖼️</div>
                    <p>Select a candidate to preview their poster</p>
                </div>
                <!-- Poster preview: scrollable container, poster scales independently -->
                <div id="ps-frame-wrap" style="display:none">
                    <!-- Zoom controls above poster only -->
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;padding:8px 12px;background:#f0f0f0;border-radius:8px">
                        <span style="font-size:12px;font-weight:700;white-space:nowrap">Poster Zoom:</span>
                        <input type="range" id="ps-zoom-slider" min="25" max="100" value="50" style="flex:1">
                        <span id="ps-zoom-label" style="font-size:12px;font-weight:700;min-width:36px;text-align:right">50%</span>
                        <a id="ps-open-new-tab" href="#" target="_blank" class="button" style="font-size:12px">↗ Full Size</a>
                    </div>
                    <!-- Scrollable poster container — controls inside iframe scroll separately -->
                    <div id="ps-scroll-wrap" style="overflow:auto;border:1px solid #e0e0e0;border-radius:10px;background:#f5f5f5;max-height:620px">
                        <div id="ps-scale-wrap" style="transform-origin:top left;display:inline-block">
                            <iframe id="ps-poster-frame"
                                style="width:1080px;height:1080px;border:none;display:block"
                                src="about:blank"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Post caption -->
            <div id="ps-actions-wrap" style="display:none">
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:20px">
                    <h2 style="margin:0 0 14px;font-size:15px;border-bottom:2px solid #1877f2;padding-bottom:8px">📤 Facebook Post</h2>

                    <div style="margin-bottom:12px">
                        <label style="font-size:12px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:5px">Post Caption</label>
                        <textarea id="ps-caption" rows="4" style="width:100%;padding:9px;border:1px solid #ddd;border-radius:8px;font-size:13px;resize:vertical;box-sizing:border-box" placeholder="Write your post caption..."></textarea>
                    </div>

                    <div style="margin-bottom:14px">
                        <label style="font-size:12px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:5px">Poster Image URL <small style="font-weight:400;text-transform:none;color:#888">(auto-filled from poster page)</small></label>
                        <div style="display:flex;gap:8px">
                            <input type="url" id="ps-image-url" style="flex:1;padding:9px;border:1px solid #ddd;border-radius:8px;font-size:13px" placeholder="https://...">
                            <button type="button" id="ps-copy-poster-url" class="button">📋 Copy</button>
                        </div>
                        <small style="color:#888;font-size:11px">Tip: Share the poster URL directly, or paste the image URL after downloading the poster.</small>
                    </div>

                    <div style="display:flex;gap:10px">
                        <button id="ps-fb-publish" class="button button-primary" style="background:#1877f2;border-color:#1877f2;flex:1;padding:10px;font-size:14px">
                            📘 Publish to Facebook
                        </button>
                        <a id="ps-view-poster" href="#" target="_blank" class="button" style="padding:10px;font-size:14px">
                            👁️ Open Poster
                        </a>
                        <a id="ps-edit-candidate" href="#" class="button" style="padding:10px;font-size:14px">
                            ✏️ Edit
                        </a>
                    </div>

                    <div id="ps-fb-result" style="margin-top:10px;display:none;padding:10px;border-radius:8px;font-size:13px"></div>
                </div>
            </div>
        </div>
        </div>
    </div>

    <script>
    (function(){
        var nonce     = '<?php echo $nonce ?>';
        var locData   = <?php echo wp_json_encode($loc_data) ?>;
        var ajax      = '<?php echo admin_url('admin-ajax.php') ?>';
        var selected  = null;

        var dSel = document.getElementById('ps-district');
        var bSel = document.getElementById('ps-block');
        var pSel = document.getElementById('ps-panchayat');

        function resetSel(sel, label) {
            sel.innerHTML = '<option value="">'+label+'</option>';
        }

        dSel.addEventListener('change', function(){
            resetSel(bSel,'All Blocks'); resetSel(pSel,'All Panchayats');
            var d = this.value;
            if(!d || !locData[d]) return;
            Object.keys(locData[d].blocks||{}).sort(function(a,b){
                return ((locData[d].blocks[a]||{}).label_en||a).localeCompare((locData[d].blocks[b]||{}).label_en||b);
            }).forEach(function(bs){
                var bl = locData[d].blocks[bs];
                bSel.appendChild(new Option((bl.label_en||bs), bs));
            });
        });

        bSel.addEventListener('change', function(){
            resetSel(pSel,'All Panchayats');
            var d=dSel.value, b=this.value;
            if(!d||!b||!locData[d]) return;
            var blk = locData[d].blocks[b]||{};
            Object.keys(blk.panchayats||{}).sort(function(a,b2){
                return ((blk.panchayats[a]||{}).label_en||a).localeCompare((blk.panchayats[b2]||{}).label_en||b2);
            }).forEach(function(ps){
                var p=blk.panchayats[ps];
                pSel.appendChild(new Option((typeof p==='object'?p.label_en:p)||ps, ps));
            });
        });

        document.getElementById('ps-search-btn').addEventListener('click', doSearch);
        document.getElementById('ps-search').addEventListener('keydown', function(e){ if(e.key==='Enter') doSearch(); });

        function doSearch() {
            var results = document.getElementById('ps-results');
            results.innerHTML = '<div style="text-align:center;padding:20px;color:#888">🔍 Searching...</div>';
            var fd = new FormData();
            fd.append('action','enx_poster_search');
            fd.append('nonce',nonce);
            fd.append('district',dSel.value);
            fd.append('block',bSel.value);
            fd.append('panchayat',pSel.value);
            fd.append('search',document.getElementById('ps-search').value);
            fd.append('tier',document.getElementById('ps-tier').value);
            fetch(ajax,{method:'POST',body:fd}).then(r=>r.json()).then(function(d){
                if(!d.success){results.innerHTML='<p style="color:red">'+d.data+'</p>';return;}
                if(!d.data.length){results.innerHTML='<p style="color:#888;text-align:center;padding:20px">No candidates found.</p>';return;}
                results.innerHTML = d.data.map(function(c){
                    return '<div class="ps-result-item" data-id="'+c.id+'" data-name="'+c.name+'" data-profile="'+c.profile+'" data-contest="'+c.contest+'" data-district="'+c.district+'" data-block="'+c.block+'" data-pan="'+c.panchayat+'" style="display:flex;align-items:center;gap:10px;padding:10px;border-bottom:1px solid #f0f0f0;cursor:pointer;border-radius:8px">'
                        +'<img src="'+(c.photo||'')+'" style="width:44px;height:44px;border-radius:50%;object-fit:cover;background:#eee">'
                        +'<div><div style="font-weight:600;font-size:13px">'+c.name+'</div>'
                        +'<div style="font-size:11px;color:#888">'+c.contest+' &bull; '+c.district+'</div></div></div>';
                }).join('');
                document.querySelectorAll('.ps-result-item').forEach(function(el){
                    el.addEventListener('mouseenter',function(){ this.style.background='#f0f9ff'; });
                    el.addEventListener('mouseleave',function(){ this.style.background=''; });
                    el.addEventListener('click',function(){ selectCandidate(this); });
                });
            });
        }

        function selectCandidate(el) {
            document.querySelectorAll('.ps-result-item').forEach(function(e){ e.style.background=''; e.style.borderLeft=''; });
            el.style.background='#eff6ff'; el.style.borderLeft='3px solid #3b82f6';
            selected = {id:el.dataset.id, name:el.dataset.name, profile:el.dataset.profile};
            loadPoster(selected.id, selected.name, selected.profile, el.dataset.contest, el.dataset.district, el.dataset.block, el.dataset.pan);
        }

        function loadPoster(id, name, profile, contest, district, block, pan) {
            var posterUrl = '<?php echo home_url('/candidate-poster/?candidate_id=') ?>'+id;
            document.getElementById('ps-placeholder').style.display='none';
            var frame = document.getElementById('ps-poster-frame');
            var wrap  = document.getElementById('ps-frame-wrap');
            frame.src = posterUrl;
            wrap.style.display='block';
            // Apply initial 50% zoom
            applyZoom(50);
            document.getElementById('ps-open-new-tab').href = posterUrl;
            document.getElementById('ps-image-url').value = posterUrl;
            document.getElementById('ps-view-poster').href = posterUrl;
            document.getElementById('ps-edit-candidate').href = '<?php echo admin_url('admin.php?page=enx-add-candidate&edit=') ?>'+id;
            document.getElementById('ps-actions-wrap').style.display='block';
            // Auto-fill caption
            var caption = name+'\n'+contest+'\n'+district+(block?' • '+block:'')+(pan?' • '+pan:'');
            caption += '\n\n👉 Full Profile: '+profile+'\n\n🔗 enoxxnews.com/ulb-elections/ | HP Elections 2026';
            document.getElementById('ps-caption').value = caption;
        }

        // Copy poster URL
        document.getElementById('ps-copy-poster-url').addEventListener('click', function(){
            var u = document.getElementById('ps-image-url');
            u.select(); document.execCommand('copy');
            this.textContent = '✅ Copied!';
            setTimeout(function(){ document.getElementById('ps-copy-poster-url').textContent='📋 Copy'; },2000);
        });

        // Facebook publish
        document.getElementById('ps-fb-publish').addEventListener('click', function(){
            var btn = this;
            var res = document.getElementById('ps-fb-result');
            var imageUrl = document.getElementById('ps-image-url').value;
            var caption  = document.getElementById('ps-caption').value;
            if(!imageUrl){alert('Please enter a poster image URL first.');return;}
            btn.textContent='📘 Publishing...'; btn.disabled=true;
            var fd=new FormData();
            fd.append('action','enx_fb_publish');
            fd.append('nonce',nonce);
            fd.append('image_url',imageUrl);
            fd.append('caption',caption);
            fd.append('profile_url',selected?selected.profile:'');
            fetch(ajax,{method:'POST',body:fd}).then(r=>r.json()).then(function(d){
                btn.textContent='📘 Publish to Facebook'; btn.disabled=false;
                res.style.display='block';
                if(d.success){
                    res.style.background='#d1fae5'; res.style.color='#065f46';
                    res.textContent='✅ '+d.data.message;
                } else {
                    res.style.background='#fee2e2'; res.style.color='#991b1b';
                    res.textContent='❌ '+d.data;
                }
            });
        });
        function applyZoom(pct) {
            var scale = pct / 100;
            var sw = document.getElementById('ps-scale-wrap');
            var scrollWrap = document.getElementById('ps-scroll-wrap');
            if(sw) {
                sw.style.transform = 'scale('+scale+')';
                sw.style.width  = (1080 * scale) + 'px';
                sw.style.height = (1080 * scale) + 'px';
                // Let scroll-wrap height stay at max-height:620px, scrollable
            }
            var lbl = document.getElementById('ps-zoom-label');
            if(lbl) lbl.textContent = pct+'%';
        }
        var zoomSlider = document.getElementById('ps-zoom-slider');
        if(zoomSlider) {
            zoomSlider.addEventListener('input', function(){ applyZoom(parseInt(this.value)); });
        }
    })();
    </script>
    <?php
}
