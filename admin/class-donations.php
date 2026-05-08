<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── UPI Settings in Sync tab ──────────────────────────────────────────── */
// Keys are saved by class-settings.php save handler — just need them in $opts list
// Hooked from class-settings.php via filter
add_filter('enx_settings_extra_opts', function($opts){
    $opts[] = 'enx_upi_id';
    $opts[] = 'enx_upi_name';
    $opts[] = 'enx_upi_qr_url';
    return $opts;
});

/* ── Admin: UPI QR code upload AJAX ────────────────────────────────────── */
add_action('wp_ajax_enx_upload_qr', function(){
    if (!wp_verify_nonce($_POST['nonce']??'','enx_admin')) wp_send_json_error('nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('permission');
    if (empty($_FILES['qr_file'])) wp_send_json_error('No file');
    require_once ABSPATH.'wp-admin/includes/image.php';
    require_once ABSPATH.'wp-admin/includes/file.php';
    require_once ABSPATH.'wp-admin/includes/media.php';
    $id = media_handle_upload('qr_file', 0);
    if (is_wp_error($id)) wp_send_json_error($id->get_error_message());
    $url = wp_get_attachment_url($id);
    update_option('enx_upi_qr_url', $url);
    wp_send_json_success(['url'=>$url]);
});

/* ── Admin menu: Donation Tracker ──────────────────────────────────────── */
add_action('admin_menu', function(){
    add_submenu_page(
        'enx-elections',
        '💰 Donation Tracker',
        '💰 Donations',
        'manage_options',
        'enx-donations',
        'enx_page_donations'
    );
}, 28);

function enx_page_donations() {
    if (!current_user_can('manage_options')) wp_die('No permission');

    // Handle status update
    if (isset($_POST['enx_don_update']) && wp_verify_nonce($_POST['_wpnonce'],'enx_don_update')) {
        $idx    = absint($_POST['don_idx']??-1);
        $status = sanitize_key($_POST['don_status']??'');
        $log    = get_option('enx_donation_log',[]);
        if (isset($log[$idx]) && in_array($status,['pending','paid','cancelled'],true)) {
            $log[$idx]['status'] = $status;
            if ($status === 'paid') {
                update_post_meta($log[$idx]['post_id'], 'donation_status', 'paid');
            }
            update_option('enx_donation_log', $log);
            echo '<div class="notice notice-success"><p>Status updated.</p></div>';
        }
    }

    $log  = get_option('enx_donation_log',[]);
    $log  = array_reverse($log, true); // newest first
    $total_pending = array_sum(array_column(array_filter($log, function($r){ return $r['status']==='pending'; }), 'amount'));
    $total_paid    = array_sum(array_column(array_filter($log, function($r){ return $r['status']==='paid'; }), 'amount'));

    $upi_id   = get_option('enx_upi_id','');
    $upi_name = get_option('enx_upi_name','Enoxx News');
    $upi_qr   = get_option('enx_upi_qr_url','');
    $nonce    = wp_create_nonce('enx_admin');
    ?>
    <div class="wrap" style="max-width:1000px">
        <h1>💰 Donation Tracker</h1>

        <!-- Summary cards -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px">
            <?php foreach([
                ['₹'.number_format($total_pending),'Pending Payments','#fef3c7','#d97706'],
                ['₹'.number_format($total_paid),'Confirmed Payments','#d1fae5','#059669'],
                [count($log),'Total Submissions','#eff6ff','#2563eb'],
            ] as [$v,$l,$bg,$col]):?>
            <div style="background:<?php echo $bg ?>;border-radius:10px;padding:16px;text-align:center">
                <div style="font-size:24px;font-weight:700;color:<?php echo $col ?>"><?php echo $v ?></div>
                <div style="font-size:13px;color:#555"><?php echo $l ?></div>
            </div>
            <?php endforeach ?>
        </div>

        <!-- UPI Settings inline -->
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px;margin-bottom:24px">
            <h2 style="margin:0 0 14px;font-size:15px">⚙️ UPI Payment Settings</h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('enx_upi_settings') ?>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:12px;align-items:end">
                    <div>
                        <label style="font-size:11px;font-weight:700;display:block;margin-bottom:4px">UPI ID #1</label>
                        <input type="text" name="enx_upi_id" value="<?php echo esc_attr($upi_id) ?>" placeholder="upi1@bank" style="padding:7px;width:100%;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;font-size:12px">
                    </div>
                    <div>
                        <label style="font-size:11px;font-weight:700;display:block;margin-bottom:4px">Name #1</label>
                        <input type="text" name="enx_upi_name" value="<?php echo esc_attr($upi_name) ?>" placeholder="Enoxx News" style="padding:7px;width:100%;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;font-size:12px">
                    </div>
                    <div>
                        <label style="font-size:11px;font-weight:700;display:block;margin-bottom:4px">QR Code #1</label>
                        <input type="file" name="qr_file" accept="image/*" style="font-size:11px">
                        <?php if($upi_qr): ?><br><img src="<?php echo esc_url($upi_qr) ?>" style="height:40px;margin-top:4px;border-radius:4px"><?php endif ?>
                    </div>
                    <div>
                        <label style="font-size:11px;font-weight:700;display:block;margin-bottom:4px">UPI ID #2 <small style="font-weight:400">(fallback)</small></label>
                        <input type="text" name="enx_upi_id_2" value="<?php echo esc_attr(get_option('enx_upi_id_2','')) ?>" placeholder="upi2@bank" style="padding:7px;width:100%;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;font-size:12px">
                    </div>
                    <div>
                        <label style="font-size:11px;font-weight:700;display:block;margin-bottom:4px">QR Code #2</label>
                        <input type="file" name="qr_file_2" accept="image/*" style="font-size:11px">
                        <?php if(get_option('enx_upi_qr_url_2')): ?><br><img src="<?php echo esc_url(get_option('enx_upi_qr_url_2')) ?>" style="height:40px;margin-top:4px;border-radius:4px"><?php endif ?>
                    </div>
                </div>
                <button type="submit" name="enx_save_upi" class="button button-primary" style="margin-top:12px">Save UPI Settings</button>
            </form>
            <?php
            if (isset($_POST['enx_save_upi']) && wp_verify_nonce($_POST['_wpnonce'],'enx_upi_settings')) {
                update_option('enx_upi_id',   sanitize_text_field($_POST['enx_upi_id']??''));
                update_option('enx_upi_name', sanitize_text_field($_POST['enx_upi_name']??''));
                require_once ABSPATH.'wp-admin/includes/image.php';
                require_once ABSPATH.'wp-admin/includes/file.php';
                require_once ABSPATH.'wp-admin/includes/media.php';
                if (!empty($_FILES['qr_file']['tmp_name'])) {
                    $att = media_handle_upload('qr_file', 0);
                    if (!is_wp_error($att)) update_option('enx_upi_qr_url', wp_get_attachment_url($att));
                }
                if (!empty($_FILES['qr_file_2']['tmp_name'])) {
                    $att2 = media_handle_upload('qr_file_2', 0);
                    if (!is_wp_error($att2)) update_option('enx_upi_qr_url_2', wp_get_attachment_url($att2));
                }
                update_option('enx_upi_id_2', sanitize_text_field($_POST['enx_upi_id_2']??''));
                echo '<div class="notice notice-success is-dismissible" style="margin-top:10px"><p>UPI settings saved.</p></div>';
            }
            ?>
        </div>

        <!-- Donation log -->
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px">
            <h2 style="margin:0 0 16px;font-size:15px">📋 Donation Log</h2>
            <?php if (empty($log)): ?>
            <p style="color:#888;text-align:center;padding:20px">No donations recorded yet.</p>
            <?php else: ?>
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead>
                    <tr style="background:#f5f5f5">
                        <th style="padding:8px;text-align:left;border-bottom:2px solid #e0e0e0">#</th>
                        <th style="padding:8px;text-align:left;border-bottom:2px solid #e0e0e0">Name</th>
                        <th style="padding:8px;text-align:left;border-bottom:2px solid #e0e0e0">Phone</th>
                        <th style="padding:8px;text-align:center;border-bottom:2px solid #e0e0e0">Amount</th>
                        <th style="padding:8px;text-align:left;border-bottom:2px solid #e0e0e0">Date</th>
                        <th style="padding:8px;text-align:center;border-bottom:2px solid #e0e0e0">Status</th>
                        <th style="padding:8px;text-align:left;border-bottom:2px solid #e0e0e0">Profile</th>
                        <th style="padding:8px;border-bottom:2px solid #e0e0e0">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($log as $idx => $entry): ?>
                <?php
                    $status_styles = [
                        'pending'   => 'background:#fef3c7;color:#92400e',
                        'paid'      => 'background:#d1fae5;color:#065f46',
                        'cancelled' => 'background:#fee2e2;color:#991b1b',
                    ];
                    $ss = $status_styles[$entry['status']??'pending'] ?? $status_styles['pending'];
                ?>
                <tr style="border-bottom:1px solid #f0f0f0">
                    <td style="padding:8px;color:#888"><?php echo $idx+1 ?></td>
                    <td style="padding:8px;font-weight:600"><?php echo esc_html($entry['name']??'') ?></td>
                    <td style="padding:8px"><?php echo esc_html($entry['phone']??'') ?></td>
                    <td style="padding:8px;text-align:center;font-weight:700;color:#1e3a5f">₹<?php echo number_format($entry['amount']??0) ?></td>
                    <td style="padding:8px;font-size:12px;color:#888"><?php echo esc_html(date('d M, H:i',strtotime($entry['timestamp']??'now'))) ?></td>
                    <td style="padding:8px;text-align:center">
                        <span style="padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;<?php echo $ss ?>"><?php echo ucfirst($entry['status']??'pending') ?></span>
                    </td>
                    <td style="padding:8px">
                        <?php if(!empty($entry['post_id'])): ?>
                        <a href="<?php echo admin_url('admin.php?page=enx-add-candidate&edit='.$entry['post_id']) ?>" style="font-size:12px">Edit</a>
                        | <a href="<?php echo get_permalink($entry['post_id']) ?>" target="_blank" style="font-size:12px">View</a>
                        <?php endif ?>
                    </td>
                    <td style="padding:8px">
                        <form method="post" style="display:flex;gap:6px;align-items:center">
                            <?php wp_nonce_field('enx_don_update') ?>
                            <input type="hidden" name="don_idx" value="<?php echo $idx ?>">
                            <select name="don_status" style="font-size:12px;padding:3px 6px;border:1px solid #ddd;border-radius:4px">
                                <option value="pending" <?php selected($entry['status']??'pending','pending') ?>>Pending</option>
                                <option value="paid"    <?php selected($entry['status']??'pending','paid') ?>>Paid</option>
                                <option value="cancelled" <?php selected($entry['status']??'pending','cancelled') ?>>Cancel</option>
                            </select>
                            <button type="submit" name="enx_don_update" class="button" style="font-size:11px;padding:2px 8px">Save</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach ?>
                </tbody>
            </table>
            <?php endif ?>
        </div>
    </div>
    <?php
}
