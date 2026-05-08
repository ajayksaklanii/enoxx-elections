<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Shortcode: [enx_candidate_form] ──────────────────────────────────── */
add_shortcode( 'enx_candidate_form', 'enx_render_public_form' );

/* ── Page template intercept for /candidate-form/ ─────────────────────── */
add_action( 'template_redirect', function() {
    if ( ! is_page() ) return;
    global $post;
    if ( ! $post ) return;
    if ( has_shortcode($post->post_content, 'enx_candidate_form') ) {
        add_filter('body_class', function($c){ $c[]='enx-public-form-page'; return $c; });
    }
} );

/* ── AJAX: Handle public form submission ───────────────────────────────── */
add_action( 'wp_ajax_enx_public_submit',        'enx_handle_public_submission' );
add_action( 'wp_ajax_nopriv_enx_public_submit', 'enx_handle_public_submission' );

function enx_handle_public_submission() {
    if ( ! wp_verify_nonce($_POST['_wpnonce']??'', 'enx_public_form') ) {
        wp_send_json_error('Security check failed. Please refresh and try again.');
    }

    // Rate limiting: max 3 submissions per IP per hour
    $ip    = sanitize_text_field($_SERVER['REMOTE_ADDR']??'unknown');
    $rl_key= 'enx_sub_'.md5($ip);
    $count = (int) get_transient($rl_key);
    if ( $count >= 5 ) {
        wp_send_json_error('Too many submissions. Please try again later.');
    }
    set_transient($rl_key, $count + 1, HOUR_IN_SECONDS);

    // Sanitise fields
    $name_en  = sanitize_text_field($_POST['name_en']??'');
    $name_hi  = sanitize_text_field($_POST['name_hi']??'');
    $age      = absint($_POST['age']??0);
    $gender   = sanitize_text_field($_POST['gender']??'');
    $phone    = sanitize_text_field($_POST['phone']??'');
    $district = sanitize_title($_POST['district_slug']??'');
    $block    = sanitize_title($_POST['block_slug']??'');
    $contest  = sanitize_text_field($_POST['contest']??'');
    $pan_slugs= array_filter(array_map('sanitize_title', (array)($_POST['panchayat_slugs']??[])));
    $party    = sanitize_text_field($_POST['party']??'');
    $notes    = sanitize_textarea_field($_POST['notes_text']??'');
    $donation_amount = sanitize_text_field($_POST['donation_amount']??'0');

    if ( ! $name_en || ! $phone || ! $district || ! $contest ) {
        wp_send_json_error('Please fill all required fields (Name, Phone, District, Position).');
    }
    if ( strlen($phone) < 10 ) {
        wp_send_json_error('Please enter a valid 10-digit phone number.');
    }

    // Build title
    $pan_text = implode(', ', array_map('enx_labelize', $pan_slugs));
    $title    = trim(implode(' ', array_filter([$name_en, $contest, $pan_text ?: enx_labelize($block)])));

    // Insert as draft
    $post_id = wp_insert_post([
        'post_type'   => 'candidate',
        'post_title'  => $title ?: $name_en,
        'post_status' => 'pending',
        'post_author' => 1,
    ]);

    if ( is_wp_error($post_id) ) {
        wp_send_json_error('Could not save your submission. Please try again.');
    }

    // Meta fields
    update_post_meta($post_id, 'candidate_name_text', $name_en);
    update_post_meta($post_id, 'candidate_name_hi',   $name_hi);
    update_post_meta($post_id, 'age_text',            $age ?: '');
    update_post_meta($post_id, 'gender_text',         $gender);
    update_post_meta($post_id, 'phone_text',          $phone);
    update_post_meta($post_id, 'district_slug',       $district);
    update_post_meta($post_id, 'district_text',       enx_district_label($district) ?: enx_labelize($district));
    update_post_meta($post_id, 'block_slug',          $block);
    update_post_meta($post_id, 'block_text',          enx_block_label($district, $block) ?: enx_labelize($block));
    update_post_meta($post_id, 'contest_text',        $contest);
    update_post_meta($post_id, 'contest_slug',        sanitize_title($contest));
    update_post_meta($post_id, 'election_type',       'panchayat');
    update_post_meta($post_id, 'election_slug',       'panchayat-elections-2026');
    update_post_meta($post_id, 'party_affiliation_text', $party);
    update_post_meta($post_id, 'profile_tier_text',   'basic');
    update_post_meta($post_id, 'submitted_by',        'public_form');
    update_post_meta($post_id, 'submitter_ip',        $ip);
    if ( $notes !== '' ) {
        update_post_meta($post_id, 'notes_text', $notes);
    }

    if ( ! empty($pan_slugs) ) {
        update_post_meta($post_id, 'panchayat_slugs', implode(',', $pan_slugs));
        update_post_meta($post_id, 'panchayat_slug',  $pan_slugs[0]);
        $pname = enx_panchayat_label($district, $block, $pan_slugs[0]) ?: enx_labelize($pan_slugs[0]);
        update_post_meta($post_id, 'panchayat_text',  $pname);
    }

    // Photo upload handling
    if ( ! empty($_FILES['photo']['tmp_name']) ) {
        require_once ABSPATH.'wp-admin/includes/image.php';
        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/media.php';
        $att_id = media_handle_upload('photo', $post_id);
        if ( ! is_wp_error($att_id) ) {
            update_post_meta($post_id, 'candidate_photo_id', $att_id);
        }
    }

    // Record donation selection
    $don_val = intval($donation_amount);
    if ( $don_val > 0 ) {
        update_post_meta($post_id, 'donation_selected', $don_val);
        update_post_meta($post_id, 'donation_status',   'selected'); // changes to 'paid' when confirmed
        // Store in donations log
        $log = get_option('enx_donation_log', []);
        $log[] = [
            'post_id'   => $post_id,
            'name'      => $name_en,
            'phone'     => $phone,
            'amount'    => $don_val,
            'timestamp' => current_time('mysql'),
            'status'    => 'pending',
        ];
        update_option('enx_donation_log', array_slice($log, -500)); // keep last 500
    }

    wp_send_json_success([
        'post_id'         => $post_id,
        'name'            => $name_en,
        'donation_amount' => $don_val,
        // Randomly pick UPI 1 or 2 for load distribution
        'upi_id'    => (rand(0,1) && get_option('enx_upi_id_2')) ? get_option('enx_upi_id_2') : get_option('enx_upi_id',''),
        'upi_qr_url'=> (rand(0,1) && get_option('enx_upi_qr_url_2')) ? get_option('enx_upi_qr_url_2') : get_option('enx_upi_qr_url',''),
        'upi_name'  => get_option('enx_upi_name','Enoxx News'),
    ]);
}

/* ── Render the public form ─────────────────────────────────────────────── */
function enx_render_public_form( $atts ) {
    $loc_data  = enx_get_location_data();
    $districts = array_filter(array_keys($loc_data), function($k){ return $k !== 'zp_wards'; });
    $nonce     = wp_create_nonce('enx_public_form');
    $upi_id    = get_option('enx_upi_id','');
    $upi_name  = get_option('enx_upi_name','Enoxx News');
    $upi_qr    = get_option('enx_upi_qr_url','');
    $donation_amounts = [300, 500];

    $gp_pos  = ['Pradhan','Up-Pradhan'];
    $zp_pos  = ['Zila Parishad Member'];
    $bdc_pos = ['Panchayat Samiti Member (BDC)'];
    $all_pos = array_merge($gp_pos, $zp_pos, $bdc_pos);

    ob_start(); ?>
<style>
.enx-pub-form { max-width:680px; margin:0 auto; font-family:inherit; }
.enx-pub-form h2 { font-size:22px; margin:0 0 6px; }
.enx-pub-form .enx-pub-subtitle { color:#666; margin:0 0 24px; font-size:14px; }
.enx-pf-section { background:#fff; border:1px solid #e5e5e5; border-radius:12px; padding:20px; margin-bottom:16px; }
.enx-pf-section h3 { margin:0 0 16px; font-size:15px; color:#1e3a5f; border-left:4px solid #f59e0b; padding-left:10px; }
.enx-pf-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px; }
.enx-pf-row.full { grid-template-columns:1fr; }
.enx-pf-field { display:flex; flex-direction:column; gap:5px; }
.enx-pf-field label { font-size:12px; font-weight:700; color:#555; }
.enx-pf-field label span { font-weight:400; color:#888; }
.enx-pf-field input, .enx-pf-field select { padding:10px 12px; border:1px solid #ddd; border-radius:8px; font-size:14px; width:100%; box-sizing:border-box; }
.enx-pf-field select[multiple] { min-height:120px; }
.enx-pf-field input:focus, .enx-pf-field select:focus { border-color:#f59e0b; outline:none; box-shadow:0 0 0 3px rgba(245,158,11,.15); }
.enx-pf-required { color:#e11d48; }
.enx-pub-submit { background:#1e3a5f; color:#fff; border:none; border-radius:10px; padding:14px 24px; font-size:16px; font-weight:700; width:100%; cursor:pointer; margin-top:8px; }
.enx-pub-submit:hover { background:#2563eb; }
/* Donation section */
.enx-donation-wrap { display:none; }
.enx-donation-radios { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
.enx-donation-radios label { display:flex; align-items:center; gap:6px; padding:8px 16px; border:2px solid #ddd; border-radius:8px; cursor:pointer; font-weight:600; font-size:15px; }
.enx-donation-radios input[type=radio]:checked + span { color:#1e3a5f; }
.enx-donation-radios label:has(input:checked) { border-color:#f59e0b; background:#fffbeb; }
.enx-upi-section { display:none; text-align:center; padding:20px; background:#f0fdf4; border:1px solid #86efac; border-radius:12px; margin-top:16px; }
.enx-upi-section h3 { color:#15803d; margin:0 0 10px; }
.enx-upi-section .enx-upi-id { font-size:20px; font-weight:700; letter-spacing:1px; color:#1e3a5f; padding:10px 20px; background:#fff; border:2px dashed #f59e0b; border-radius:8px; display:inline-block; margin:8px 0; }
.enx-upi-qr { max-width:200px; margin:12px auto; display:block; }
.enx-success-msg { display:none; background:#d1fae5; border:1px solid #6ee7b7; border-radius:12px; padding:24px; text-align:center; }
.enx-success-msg h2 { color:#065f46; }
@media (max-width:600px) {
    .enx-pf-row { grid-template-columns:1fr; }
    .enx-donation-radios { gap:8px; }
}
</style>

<div class="enx-pub-form" id="enx-public-form-wrap">
    <div class="enx-pf-section" style="background:linear-gradient(135deg,#1e3a5f,#2563eb);color:#fff;border:none">
        <h2 style="color:#fff">🗳️ HP Elections 2026 — Candidate Registration</h2>
        <p class="enx-pub-subtitle" style="color:#bfdbfe">हिमाचल प्रदेश चुनाव 2026 — उम्मीदवार पंजीकरण</p>
        <p style="font-size:13px;color:#93c5fd;margin:0">Fill the form below to add your profile on Enoxx News. Our team will review and publish within 24 hours.<br>
        नीचे फॉर्म भरें। हमारी टीम 24 घंटे में रिव्यू कर प्रकाशित करेगी।</p>
    </div>

    <form id="enx-public-form" enctype="multipart/form-data">
        <input type="hidden" name="_wpnonce" value="<?php echo $nonce ?>">
        <input type="hidden" name="action" value="enx_public_submit">

        <!-- Name -->
        <div class="enx-pf-section">
            <h3>👤 Candidate Name / उम्मीदवार का नाम</h3>
            <div class="enx-pf-row">
                <div class="enx-pf-field">
                    <label>Name in English <span>(अंग्रेजी में नाम)</span> <span class="enx-pf-required">*</span></label>
                    <input type="text" name="name_en" placeholder="e.g. Ram Kapoor" required>
                </div>
                <div class="enx-pf-field">
                    <label>Name in Hindi <span>(हिंदी में नाम)</span></label>
                    <input type="text" name="name_hi" placeholder="जैसे राम कपूर">
                </div>
            </div>
            <div class="enx-pf-row">
                <div class="enx-pf-field">
                    <label>Age <span>(आयु)</span></label>
                    <input type="number" name="age" min="21" max="90" placeholder="e.g. 45">
                </div>
                <div class="enx-pf-field">
                    <label>Gender <span>(लिंग)</span></label>
                    <select name="gender">
                        <option value="">Select / चुनें</option>
                        <option value="Male">Male / पुरुष</option>
                        <option value="Female">Female / महिला</option>
                        <option value="Other">Other / अन्य</option>
                    </select>
                </div>
            </div>
            <div class="enx-pf-row full">
                <div class="enx-pf-field">
                    <label>Phone Number <span>(फोन नंबर)</span> <span class="enx-pf-required">*</span></label>
                    <input type="tel" name="phone" placeholder="10-digit mobile number" maxlength="10" pattern="[0-9]{10}" required>
                </div>
            </div>
        </div>

        <!-- Election Position -->
        <div class="enx-pf-section">
            <h3>🗳️ Election Details / चुनाव विवरण</h3>
            <div class="enx-pf-row">
                <div class="enx-pf-field">
                    <label>District <span>(जिला)</span> <span class="enx-pf-required">*</span></label>
                    <select name="district_slug" id="pf-district" required>
                        <option value="">Select District / जिला चुनें</option>
                        <?php foreach($districts as $dk): ?>
                        <option value="<?php echo esc_attr($dk) ?>"><?php echo esc_html(enx_district_label($dk)?:enx_labelize($dk)) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="enx-pf-field">
                    <label>Position / Candidate Type <span>(पद का चुनाव)</span> <span class="enx-pf-required">*</span></label>
                    <select name="contest" id="pf-contest" required>
                        <option value="">Select Position / पद चुनें</option>
                        <optgroup label="Gram Panchayat / ग्राम पंचायत">
                            <option value="Pradhan">Pradhan (प्रधान)</option>
                            <option value="Up-Pradhan">Up-Pradhan (उप-प्रधान)</option>
                        </optgroup>
                        <optgroup label="Panchayat Samiti / पंचायत समिति">
                            <option value="Panchayat Samiti Member (BDC)">Panchayat Samiti Member / पंचायत समिति सदस्य (BDC)</option>
                        </optgroup>
                        <optgroup label="Zila Parishad / जिला परिषद">
                            <option value="Zila Parishad Member">Zila Parishad Member / जिला परिषद सदस्य</option>
                        </optgroup>
                    </select>
                </div>
            </div>

            <div id="pf-block-wrap" style="display:none" class="enx-pf-row full">
                <div class="enx-pf-field">
                    <label>Block / Panchayat Samiti <span>(ब्लॉक / पंचायत समिति)</span></label>
                    <select name="block_slug" id="pf-block">
                        <option value="">Select Block / ब्लॉक चुनें</option>
                    </select>
                </div>
            </div>

            <!-- GP: single panchayat dropdown -->
            <div id="pf-pan-wrap" style="display:none" class="enx-pf-row full">
                <div class="enx-pf-field">
                    <label>Gram Panchayat <span>(ग्राम पंचायत)</span></label>
                    <select name="panchayat_slugs[]" id="pf-panchayat">
                        <option value="">Select Panchayat / पंचायत चुनें</option>
                    </select>
                </div>
            </div>

            <!-- BDC: checkbox list of panchayats (2-6 selection) -->
            <div id="pf-multi-pan-wrap" style="display:none" class="enx-pf-row full">
                <div class="enx-pf-field">
                    <label>Select Panchayats in your Panchayat Samiti Ward <span>(अपने वार्ड की पंचायतें चुनें — 2 से 6 तक)</span></label>
                    <div id="pf-checkbox-list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;max-height:320px;overflow-y:auto;padding:10px;border:1px solid #ddd;border-radius:8px;background:#fafafa"></div>
                    <small style="color:#888;margin-top:4px;display:block" id="pf-check-count">Select 2 to 6 panchayats / 2 से 6 पंचायतें चुनें</small>
                </div>
            </div>

        </div>

        <!-- Bio / Notes -->
        <div class="enx-pf-section">
            <h3>📝 Candidate Information / उम्मीदवार के बारे में लिखें</h3>
            <div class="enx-pf-field">
                <label>आपके बारे में जानकारी <span>(About you — achievements, promises, background, local issues)</span></label>
                <textarea name="notes_text" rows="6" style="padding:10px;border:1px solid #ddd;border-radius:8px;font-size:14px;width:100%;box-sizing:border-box;resize:vertical" placeholder="अपने बारे में, अपनी उपलब्धियाँ, चुनावी वादे और स्थानीय मुद्दे लिखें..."></textarea>
                <small style="color:#888;margin-top:4px;display:block">हमारी टीम आपकी जानकारी के आधार पर हिंदी और English दोनों में आपकी प्रोफाइल तैयार करेगी।<br>Our team will create the final English &amp; Hindi profile based on your inputs.</small>
            </div>
        </div>

        <!-- Photo -->
        <div class="enx-pf-section">
            <h3>📸 Photo / फोटो</h3>
            <div class="enx-pf-field">
                <label>Upload your photo <span>(अपनी फोटो अपलोड करें — JPG/PNG, max 5MB)</span></label>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" style="border:none;padding:0">
            </div>
            <small style="color:#888">A clear, front-facing photo works best. Formal attire preferred.<br>
            स्पष्ट, सामने की फोटो सबसे अच्छी होती है।</small>
        </div>

        <!-- Donation step — hidden until after validation -->
        <div class="enx-pf-section enx-donation-wrap" id="enx-donation-section">
            <h3 style="color:#1e3a5f">🙏 निष्पक्ष पत्रकारिता के लिए हमें सपोर्ट करें</h3>
            <p style="color:#444;font-size:16px;line-height:1.6;margin-bottom:16px">यह सहयोग आप अपनी इच्छा से कर सकते हैं, वेबसाइट में आपकी खबर लगाने के लिए ज़रूरी नहीं है।</p>

            <div class="enx-donation-radios" id="enx-don-radios" style="margin-bottom:18px">
                <?php foreach($donation_amounts as $amt): ?>
                <label>
                    <input type="radio" name="donation_amount" value="<?php echo $amt ?>">
                    <span>₹<?php echo $amt ?></span>
                </label>
                <?php endforeach ?>
            </div>

            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <button type="button" id="enx-don-pay" class="enx-pub-submit" style="background:#15803d;font-size:15px;padding:14px 18px;line-height:1.2">
                    ✅ सहयोग के साथ सबमिट करें
                </button>
                <button type="button" id="enx-don-skip" class="enx-pub-submit" style="background:#6b7280;width:auto;font-size:15px;padding:14px 18px;line-height:1.2">
                    बिना सहयोग सबमिट करें
                </button>
            </div>

            <!-- UPI payment display -->
            <div class="enx-upi-section" id="enx-upi-display">
                <h3>📲 Pay via UPI</h3>
                <p style="color:#555;font-size:14px">Open any UPI app and pay to:</p>
                <div class="enx-upi-id" id="enx-upi-id-text"><?php echo esc_html($upi_id) ?></div>
                <p id="enx-upi-amount-label" style="font-weight:700;font-size:18px;color:#1e3a5f"></p>
                <?php if($upi_qr): ?>
                <img src="<?php echo esc_url($upi_qr) ?>" alt="UPI QR Code" class="enx-upi-qr" id="enx-qr-img">
                <?php else: ?>
                <div id="enx-qr-img" style="width:200px;height:200px;margin:12px auto;background:#f3f4f6;border:2px dashed #d1d5db;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:13px;text-align:center;padding:10px">QR Code<br>not configured</div>
                <?php endif ?>
                <p style="font-size:12px;color:#888;margin-top:8px">Scan QR code or use UPI ID above. After payment, your profile will be given priority for publishing.</p>
                <p style="font-size:12px;color:#888">भुगतान के बाद आपकी प्रोफाइल को प्राथमिकता से प्रकाशित किया जाएगा।</p>
                <button type="button" id="enx-payment-done" class="enx-pub-submit" style="background:#1e3a5f;margin-top:12px;max-width:320px">
                    ✅ I have made the payment / मैंने भुगतान कर दिया है
                </button>
            </div>
        </div>

        <!-- Submit (initial) -->
        <div id="enx-submit-wrap">
            <button type="submit" class="enx-pub-submit">
                🗳️ Submit Registration / पंजीकरण जमा करें
            </button>
            <p style="font-size:12px;color:#888;margin-top:8px;text-align:center">
                Your profile will be reviewed and published within 24 hours.<br>
                आपकी प्रोफाइल 24 घंटे में रिव्यू होगी।
            </p>
        </div>

        <div id="enx-form-error" style="display:none;padding:12px;background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;font-size:14px;margin-top:12px"></div>
    </form>

    <!-- Success message -->
    <div class="enx-success-msg" id="enx-form-success">
        <div style="font-size:48px">🎉</div>
        <h2>Registration Submitted! / पंजीकरण सफल!</h2>
        <p>Thank you, <strong id="enx-success-name"></strong>! Your profile has been received and will be published within 24 hours.</p>
        <p>धन्यवाद! आपकी प्रोफाइल प्राप्त हो गई है और 24 घंटे में प्रकाशित की जाएगी।</p>
    </div>
</div>

<script>
(function(){
    var locData = <?php echo wp_json_encode($loc_data) ?>;
    var ajaxUrl = '<?php echo admin_url('admin-ajax.php') ?>';
    var bdcPos  = <?php echo wp_json_encode($bdc_pos) ?>;
    var isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

    var dSel  = document.getElementById('pf-district');
    var cSel  = document.getElementById('pf-contest');
    var bSel  = document.getElementById('pf-block');
    var pSel  = document.getElementById('pf-panchayat');
    var cbList= document.getElementById('pf-checkbox-list');
    var cbCount=document.getElementById('pf-check-count');

    function reset(sel, lbl) { if(sel) sel.innerHTML = '<option value="">' + lbl + '</option>'; }
    function clearCheckboxes(){ if(cbList) cbList.innerHTML=''; }

    function getSortedPanchayats(d, b) {
        if(!d||!b||!locData[d]) return [];
        var pans = (locData[d].blocks[b]||{}).panchayats || {};
        return Object.keys(pans).sort(function(a,b2){
            var an = (typeof pans[a]==='object') ? (pans[a].label_en||a) : (pans[a]||a);
            var bn = (typeof pans[b2]==='object') ? (pans[b2].label_en||b2) : (pans[b2]||b2);
            return String(an).localeCompare(String(bn));
        }).map(function(s){
            var p = pans[s];
            var lbl = (typeof p==='object') ? (p.label_en||s) : p;
            return {slug:s, label:lbl};
        });
    }

    function updateCheckCount(){
        if(!cbList || !cbCount) return;
        var n = cbList.querySelectorAll('input[type="checkbox"]:checked').length;
        if (n === 0) {
            cbCount.textContent = 'Select 2 to 6 panchayats / 2 से 6 पंचायतें चुनें';
            cbCount.style.color = '#888';
        } else if (n < 2) {
            cbCount.textContent = 'Selected: '+n+' — please select at least 2 / कम से कम 2 चुनें';
            cbCount.style.color = '#c2410c';
        } else if (n > 6) {
            cbCount.textContent = 'Selected: '+n+' — maximum 6 allowed / अधिकतम 6 तक';
            cbCount.style.color = '#c2410c';
        } else {
            cbCount.textContent = 'Selected: '+n+' panchayats ✓';
            cbCount.style.color = '#15803d';
        }
    }

    function populateBdcCheckboxes(){
        if(!cbList) return;
        clearCheckboxes();
        var d = dSel ? dSel.value : '';
        var b = bSel ? bSel.value : '';
        var items = getSortedPanchayats(d, b);
        if (!items.length) {
            cbList.innerHTML = '<p style="color:#888;grid-column:1/-1;margin:0">'
                + (b ? 'No panchayats found / कोई पंचायत नहीं मिली' : 'Select a Block first / पहले ब्लॉक चुनें')
                + '</p>';
            updateCheckCount();
            return;
        }
        items.forEach(function(it){
            var lbl = document.createElement('label');
            lbl.style.cssText = 'display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid #e0e0e0;border-radius:8px;background:#fff;cursor:pointer;font-size:13px';
            var cb = document.createElement('input');
            cb.type='checkbox'; cb.name='panchayat_slugs[]'; cb.value=it.slug;
            cb.style.cssText='width:15px;height:15px;accent-color:#1e3a5f';
            cb.addEventListener('change', updateCheckCount);
            var span = document.createElement('span');
            span.textContent = it.label;
            lbl.appendChild(cb);
            lbl.appendChild(span);
            cbList.appendChild(lbl);
        });
        updateCheckCount();
    }

    if(dSel) dSel.addEventListener('change', function(){
        reset(bSel,'Select Block / ब्लॉक चुनें');
        reset(pSel,'Select Panchayat / पंचायत चुनें');
        clearCheckboxes();
        var d = this.value;
        if (!d || !locData[d]) { applyContest(); return; }
        Object.keys(locData[d].blocks||{}).sort(function(a,b2){
            return ((locData[d].blocks[a]||{}).label_en||a).localeCompare((locData[d].blocks[b2]||{}).label_en||b2);
        }).forEach(function(bs){
            bSel.appendChild(new Option((locData[d].blocks[bs].label_en||bs), bs));
        });
        applyContest();
    });

    if(bSel) bSel.addEventListener('change', function(){
        reset(pSel,'Select Panchayat / पंचायत चुनें');
        var d=dSel.value, b=this.value;
        if(d&&b&&locData[d]){
            var items = getSortedPanchayats(d, b);
            items.forEach(function(it){ pSel.appendChild(new Option(it.label, it.slug)); });
        }
        // Always refresh the BDC checkbox grid when block changes
        populateBdcCheckboxes();
    });

    function applyContest(){
        var v = cSel ? cSel.value : '';
        var isBDC = bdcPos.indexOf(v) >= 0;
        var isGP  = (v === 'Pradhan' || v === 'Up-Pradhan');
        var showBlock = isBDC || isGP;
        var blockWrap = document.getElementById('pf-block-wrap');
        var panWrap   = document.getElementById('pf-pan-wrap');
        var multiWrap = document.getElementById('pf-multi-pan-wrap');
        if(blockWrap) blockWrap.style.display = showBlock ? '' : 'none';
        if(panWrap)   panWrap.style.display   = isGP ? '' : 'none';
        if(multiWrap) multiWrap.style.display = isBDC ? '' : 'none';
        // When switching to BDC, populate the checkbox grid for the currently-selected block
        if (isBDC) populateBdcCheckboxes();
    }
    if(cSel) cSel.addEventListener('change', applyContest);

    var submittedPostId = null;

    // Form submit - validate then show donation section
    document.getElementById('enx-public-form').addEventListener('submit', function(e){
        e.preventDefault();
        var err = document.getElementById('enx-form-error');
        err.style.display = 'none';

        // Basic validation
        // BDC checkbox validation
        var isBDC = document.getElementById('pf-contest').value.indexOf('BDC') >= 0 || document.getElementById('pf-contest').value.indexOf('Samiti') >= 0;
        if (isBDC && document.getElementById('pf-multi-pan-wrap').style.display !== 'none') {
            var checkedCount = document.querySelectorAll('#pf-checkbox-list input:checked').length;
            if (checkedCount < 2) {
                err.textContent = 'Please select at least 2 panchayats for Panchayat Samiti Ward. / कृपया कम से कम 2 पंचायतें चुनें।';
                err.style.display = 'block'; return;
            }
            if (checkedCount > 6) {
                err.textContent = 'Maximum 6 panchayats allowed. / अधिकतम 6 पंचायतें चुन सकते हैं।';
                err.style.display = 'block'; return;
            }
        }
        if (!document.querySelector('[name="name_en"]').value.trim()) {
            err.textContent = 'Please enter your name. / कृपया अपना नाम दर्ज करें।';
            err.style.display = 'block'; return;
        }
        var phone = document.querySelector('[name="phone"]').value.trim();
        if (phone.length !== 10 || !/^\d+$/.test(phone)) {
            err.textContent = 'Please enter a valid 10-digit phone number. / 10 अंक का फोन नंबर दर्ज करें।';
            err.style.display = 'block'; return;
        }

        // Show donation section, hide submit button
        document.getElementById('enx-submit-wrap').style.display = 'none';
        document.getElementById('enx-donation-section').style.display = 'block';
        document.getElementById('enx-donation-section').scrollIntoView({behavior:'smooth'});
    });

    function doSubmit(donationAmount) {
        var fd = new FormData(document.getElementById('enx-public-form'));
        fd.set('donation_amount', donationAmount || 0);

        // Show loading
        document.getElementById('enx-don-pay').disabled = true;
        document.getElementById('enx-don-pay').textContent = 'जमा हो रहा है...';

        fetch(ajaxUrl, {method:'POST', body:fd}).then(function(r){ return r.json(); }).then(function(d){
            if (!d.success) {
                var err = document.getElementById('enx-form-error');
                err.textContent = d.data || 'Error. Please try again.';
                err.style.display = 'block';
                document.getElementById('enx-don-pay').disabled = false;
                document.getElementById('enx-don-pay').textContent = '✅ सहयोग के साथ सबमिट करें';
                return;
            }
            submittedPostId = d.data.post_id;
            if (donationAmount > 0 && d.data.upi_id) {
                showUPI(donationAmount, d.data.upi_id, d.data.upi_name, d.data.upi_qr_url);
            } else {
                showSuccess(d.data.name);
            }
        }).catch(function(){
            document.getElementById('enx-form-error').textContent = 'Network error. Please try again.';
            document.getElementById('enx-form-error').style.display = 'block';
            document.getElementById('enx-don-pay').disabled = false;
        });
    }

    function showUPI(amount, upiId, upiName, qrUrl) {
        document.getElementById('enx-upi-id-text').textContent = upiId;
        document.getElementById('enx-upi-amount-label').textContent = '₹' + amount;
        if (qrUrl) {
            var qrImg = document.getElementById('enx-qr-img');
            if (qrImg && qrImg.tagName === 'IMG') qrImg.src = qrUrl;
        }

        // On mobile: open UPI intent link
        if (isMobile && upiId) {
            var upiLink = 'upi://pay?pa='+encodeURIComponent(upiId)+'&pn='+encodeURIComponent(upiName)+'&am='+amount+'&cu=INR';
            setTimeout(function(){ window.location.href = upiLink; }, 500);
        }

        document.getElementById('enx-upi-display').style.display = 'block';
        document.getElementById('enx-upi-display').scrollIntoView({behavior:'smooth'});
    }

    function showSuccess(name) {
        document.getElementById('enx-public-form-wrap').querySelector('form').style.display='none';
        document.getElementById('enx-form-success').style.display = 'block';
        document.getElementById('enx-success-name').textContent = name;
        document.getElementById('enx-form-success').scrollIntoView({behavior:'smooth'});
    }

    document.getElementById('enx-don-pay').addEventListener('click', function(){
        var radio  = document.querySelector('[name="donation_amount"]:checked');
        var amount = radio ? parseInt(radio.value) : 0;
        if (!amount || amount < 10) {
            alert('कृपया राशि चुनें / Please select an amount.');
            return;
        }
        doSubmit(amount);
    });

    document.getElementById('enx-don-skip').addEventListener('click', function(){
        doSubmit(0);
    });

    document.getElementById('enx-payment-done').addEventListener('click', function(){
        showSuccess(document.querySelector('[name="name_en"]').value);
    });
})();
</script>
<?php
    return ob_get_clean();
}
