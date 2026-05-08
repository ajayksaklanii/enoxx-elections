<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Export fields definition ────────────────────────────────────────────── */
function enx_export_fields() {
    return [
        'ID'                      => 'Post ID',
        'post_status'             => 'Status',
        'post_date'               => 'Date Added',
        'candidate_name_text'     => 'Name (EN)',
        'candidate_name_hi'       => 'Name (HI)',
        'age_text'                => 'Age',
        'gender_text'             => 'Gender',
        'party_affiliation_text'  => 'Party',
        'profile_tier_text'       => 'Tier',
        'election_type'           => 'Election Type',
        'election_text'           => 'Election Name',
        'contest_text'            => 'Position',
        'contest_slug'            => 'Position Slug',
        'district_slug'           => 'District Slug',
        'district_text'           => 'District',
        'block_slug'              => 'Block Slug',
        'block_text'              => 'Block',
        'panchayat_slug'          => 'Panchayat Slug',
        'panchayat_text'          => 'Panchayat',
        'zp_ward_slug'            => 'ZP Ward Slug',
        'zp_ward_text'            => 'ZP Ward',
        'bdc_ward_slug'           => 'BDC Ward Slug',
        'bdc_ward_text'           => 'BDC Ward',
        'ulb_slug'                => 'ULB Slug',
        'ulb_text'                => 'ULB Name',
        'ulb_type'                => 'ULB Type',
        'ward_slug'               => 'Ward Slug',
        'ward_text'               => 'Ward',
        'constituency_slug'       => 'Constituency Slug',
        'constituency_text'       => 'Constituency',
        'short_intro_en'          => 'Bio (EN)',
        'short_intro_hi'          => 'Bio (HI)',
        'notes_text'              => 'Telecaller Notes',
        'phone_text'              => 'Phone',
        'whatsapp_text'           => 'WhatsApp',
        'facebook_url'            => 'Facebook URL',
        'instagram_url'           => 'Instagram URL',
        'youtube_url'             => 'YouTube URL',
        'candidate_video_url'     => 'Video URL',
        'submitted_by'            => 'Submitted By',
        'en_source_post_id'       => 'EN Source Post ID',
    ];
}

/* ════════════════════════════════════════════════════════════════
   EXPORT — runs on admin_init BEFORE any HTML is output
   ════════════════════════════════════════════════════════════════ */
add_action( 'admin_init', function() {
    // Export trigger
    if (
        ! isset( $_POST['enx_export'] ) ||
        ! isset( $_POST['_wpnonce'] ) ||
        ! wp_verify_nonce( $_POST['_wpnonce'], 'enx_import_export' )
    ) return;
    if ( ! current_user_can('manage_options') ) return;

    $fields = isset($_POST['export_fields']) ? (array)$_POST['export_fields'] : [];
    $format = sanitize_text_field( $_POST['export_format'] ?? 'csv' );
    $status = sanitize_text_field( $_POST['export_status'] ?? 'any' );

    if ( empty($fields) ) return;

    enx_do_export( $fields, $format, $status );
    exit; // exit AFTER sending file
} );

/* Download blank template */
add_action( 'admin_init', function() {
    if ( ! isset($_GET['enx_dl_template']) || ! isset($_GET['_wpnonce']) ) return;
    if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'enx_dl_template' ) ) return;
    if ( ! current_user_can('manage_options') ) return;

    $core = [
        'candidate_name_text','candidate_name_hi',
        'age_text','gender_text',
        'election_type','contest_text',
        'district_slug','block_slug','panchayat_slug',
        'ulb_slug','ward_slug','constituency_slug',
        'phone_text','whatsapp_text',
        'party_affiliation_text','profile_tier_text',
        'short_intro_en','short_intro_hi',
        'notes_text',
    ];

    // Clean buffer to prevent admin HTML leaking into file
    while ( ob_get_level() ) ob_end_clean();

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="enoxx-candidates-import-template.csv"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    $out = fopen( 'php://output', 'w' );
    fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) ); // UTF-8 BOM for Excel
    fputcsv( $out, $core );
    fputcsv( $out, array_fill( 0, count($core), '' ) );
    fclose( $out );
    exit;
} );

/* ── Admin page ──────────────────────────────────────────────────────────── */
add_action( 'admin_menu', function() {
    add_submenu_page(
        'enx-elections', 'Import / Export', '↕ Import / Export',
        'manage_options', 'enx-import-export', 'enx_page_import_export'
    );
}, 25 );

function enx_page_import_export() {
    if ( ! current_user_can('manage_options') ) wp_die('No permission');

    // Handle import (POST, no file headers needed)
    $import_result = '';
    if ( isset($_POST['enx_import']) && wp_verify_nonce($_POST['_wpnonce']??'','enx_import_export') ) {
        if ( ! empty($_FILES['import_file']['tmp_name']) ) {
            $import_result = enx_do_import( $_FILES['import_file']['tmp_name'], $_POST['import_fields']??[] );
        } else {
            $import_result = 'Error: No file uploaded or file too large.';
        }
    }

    $all_fields     = enx_export_fields();
    $default_export = [
        'candidate_name_text','candidate_name_hi','age_text','gender_text',
        'contest_text','district_text','block_text','panchayat_text',
        'phone_text','profile_tier_text','post_status',
        'short_intro_en','short_intro_hi',
    ];
    ?>
    <div class="wrap" style="max-width:980px">
        <h1>↕ Import / Export Candidates</h1>

        <?php if ($import_result): ?>
        <div class="notice <?php echo strpos($import_result,'Error')!==false?'notice-error':'notice-success' ?> is-dismissible">
            <p><?php echo wp_kses_post($import_result) ?></p>
        </div>
        <?php endif ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

        <!-- ── EXPORT ── -->
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px">
            <h2 style="margin:0 0 16px;font-size:16px;border-bottom:2px solid #f59e0b;padding-bottom:8px">📤 Export Candidates</h2>
            <form method="post">
                <?php wp_nonce_field('enx_import_export') ?>

                <div style="margin-bottom:14px">
                    <label style="font-size:12px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:6px">Format</label>
                    <div style="display:flex;gap:14px">
                        <label><input type="radio" name="export_format" value="csv" checked> CSV (.csv)</label>
                        <label><input type="radio" name="export_format" value="excel"> Excel (.xls)</label>
                    </div>
                </div>

                <div style="margin-bottom:14px">
                    <label style="font-size:12px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:6px">Status</label>
                    <select name="export_status" style="padding:7px;border:1px solid #ddd;border-radius:6px;font-size:13px">
                        <option value="any">All</option>
                        <option value="publish">Published Only</option>
                        <option value="pending">Pending Only</option>
                    </select>
                </div>

                <div style="margin-bottom:16px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <label style="font-size:12px;font-weight:700;text-transform:uppercase">Fields to Export</label>
                        <div style="display:flex;gap:6px">
                            <button type="button" onclick="document.querySelectorAll('[name=\'export_fields[]\']').forEach(c=>c.checked=true)" style="font-size:11px;padding:3px 8px;border:1px solid #ddd;border-radius:4px;cursor:pointer">All</button>
                            <button type="button" onclick="document.querySelectorAll('[name=\'export_fields[]\']').forEach(c=>c.checked=false)" style="font-size:11px;padding:3px 8px;border:1px solid #ddd;border-radius:4px;cursor:pointer">None</button>
                        </div>
                    </div>
                    <div style="max-height:300px;overflow-y:auto;border:1px solid #ddd;border-radius:6px;padding:10px">
                        <?php foreach($all_fields as $key=>$label): ?>
                        <label style="display:flex;align-items:center;gap:8px;padding:4px 0;font-size:13px;cursor:pointer">
                            <input type="checkbox" name="export_fields[]" value="<?php echo esc_attr($key) ?>" <?php checked(in_array($key,$default_export)) ?>>
                            <span style="font-weight:600"><?php echo esc_html($label) ?></span>
                            <span style="color:#bbb;font-size:11px"><?php echo esc_html($key) ?></span>
                        </label>
                        <?php endforeach ?>
                    </div>
                </div>

                <button type="submit" name="enx_export" class="button button-primary button-large" style="width:100%">
                    📤 Download Export File
                </button>
            </form>
        </div>

        <!-- ── IMPORT ── -->
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px">
            <h2 style="margin:0 0 16px;font-size:16px;border-bottom:2px solid #059669;padding-bottom:8px">📥 Import Candidates</h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('enx_import_export') ?>

                <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:12px;font-size:12px;margin-bottom:16px">
                    <strong>CSV format:</strong> First row = column headers. Candidates matched by
                    <code>candidate_name_text + district_slug</code> are updated; others are created.
                    Use the template below for correct column names.
                </div>

                <div style="margin-bottom:14px">
                    <label style="font-size:12px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:6px">CSV File *</label>
                    <input type="file" name="import_file" accept=".csv,.txt" required style="width:100%">
                </div>

                <button type="submit" name="enx_import" class="button button-primary button-large" style="width:100%;background:#059669;border-color:#059669">
                    📥 Import from CSV
                </button>
            </form>

            <div style="margin-top:14px;padding:12px;background:#f0f9ff;border-radius:8px">
                <strong style="font-size:13px">Download CSV Template:</strong><br>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=enx-import-export&enx_dl_template=1'),'enx_dl_template') ?>" style="font-size:13px">
                    📄 Download blank template (all fields)
                </a>
            </div>
        </div>
        </div>
    </div>
    <?php
}

/* ── Export handler ──────────────────────────────────────────────────────── */
function enx_do_export( $fields, $format, $status ) {
    $statuses = $status === 'any' ? ['publish','pending','draft'] : [$status];
    $posts    = get_posts([
        'post_type'      => 'candidate',
        'post_status'    => $statuses,
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    $all_flds = enx_export_fields();
    $date     = date('Y-m-d');

    // Clean any existing output buffer before sending file
    while ( ob_get_level() ) ob_end_clean();

    if ( $format === 'excel' ) {
        header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="enoxx-candidates-'.$date.'.xls"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        echo "<html><head><meta charset='UTF-8'></head><body>\n<table border='1'>\n<tr>";
        foreach ( $fields as $f ) {
            echo '<th>' . esc_html( $all_flds[$f] ?? $f ) . '</th>';
        }
        echo "</tr>\n";
        foreach ( $posts as $post ) {
            echo '<tr>';
            foreach ( $fields as $f ) {
                $v = enx_get_export_value( $post, $f );
                echo '<td>' . esc_html( $v ) . '</td>';
            }
            echo "</tr>\n";
        }
        echo "</table></body></html>";

    } else {
        // CSV
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="enoxx-candidates-'.$date.'.csv"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $out = fopen( 'php://output', 'w' );
        fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) ); // UTF-8 BOM
        $headers = array_map( function($f) use($all_flds) { return $all_flds[$f] ?? $f; }, $fields );
        fputcsv( $out, $headers );
        foreach ( $posts as $post ) {
            $row = array_map( function($f) use($post) { return enx_get_export_value($post,$f); }, $fields );
            fputcsv( $out, $row );
        }
        fclose( $out );
    }
}

function enx_get_export_value( $post, $field ) {
    switch ($field) {
        case 'ID':          return $post->ID;
        case 'post_status': return $post->post_status;
        case 'post_date':   return $post->post_date;
        default:            return (string) get_post_meta( $post->ID, $field, true );
    }
}

/* ── Import handler ──────────────────────────────────────────────────────── */
function enx_do_import( $file_path, $field_map ) {
    $handle = fopen( $file_path, 'r' );
    if ( ! $handle ) return 'Error: Could not open uploaded file.';

    // Strip UTF-8 BOM
    $bom = fread( $handle, 3 );
    if ( $bom !== "\xEF\xBB\xBF" ) rewind( $handle );

    $headers = fgetcsv( $handle );
    if ( ! $headers ) { fclose($handle); return 'Error: Empty CSV or invalid format.'; }

    $headers = array_map( 'trim', $headers );

    // Map column index → meta key
    $col_map = [];
    foreach ( $headers as $i => $h ) {
        $col_map[$i] = $h;
        foreach ( $field_map as $meta_key => $csv_col ) {
            if ( trim($csv_col) === $h ) $col_map[$i] = $meta_key;
        }
    }

    // Also handle "pretty" header names → meta keys
    $all_flds   = enx_export_fields();
    $label_map  = array_flip( $all_flds ); // 'Name (EN)' => 'candidate_name_text'
    foreach ( $col_map as $i => $key ) {
        if ( isset($label_map[$key]) ) $col_map[$i] = $label_map[$key];
    }

    $created = $updated = $errors = 0;

    while ( ($row = fgetcsv($handle)) !== false ) {
        if ( count($row) < 2 ) continue;
        $data = [];
        foreach ( $col_map as $i => $key ) {
            $data[$key] = isset($row[$i]) ? trim($row[$i]) : '';
        }

        $name     = $data['candidate_name_text'] ?? '';
        $district = $data['district_slug'] ?? '';
        if ( ! $name ) { $errors++; continue; }

        // Find existing
        $existing = get_posts([
            'post_type'      => 'candidate',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                ['key'=>'candidate_name_text','value'=>$name,'compare'=>'='],
                ['key'=>'district_slug','value'=>$district,'compare'=>'='],
            ],
        ]);

        $et      = $data['election_type'] ?? 'panchayat';
        $contest = $data['contest_text']  ?? '';
        $loc     = $et==='ulb' ? ($data['ulb_slug']??'') : ($data['panchayat_slug']??$data['block_slug']??'');
        $title   = trim(implode(' ',array_filter([$name,$contest,$loc]))) ?: $name;

        if ( ! empty($existing) ) {
            $post_id = $existing[0];
            // Temporarily remove the save_post action to prevent closure errors during import
            remove_action( 'save_post_candidate', 'enx_seo_save_post_candidate', 20 );
            wp_update_post( ['ID'=>$post_id,'post_title'=>$title] );
            add_action( 'save_post_candidate', 'enx_seo_save_post_candidate', 20 );
            $updated++;
        } else {
            remove_action( 'save_post_candidate', 'enx_seo_save_post_candidate', 20 );
            $post_id = wp_insert_post( [
                'post_type'   => 'candidate',
                'post_title'  => $title ?: $name,
                'post_status' => 'pending',
            ], true );
            add_action( 'save_post_candidate', 'enx_seo_save_post_candidate', 20 );
            if ( is_wp_error($post_id) ) { $errors++; continue; }
            $created++;
        }

        // Save allowed meta fields
        $allowed = array_merge( array_keys($all_flds), enx_meta_fields() );
        foreach ( $data as $key => $val ) {
            if ( in_array($key, $allowed, true) && $key !== 'ID' && $key !== 'post_status' && $key !== 'post_date' ) {
                // Textarea fields
                if ( in_array($key, ['short_intro_en','short_intro_hi','notes_text','candidate_interviews_urls'], true) ) {
                    update_post_meta( $post_id, $key, sanitize_textarea_field($val) );
                } else {
                    update_post_meta( $post_id, $key, sanitize_text_field($val) );
                }
            }
        }

        // Derive slugs from text
        if ( ! empty($data['contest_text']) && empty($data['contest_slug']) ) {
            update_post_meta( $post_id, 'contest_slug', sanitize_title($data['contest_text']) );
        }
        $eslug = ['panchayat'=>'panchayat-elections-2026','ulb'=>'ulb-elections-2026','assembly'=>'assembly-elections-2027'];
        if ( ! empty($et) ) update_post_meta( $post_id, 'election_slug', $eslug[$et] ?? '' );
    }

    fclose( $handle );
    return "Import complete: <strong>{$created} created</strong>, <strong>{$updated} updated</strong>".( $errors ? ", <strong>{$errors} skipped</strong> (missing name)" : '.' );
}
