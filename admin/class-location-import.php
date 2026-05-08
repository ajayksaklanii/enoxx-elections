<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function() {
    add_submenu_page(
        'enx-elections', 'Location Import', '📍 Location Import',
        'manage_options', 'enx-location-import', 'enx_page_location_import'
    );
}, 26 );

/* ── Download template CSV ─────────────────────────────────────────────── */
add_action( 'admin_init', function() {
    if ( ! isset($_GET['enx_loc_template']) || ! isset($_GET['_wpnonce']) ) return;
    if ( ! wp_verify_nonce($_GET['_wpnonce'],'enx_loc_template') ) return;
    if ( ! current_user_can('manage_options') ) return;

    $type = sanitize_key($_GET['enx_loc_template']);
    while(ob_get_level()) ob_end_clean();

    if ( $type === 'panchayat' ) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="panchayat-location-template.csv"');
        $out = fopen('php://output','w');
        fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out,['district_slug','district_en','district_hi','block_slug','block_en','block_hi','panchayat_slug','panchayat_en','panchayat_hi']);
        fputcsv($out,['mandi','Mandi','मंडी','sundernagar','Sundernagar','सुन्दरनगर','balh','Balh','बल्ह']);
        fclose($out);
    } elseif ( $type === 'zp_wards' ) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="zp-wards-template.csv"');
        $out = fopen('php://output','w');
        fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out,['district_slug','ward_slug','ward_en','ward_hi']);
        fputcsv($out,['kangra','majherna','Majherna','मझैरना']);
        fclose($out);
    } elseif ( $type === 'bdc_wards' ) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bdc-wards-template.csv"');
        $out = fopen('php://output','w');
        fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out,['district_slug','block_slug','ward_slug','ward_en','ward_hi']);
        fputcsv($out,['kangra','dharamshala','ward-1','Ward 1','वार्ड 1']);
        fclose($out);
    } elseif ( $type === 'ulb' ) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ulb-location-template.csv"');
        $out = fopen('php://output','w');
        fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out,['district_slug','district_en','district_hi','ulb_slug','ulb_type','ulb_en','ulb_hi','ward_slug','ward_en','ward_hi']);
        fputcsv($out,['mandi','Mandi','मंडी','mandi-mc','Municipal Council','Municipal Council, Mandi','नगर परिषद मंडी','ward-1','Ward 1','वार्ड 1']);
        fclose($out);
    } elseif ( $type === 'assembly' ) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="assembly-location-template.csv"');
        $out = fopen('php://output','w');
        fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out,['constituency_slug','constituency_en','constituency_hi','constituency_number']);
        fputcsv($out,['shimla-rural','Shimla Rural','शिमला ग्रामीण','66']);
        fclose($out);
    }
    exit;
});

/* ── Import handler via admin_init ─────────────────────────────────────── */
add_action( 'admin_init', function() {
    if ( ! isset($_POST['enx_loc_import']) ) return;
    if ( ! wp_verify_nonce($_POST['_wpnonce']??'','enx_loc_import') ) return;
    if ( ! current_user_can('manage_options') ) return;
    if ( empty($_FILES['loc_file']['tmp_name']) ) return;

    $type   = sanitize_key($_POST['loc_type']??'panchayat');
    $result = enx_do_location_import($_FILES['loc_file']['tmp_name'],$type);
    set_transient('enx_loc_import_result',$result,60);
    wp_redirect(admin_url('admin.php?page=enx-location-import&loc_type='.$type));
    exit;
});

function enx_page_location_import() {
    if ( ! current_user_can('manage_options') ) wp_die('No permission');

    $result = get_transient('enx_loc_import_result');
    if ( $result ) delete_transient('enx_loc_import_result');
    $type   = sanitize_key($_GET['loc_type']??'panchayat');

    // Stats on existing data
    $loc_data = enx_get_location_data();
    $total_blocks = 0; $total_pans = 0;
    foreach($loc_data as $dk=>$dd){
        if($dk==='zp_wards') continue;
        $total_blocks += count($dd['blocks']??[]);
        foreach(($dd['blocks']??[]) as $bk=>$bv) $total_pans += count($bv['panchayats']??[]);
    }
    $ulb_data = enx_get_ulb_data();
    $total_ulbs=0;
    foreach($ulb_data as $dd) $total_ulbs+=count($dd['ulbs']??[]);
    $asm_data = function_exists('enx_get_assembly_data') ? enx_get_assembly_data() : [];
    ?>
    <div class="wrap" style="max-width:960px">
        <h1>📍 Location Data Import</h1>
        <p style="color:#666">Import panchayat, ULB or assembly constituency data from CSV. Imported data is merged with existing data — nothing is deleted.</p>

        <?php if($result): ?>
        <div class="notice <?php echo strpos($result,'Error')!==false?'notice-error':'notice-success' ?> is-dismissible">
            <p><?php echo wp_kses_post($result) ?></p>
        </div>
        <?php endif ?>

        <!-- Current status -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px">
            <?php
            $districts = array_filter(array_keys($loc_data), function($k){ return $k!=='zp_wards'; });
            $filled_d  = count(array_filter(array_map(function($d) use($loc_data){ return count($loc_data[$d]['blocks']??[]); },$districts)));
            foreach([
                ['🏘️ Panchayat Data', count($districts).' districts', $total_blocks.' blocks', $total_pans.' panchayats', $filled_d.'/'.count($districts).' have data'],
                ['🏙️ ULB Data',       count($ulb_data).' districts', $total_ulbs.' ULBs', '', ''],
                ['🏛️ Assembly Data',  count($asm_data).' constituencies', '', '', ''],
            ] as [$title,$s1,$s2,$s3,$s4]):
            ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px">
                <h3 style="margin:0 0 10px;font-size:14px"><?php echo $title ?></h3>
                <div style="font-size:13px;color:#555"><?php echo implode('<br>',array_filter([$s1,$s2,$s3,$s4])) ?></div>
            </div>
            <?php endforeach ?>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

        <!-- Import form -->
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px">
            <h2 style="margin:0 0 16px;font-size:16px;border-bottom:2px solid #059669;padding-bottom:8px">📥 Import Location CSV</h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('enx_loc_import') ?>
                <div style="margin-bottom:14px">
                    <label style="font-size:12px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:6px">Data Type</label>
                    <select name="loc_type" id="loc-type" style="padding:8px;border:1px solid #ddd;border-radius:6px;font-size:14px;width:100%">
                        <option value="panchayat" <?php selected($type,'panchayat') ?>>🏘️ Panchayat (Districts → Blocks → Panchayats)</option>
                        <option value="zp_wards"  <?php selected($type,'zp_wards') ?>>🗳️ Zila Parishad Wards (जिला परिषद वार्ड)</option>
                        <option value="bdc_wards" <?php selected($type,'bdc_wards') ?>>🏛️ BDC / Panchayat Samiti Wards</option>
                        <option value="ulb"       <?php selected($type,'ulb') ?>>🏙️ ULB (Districts → Urban Bodies + Wards)</option>
                        <option value="assembly"  <?php selected($type,'assembly') ?>>🏛️ Assembly Constituencies</option>
                    </select>
                </div>
                <div style="margin-bottom:16px">
                    <label style="font-size:12px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:6px">CSV File *</label>
                    <input type="file" name="loc_file" accept=".csv,.txt" required style="width:100%">
                </div>
                <button type="submit" name="enx_loc_import" class="button button-primary button-large" style="width:100%;background:#059669;border-color:#047857">
                    📥 Import Locations
                </button>
            </form>
        </div>

        <!-- Templates + format guide -->
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px">
            <h2 style="margin:0 0 14px;font-size:16px;border-bottom:2px solid #f59e0b;padding-bottom:8px">📄 CSV Templates & Format</h2>
            <?php
            $nonce_p = wp_create_nonce('enx_loc_template');
            $tpls = [
                ['panchayat','🏘️ Panchayat Template','district_slug, district_en, district_hi, block_slug, block_en, block_hi, panchayat_slug, panchayat_en, panchayat_hi'],
                ['zp_wards','🗳️ Zila Parishad Wards Template','district_slug, ward_slug, ward_en, ward_hi'],
                ['bdc_wards','🏛️ BDC Wards Template','district_slug, block_slug, ward_slug, ward_en, ward_hi'],
                ['ulb','🏙️ ULB Template (with wards)','district_slug, district_en, district_hi, ulb_slug, ulb_type, ulb_en, ulb_hi, ward_slug, ward_en, ward_hi'],
                ['assembly','🏛️ Assembly Template','constituency_slug, constituency_en, constituency_hi, constituency_number'],
            ];
            foreach($tpls as [$k,$label,$cols]):
            ?>
            <div style="margin-bottom:16px;padding:12px;background:#f9f8f5;border-radius:8px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                    <strong style="font-size:13px"><?php echo $label ?></strong>
                    <a href="<?php echo add_query_arg(['enx_loc_template'=>$k,'_wpnonce'=>$nonce_p],admin_url('admin.php?page=enx-location-import')) ?>" class="button button-small">⬇ Download</a>
                </div>
                <code style="font-size:11px;color:#555;word-break:break-all"><?php echo esc_html($cols) ?></code>
            </div>
            <?php endforeach ?>

            <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:12px;font-size:12px;margin-top:8px">
                <strong>Notes:</strong><br>
                • Slugs should be lowercase, hyphenated (e.g. <code>dharamshala</code>)<br>
                • One row per panchayat/ULB/constituency<br>
                • Existing slugs are updated, new ones are added<br>
                • CSV must use UTF-8 encoding for Hindi text<br>
                • Max ~2000 rows per upload
            </div>
        </div>
        </div>

        <!-- Current data browser -->
        <div style="margin-top:28px;background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px">
            <h2 style="margin:0 0 16px;font-size:16px">📋 Current Location Data</h2>
            <div style="overflow-x:auto;max-height:400px;overflow-y:auto">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead><tr style="background:#f5f5f5;position:sticky;top:0">
                        <th style="padding:8px;text-align:left;border-bottom:2px solid #ddd">District</th>
                        <th style="padding:8px;text-align:center;border-bottom:2px solid #ddd">Blocks</th>
                        <th style="padding:8px;text-align:center;border-bottom:2px solid #ddd">Panchayats</th>
                        <th style="padding:8px;text-align:center;border-bottom:2px solid #ddd">ZP Wards</th>
                        <th style="padding:8px;text-align:left;border-bottom:2px solid #ddd">Status</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach($loc_data as $dk=>$dd):
                        if($dk==='zp_wards') continue;
                        $bc=count($dd['blocks']??[]);
                        $pc=0; foreach(($dd['blocks']??[]) as $bv) $pc+=count($bv['panchayats']??[]);
                        $zpc=count($dd['zp_wards']??[]);
                        $dn=$dd['label_en']??$dk;
                    ?>
                    <tr style="border-bottom:1px solid #f0f0f0">
                        <td style="padding:7px 8px;font-weight:600"><?php echo esc_html($dn) ?></td>
                        <td style="padding:7px 8px;text-align:center"><?php echo $bc?:"<span style='color:#ccc'>—</span>" ?></td>
                        <td style="padding:7px 8px;text-align:center"><?php echo $pc?:"<span style='color:#ccc'>—</span>" ?></td>
                        <td style="padding:7px 8px;text-align:center"><?php echo $zpc?:"<span style='color:#ccc'>—</span>" ?></td>
                        <td style="padding:7px 8px">
                            <?php if($bc>0): ?><span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700">✅ Complete</span>
                            <?php else: ?><span style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700">⚠️ No data</span><?php endif ?>
                        </td>
                    </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

/* ── Import processor ──────────────────────────────────────────────────── */
function enx_do_location_import($file_path,$type) {
    $handle = fopen($file_path,'r');
    if(!$handle) return 'Error: Could not open file.';

    // Strip BOM
    $bom = fread($handle,3);
    if($bom!=="\xEF\xBB\xBF") rewind($handle);

    $headers = fgetcsv($handle);
    if(!$headers) { fclose($handle); return 'Error: Empty CSV.'; }
    $headers = array_map('trim',$headers);
    $col = array_flip($headers);

    if($type==='panchayat') {
        return enx_import_panchayat_csv($handle,$col);
    } elseif($type==='zp_wards') {
        return enx_import_zp_wards_csv($handle,$col);
    } elseif($type==='bdc_wards') {
        return enx_import_bdc_wards_csv($handle,$col);
    } elseif($type==='ulb') {
        return enx_import_ulb_csv($handle,$col);
    } elseif($type==='assembly') {
        return enx_import_assembly_csv($handle,$col);
    }
    fclose($handle);
    return 'Error: Unknown import type.';
}

function enx_import_panchayat_csv($handle,$col) {
    $added=0; $updated=0; $errors=0;
    // Load existing data files to merge into
    $data = enx_get_location_data();

    while(($row=fgetcsv($handle))!==false) {
        if(count($row)<4) continue;
        $g = function($key) use($row,$col){ return isset($col[$key],$row[$col[$key]]) ? trim($row[$col[$key]]) : ''; };
        $ds  = sanitize_title($g('district_slug'));
        $den = $g('district_en') ?: enx_labelize($ds);
        $dhi = $g('district_hi') ?: $den;
        $bs  = sanitize_title($g('block_slug'));
        $ben = $g('block_en') ?: enx_labelize($bs);
        $bhi = $g('block_hi') ?: $ben;
        $ps  = sanitize_title($g('panchayat_slug'));
        $pen = $g('panchayat_en') ?: enx_labelize($ps);
        $phi = $g('panchayat_hi') ?: $pen;

        if(!$ds||!$bs||!$ps) { $errors++; continue; }

        if(!isset($data[$ds])) {
            $data[$ds]=['label_en'=>$den,'label_hi'=>$dhi,'blocks'=>[],'zp_wards'=>[]];
        }
        if(!isset($data[$ds]['blocks'][$bs])) {
            $data[$ds]['blocks'][$bs]=['label_en'=>$ben,'label_hi'=>$bhi,'panchayats'=>[]];
        }
        $exists = isset($data[$ds]['blocks'][$bs]['panchayats'][$ps]);
        $data[$ds]['blocks'][$bs]['panchayats'][$ps]=['label_en'=>$pen,'label_hi'=>$phi];
        $exists ? $updated++ : $added++;
    }
    fclose($handle);

    // Save back to the district files
    $saved = enx_save_location_data($data);
    return "Panchayat import complete: <strong>{$added} added</strong>, <strong>{$updated} updated</strong>".($errors?", {$errors} skipped":'').". {$saved} district file(s) saved.";
}

function enx_import_zp_wards_csv($handle,$col) {
    $added=0; $updated=0; $errors=0;
    $data = enx_get_location_data();
    while(($row=fgetcsv($handle))!==false) {
        $g = function($key) use($row,$col){ return isset($col[$key],$row[$col[$key]]) ? trim($row[$col[$key]]) : ''; };
        $ds  = sanitize_title($g('district_slug'));
        $ws  = sanitize_title($g('ward_slug'));
        $wen = $g('ward_en');
        $whi = $g('ward_hi') ?: $wen;
        if(!$ds||!$ws||!$wen) { $errors++; continue; }
        if(!isset($data[$ds])) $data[$ds]=['label_en'=>ucwords(str_replace('-',' ',$ds)),'label_hi'=>'','blocks'=>[],'zp_wards'=>[]];
        if(!isset($data[$ds]['zp_wards'])) $data[$ds]['zp_wards']=[];
        $exists=isset($data[$ds]['zp_wards'][$ws]);
        $data[$ds]['zp_wards'][$ws]=['label_en'=>$wen,'label_hi'=>$whi];
        $exists?$updated++:$added++;
    }
    fclose($handle);
    $saved=enx_save_location_data($data);
    return "ZP Wards import: <strong>{$added} added</strong>, <strong>{$updated} updated</strong>".($errors?", {$errors} skipped":'').". {$saved} file(s) saved.";
}

function enx_import_bdc_wards_csv($handle,$col) {
    $added=0; $updated=0; $errors=0;
    $data = enx_get_location_data();
    while(($row=fgetcsv($handle))!==false) {
        $g = function($key) use($row,$col){ return isset($col[$key],$row[$col[$key]]) ? trim($row[$col[$key]]) : ''; };
        $ds  = sanitize_title($g('district_slug'));
        $bs  = sanitize_title($g('block_slug'));
        $ws  = sanitize_title($g('ward_slug'));
        $wen = $g('ward_en');
        $whi = $g('ward_hi') ?: $wen;
        if(!$ds||!$bs||!$ws||!$wen) { $errors++; continue; }
        if(!isset($data[$ds]['blocks'][$bs])) { $errors++; continue; }
        if(!isset($data[$ds]['blocks'][$bs]['bdc_wards'])) $data[$ds]['blocks'][$bs]['bdc_wards']=[];
        $exists=isset($data[$ds]['blocks'][$bs]['bdc_wards'][$ws]);
        $data[$ds]['blocks'][$bs]['bdc_wards'][$ws]=['label_en'=>$wen,'label_hi'=>$whi];
        $exists?$updated++:$added++;
    }
    fclose($handle);
    $saved=enx_save_location_data($data);
    return "BDC Wards import: <strong>{$added} added</strong>, <strong>{$updated} updated</strong>".($errors?", {$errors} skipped":'').". {$saved} file(s) saved.";
}

function enx_import_ulb_csv($handle,$col) {
    $added=0; $updated=0; $errors=0;
    $data = enx_get_ulb_data();

    while(($row=fgetcsv($handle))!==false) {
        $g = function($key) use($row,$col){ return isset($col[$key],$row[$col[$key]]) ? trim($row[$col[$key]]) : ''; };
        $ds  = sanitize_title($g('district_slug'));
        $den = $g('district_en') ?: enx_labelize($ds);
        $dhi = $g('district_hi') ?: $den;
        $us  = sanitize_title($g('ulb_slug'));
        $uen = $g('ulb_en');
        $uhi = $g('ulb_hi') ?: $uen;
        $ut_raw = $g('ulb_type') ?: 'municipal_council';
        $ut_map = ['municipal corporation'=>'municipal_corporation','municipal council'=>'municipal_council','nagar panchayat'=>'nagar_panchayat'];
        $ut = isset($ut_map[strtolower(trim($ut_raw))]) ? $ut_map[strtolower(trim($ut_raw))] : sanitize_title($ut_raw);

        if(!$ds||!$us||!$uen) { $errors++; continue; }
        if(!isset($data[$ds])) $data[$ds]=['label_en'=>$den,'label_hi'=>$dhi,'ulbs'=>[]];
        $exists=isset($data[$ds]['ulbs'][$us]);
        if(!$exists) $data[$ds]['ulbs'][$us]=['label_en'=>$uen,'label_hi'=>$uhi,'ulb_type'=>$ut,'wards'=>[]];
        else { $data[$ds]['ulbs'][$us]['label_en']=$uen; $data[$ds]['ulbs'][$us]['label_hi']=$uhi; $data[$ds]['ulbs'][$us]['ulb_type']=$ut; }
        // Ward columns
        $wss = sanitize_title($g('ward_slug'));
        $wen2= $g('ward_en');
        $whi2= $g('ward_hi') ?: $wen2;
        if($wss&&$wen2){
            if(!isset($data[$ds]['ulbs'][$us]['wards'])) $data[$ds]['ulbs'][$us]['wards']=[];
            $data[$ds]['ulbs'][$us]['wards'][$wss]=['label_en'=>$wen2,'label_hi'=>$whi2];
        }
        $exists?$updated++:$added++;
    }
    fclose($handle);
    $saved = enx_save_ulb_data($data);
    return "ULB import complete: <strong>{$added} added</strong>, <strong>{$updated} updated</strong>".($errors?", {$errors} skipped":'').". Saved.";
}

function enx_import_assembly_csv($handle,$col) {
    $added=0; $updated=0; $errors=0;
    $data = function_exists('enx_get_assembly_data') ? enx_get_assembly_data() : [];

    while(($row=fgetcsv($handle))!==false) {
        $g = function($key) use($row,$col){ return isset($col[$key],$row[$col[$key]]) ? trim($row[$col[$key]]) : ''; };
        $cs  = sanitize_title($g('constituency_slug'));
        $cen = $g('constituency_en');
        $chi = $g('constituency_hi') ?: $cen;
        $num = (int)$g('constituency_number');

        if(!$cs||!$cen) { $errors++; continue; }
        $exists=isset($data[$cs]);
        $data[$cs]=['label_en'=>$cen,'label_hi'=>$chi,'number'=>$num];
        $exists?$updated++:$added++;
    }
    fclose($handle);
    enx_save_assembly_data($data);
    return "Assembly import complete: <strong>{$added} added</strong>, <strong>{$updated} updated</strong>".($errors?", {$errors} skipped":'').".";
}

/* ── Data save functions — write back to PHP data files ────────────────── */
function enx_save_location_data($data) {
    $saved = 0;
    foreach($data as $district_slug => $dd) {
        if($district_slug==='zp_wards') continue;
        $file = ENX_DIR.'location/data/district-'.$district_slug.'.php';
        // Only write if file exists or district has data
        $content = "<?php\nif(!defined('ABSPATH'))exit;\nreturn [\n";
        $content .= "    '".addslashes($district_slug)."' => [\n";
        $content .= "        'label_en' => '".addslashes($dd['label_en']??'')."',\n";
        $content .= "        'label_hi' => '".str_replace("'","\'",$dd['label_hi']??'')."',\n";
        $content .= "        'blocks' => [\n";
        foreach(($dd['blocks']??[]) as $bs=>$bk){
            $content .= "            '".addslashes($bs)."' => [\n";
            $content .= "                'label_en' => '".addslashes($bk['label_en']??'')."',\n";
            $content .= "                'label_hi' => '".str_replace("'","\'",$bk['label_hi']??'')."',\n";
            $content .= "                'panchayats' => [\n";
            foreach(($bk['panchayats']??[]) as $ps=>$pv){
                $pen=is_array($pv)?($pv['label_en']??$ps):$pv;
                $phi=is_array($pv)?($pv['label_hi']??$pen):$pv;
                $content .= "                    '".addslashes($ps)."' => ['label_en'=>'".addslashes($pen)."','label_hi'=>'".str_replace("'","\'",$phi)."'],\n";
            }
            // BDC wards for this block
            if(!empty($bk['bdc_wards'])){
                $content .= "                'bdc_wards' => [\n";
                foreach(($bk['bdc_wards']??[]) as $ws=>$wv2){
                    $we=is_array($wv2)?($wv2['label_en']??$ws):$wv2;
                    $wh=is_array($wv2)?($wv2['label_hi']??$we):$wv2;
                    $content .= "                    '".addslashes($ws)."' => ['label_en'=>'".addslashes($we)."','label_hi'=>'".str_replace("'","\'",$wh)."'],\n";
                }
                $content .= "                ],\n";
            }
            $content .= "                ],\n            ],\n";
        }
        $content .= "        ],\n";
        // Preserve ZP wards if any
        if(!empty($dd['zp_wards'])){
            $content .= "        'zp_wards' => [\n";
            foreach($dd['zp_wards'] as $ws=>$wv){
                $wen=is_array($wv)?($wv['label_en']??$ws):$wv;
                $whi=is_array($wv)?($wv['label_hi']??$wen):$wv;
                $content .= "            '".addslashes($ws)."' => ['label_en'=>'".addslashes($wen)."','label_hi'=>'".str_replace("'","\'",$whi)."'],\n";
            }
            $content .= "        ],\n";
        }
        $content .= "    ],\n];\n";
        file_put_contents($file,$content);
        $saved++;
    }
    return $saved;
}

function enx_save_ulb_data($data) {
    $file = ENX_DIR.'location/data/ulb-data.php';
    // Write a pure return-array file.
    // class-location.php defines enx_get_ulb_data() which includes this file via `include`.
    // Re-declaring the function here causes "Cannot redeclare" fatal errors.
    $out = "<?php\nif(!defined('ABSPATH'))exit;\n\n// Auto-generated by Location Import. Do not edit manually.\nreturn [\n";
    foreach ( $data as $ds => $dd ) {
        $den = addslashes($dd['label_en'] ?? '');
        $dhi = str_replace("'","\\'",$dd['label_hi'] ?? '');
        $out .= "    '".addslashes($ds)."' => [\n";
        $out .= "        'label_en' => '".$den."',\n";
        $out .= "        'label_hi' => '".$dhi."',\n";
        $out .= "        'ulbs' => [\n";
        foreach ( ($dd['ulbs'] ?? []) as $us => $uv ) {
            $uen = addslashes($uv['label_en'] ?? '');
            $uhi = str_replace("'","\\'",$uv['label_hi'] ?? '');
            $utp = addslashes($uv['ulb_type'] ?? 'municipal_council');
            $out .= "            '".addslashes($us)."' => ['label_en'=>'".$uen."','label_hi'=>'".$uhi."','ulb_type'=>'".$utp."','wards' => [\n";
            foreach ( ($uv['wards'] ?? []) as $ws => $wv ) {
                $wen = addslashes($wv['label_en'] ?? '');
                $whi = str_replace("'","\\'",$wv['label_hi'] ?? '');
                $out .= "                '".addslashes($ws)."' => ['label_en'=>'".$wen."','label_hi'=>'".$whi."'],\n";
            }
            $out .= "            ]],\n";
        }
        $out .= "        ],\n    ],\n";
    }
    $out .= "];\n";
    file_put_contents($file, $out);
}

function enx_save_assembly_data($data) {
    $file = ENX_DIR.'location/data/assembly-data.php';
    // Write a pure return-array file (no function declarations)
    $out = "<?php\nif(!defined('ABSPATH'))exit;\n\n// Auto-generated by Location Import.\nreturn [\n";
    foreach ( $data as $cs => $cv ) {
        $cen = addslashes($cv['label_en'] ?? '');
        $chi = str_replace("'","\\'",$cv['label_hi'] ?? '');
        $num = intval($cv['number'] ?? 0);
        $out .= "    '".addslashes($cs)."' => ['label_en'=>'".$cen."','label_hi'=>'".$chi."','number'=>".$num."],\n";
    }
    $out .= "];\n";
    file_put_contents($file, $out);
}
