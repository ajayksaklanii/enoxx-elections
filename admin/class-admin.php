<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function() {
    add_menu_page('ENOXX Elections','ENOXX Elections','edit_posts','enx-elections','enx_page_dashboard','dashicons-groups',26);
    add_submenu_page('enx-elections','All Candidates','All Candidates','edit_posts','enx-elections','enx_page_dashboard');
    add_submenu_page('enx-elections','Add Candidate','+ Add Candidate','edit_posts','enx-add-candidate','enx_page_add_candidate');
    if ( current_user_can('manage_options') ) {
        add_submenu_page('enx-elections','Location Data','Location Data','manage_options','enx-location-check','enx_page_location_check');
        add_submenu_page('enx-elections','Sync to Hindi','Sync to Hindi','manage_options','enx-sync','enx_page_sync');
    }
}, 10);

add_action('admin_head', function() {
    if ( ! isset($_GET['page']) || strpos($_GET['page'],'enx-') === false ) return; ?>
<style>
.enx-wrap{max-width:1280px}
.enx-panel{background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:22px;margin-bottom:18px}
.enx-panel h2{margin:0 0 14px;font-size:14px;font-weight:700;color:#1e1e1e;border-bottom:2px solid #f59e0b;padding-bottom:7px}
.enx-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.enx-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
.enx-f{display:flex;flex-direction:column;gap:4px}
.enx-f label{font-size:11px;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.3px}
.enx-f input,.enx-f textarea,.enx-f select{width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;box-sizing:border-box}
.enx-f textarea{resize:vertical;min-height:80px}
.enx-full{grid-column:1/-1}
.enx-btn{display:inline-flex;align-items:center;gap:5px;padding:8px 16px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;border:none;text-decoration:none;white-space:nowrap}
.enx-btn-primary{background:#f59e0b;color:#fff}.enx-btn-primary:hover{background:#d97706;color:#fff}
.enx-btn-dark{background:#112b4a;color:#fff}.enx-btn-dark:hover{background:#0e2338;color:#fff}
.enx-btn-green{background:#059669;color:#fff}.enx-btn-green:hover{background:#047857;color:#fff}
.enx-btn-danger{background:#dc2626;color:#fff}.enx-btn-danger:hover{background:#b91c1c;color:#fff}
.enx-tbl{width:100%;border-collapse:collapse;font-size:12px}
.enx-tbl th{background:#f4f4f4;padding:9px 10px;text-align:left;font-weight:700;border-bottom:2px solid #e0e0e0;white-space:nowrap}
.enx-tbl td{padding:9px 10px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
.enx-tbl tr:hover td{background:#fafafa}
.enx-badge{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700}
.enx-badge-premium{background:#fef3c7;color:#92400e}
.enx-badge-basic{background:#f3f4f6;color:#555}
.enx-badge-panchayat{background:#dbeafe;color:#1e40af}
.enx-badge-ulb{background:#fce7f3;color:#9d174d}
.enx-badge-assembly{background:#d1fae5;color:#065f46}
.enx-badge-publish{background:#d1fae5;color:#065f46}
.enx-badge-pending{background:#fef3c7;color:#92400e}
.enx-badge-draft{background:#f3f4f6;color:#6b7280}
.enx-tier-toggle{display:flex}
.enx-tier-toggle input[type=radio]{display:none}
.enx-tier-toggle label{padding:7px 14px;border:1px solid #ddd;cursor:pointer;font-size:12px;font-weight:600;background:#f8f8f8}
.enx-tier-toggle label:first-of-type{border-radius:6px 0 0 6px}
.enx-tier-toggle label:last-of-type{border-radius:0 6px 6px 0}
.enx-tier-toggle input:checked+label{background:#f59e0b;color:#fff;border-color:#f59e0b}
.enx-photo-preview{width:120px;height:150px;border-radius:10px;border:2px solid #eee;object-fit:cover;display:block;margin-bottom:10px}
.enx-section-divider{grid-column:1/-1;border:none;border-top:1px solid #e8e3d7;margin:10px 0}
.enx-subsection{grid-column:1/-1;background:#f9f8f5;border:1px solid #eee7d9;border-radius:8px;padding:14px;display:grid;grid-template-columns:1fr 1fr;gap:12px}
</style>
    <?php
});

function enx_page_dashboard() {
    $is_admin = current_user_can('administrator') || current_user_can('editor');

    $counts    = wp_count_posts('candidate');
    $published = (int)($counts->publish ?? 0);
    $pending   = (int)($counts->pending ?? 0);
    $draft     = (int)($counts->draft   ?? 0);
    $premium   = (new WP_Query(['post_type'=>'candidate','posts_per_page'=>-1,'fields'=>'ids','meta_query'=>[['key'=>'profile_tier_text','value'=>'premium']]]))->found_posts;

    $filter_status   = sanitize_text_field($_GET['status']       ?? '');
    $filter_election = sanitize_text_field($_GET['election_type']?? '');
    $filter_district = sanitize_text_field($_GET['district']     ?? '');
    $filter_tier     = sanitize_text_field($_GET['tier']         ?? '');
    $filter_s        = sanitize_text_field($_GET['s']            ?? '');
    $paged           = max(1,(int)($_GET['paged']??1));

    $post_statuses = ['publish','pending','draft'];
    if ($filter_status && in_array($filter_status,$post_statuses)) $post_statuses = [$filter_status];
    $author_id = 0;
    if (!$is_admin) { $author_id=get_current_user_id(); $post_statuses=['pending','draft']; }

    $meta_query = ['relation'=>'AND'];
    if ($filter_election) $meta_query[]=['key'=>'election_type','value'=>$filter_election,'compare'=>'='];
    if ($filter_district) $meta_query[]=['key'=>'district_slug','value'=>$filter_district,'compare'=>'='];
    if ($filter_tier)     $meta_query[]=['key'=>'profile_tier_text','value'=>$filter_tier,'compare'=>'='];

    $q_args = ['post_type'=>'candidate','post_status'=>$post_statuses,'posts_per_page'=>20,'paged'=>$paged,'meta_query'=>$meta_query,'orderby'=>'date','order'=>'DESC'];
    if ($filter_s)  $q_args['s']      = $filter_s;
    if ($author_id) $q_args['author'] = $author_id;
    $q = new WP_Query($q_args);
    $base_url = admin_url('admin.php?page=enx-elections');
    $nonce = wp_create_nonce('enx_admin');
    ?>
    <div class="wrap enx-wrap">
        <h1 style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
            <span style="background:#112b4a;color:#fff;padding:5px 14px;border-radius:8px;font-size:13px">ENOXX Elections</span>
            <?php echo $is_admin ? 'All Candidates' : 'My Submissions' ?>
            <a href="<?php echo admin_url('admin.php?page=enx-add-candidate') ?>" class="enx-btn enx-btn-primary" style="margin-left:auto">+ Add Candidate</a>
            <?php if ($is_admin): ?>
            <a href="<?php echo admin_url('admin.php?page=enx-import-export') ?>" class="enx-btn enx-btn-dark">↕ Import/Export</a>
            <?php endif ?>
        </h1>

        <?php if (!$is_admin): ?>
        <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px">
            ℹ️ Your submissions are reviewed by admin before publishing. You can add and edit pending submissions.
        </div>
        <?php endif ?>

        <?php if ($is_admin): ?>
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:18px">
            <?php foreach([['Total',$published+$pending+$draft,'#112b4a',''],['Published',$published,'#065f46','status=publish'],['Pending',$pending,'#92400e','status=pending'],['Draft',$draft,'#6b7280','status=draft'],['Premium',$premium,'#d97706','tier=premium']] as [$l,$n,$c,$p]): ?>
            <a href="<?php echo esc_url($base_url.($p?"&$p":'')); ?>" style="text-decoration:none">
                <div style="text-align:center;padding:14px;background:#f8f9fa;border-radius:8px;border:1px solid #e8e3d7;border-left:3px solid <?php echo $c ?>">
                    <span style="font-size:28px;font-weight:800;color:<?php echo $c ?>;display:block"><?php echo $n ?></span>
                    <span style="font-size:12px;color:#666"><?php echo $l ?></span>
                </div>
            </a>
            <?php endforeach ?>
        </div>
        <?php endif ?>

        <!-- Filters -->
        <div class="enx-panel" style="padding:12px 16px;margin-bottom:14px">
            <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <input type="hidden" name="page" value="enx-elections">
                <input type="text" name="s" placeholder="🔍 Search..." value="<?php echo esc_attr($filter_s) ?>" style="padding:7px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px">
                <?php if ($is_admin): ?>
                <select name="status" style="padding:7px;border:1px solid #ddd;border-radius:6px;font-size:13px">
                    <option value="">All Status</option>
                    <option value="publish"<?php selected($filter_status,'publish') ?>>Published</option>
                    <option value="pending"<?php selected($filter_status,'pending') ?>>Pending</option>
                    <option value="draft"<?php selected($filter_status,'draft') ?>>Draft</option>
                </select>
                <?php endif ?>
                <select name="election_type" style="padding:7px;border:1px solid #ddd;border-radius:6px;font-size:13px">
                    <option value="">All Types</option>
                    <option value="panchayat"<?php selected($filter_election,'panchayat') ?>>Panchayat</option>
                    <option value="ulb"<?php selected($filter_election,'ulb') ?>>ULB</option>
                    <option value="assembly"<?php selected($filter_election,'assembly') ?>>Assembly</option>
                </select>
                <select name="district" style="padding:7px;border:1px solid #ddd;border-radius:6px;font-size:13px">
                    <option value="">All Districts</option>
                    <?php foreach(array_keys(enx_get_location_data()) as $d): ?>
                    <option value="<?php echo esc_attr($d) ?>"<?php selected($filter_district,$d) ?>><?php echo esc_html(enx_district_label($d)?:enx_labelize($d)) ?></option>
                    <?php endforeach ?>
                </select>
                <select name="tier" style="padding:7px;border:1px solid #ddd;border-radius:6px;font-size:13px">
                    <option value="">All Tiers</option>
                    <option value="premium"<?php selected($filter_tier,'premium') ?>>Premium</option>
                    <option value="basic"<?php selected($filter_tier,'basic') ?>>Basic</option>
                </select>
                <button type="submit" class="enx-btn enx-btn-dark">Filter</button>
                <a href="<?php echo $base_url ?>" style="font-size:12px;color:#666">Clear</a>
            </form>
        </div>

        <!-- Table -->
        <div class="enx-panel" style="padding:0;overflow:hidden">
        <?php if ($q->have_posts()): ?>
        <table class="enx-tbl">
            <thead><tr>
                <th>Photo</th><th>Name</th><th>Position</th><th>Location</th>
                <th>📱 Phone</th><th>Call Status</th><th>Type</th><th>Tier</th><th>Status</th>
                <th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php while($q->have_posts()): $q->the_post(); $pid=get_the_ID();
                $name    = get_post_meta($pid,'candidate_name_text',true) ?: get_the_title($pid);
                $contest = enx_get_contest_label_for_post($pid,'en');
                $et      = get_post_meta($pid,'election_type',true) ?: 'panchayat';
                $tier    = get_post_meta($pid,'profile_tier_text',true) ?: 'basic';
                $status  = get_post_status($pid);
                $phone   = get_post_meta($pid,'phone_text',true);
                $loc     = enx_resolve_location($pid);
                if($et==='assembly')      $location=$loc['constituency']?:enx_labelize($loc['constituency_slug']);
                elseif($et==='ulb')       $location=implode(', ',array_filter([$loc['ward'],$loc['ulb']]));
                else                      $location=implode(', ',array_filter([$loc['panchayat'],$loc['block'],$loc['district']]));
                $photo    = enx_photo_url_raw($pid,'thumbnail');
                $edit_url = admin_url('admin.php?page=enx-add-candidate&id='.$pid);
                $sl = ['publish'=>'Published','pending'=>'Pending Review','draft'=>'Draft'];
            ?>
            <tr>
                <td><img src="<?php echo esc_url($photo) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #eee"></td>
                <td>
                    <strong><a href="<?php echo esc_url($edit_url) ?>"><?php echo esc_html($name) ?></a></strong>
                    <?php $sub=get_post_meta($pid,'submitted_by',true); if($sub&&$is_admin) echo '<br><small style="color:#999">'.esc_html($sub).'</small>'; ?>
                </td>
                <td style="max-width:120px"><?php echo esc_html($contest) ?></td>
                <td style="max-width:140px;font-size:11px;color:#555"><?php echo esc_html($location) ?></td>
                <td><?php if($phone): ?><a href="tel:<?php echo esc_attr($phone) ?>" style="font-weight:700;color:#059669;white-space:nowrap">📱<?php echo esc_html($phone) ?></a><?php else: echo '<span style="color:#ccc">—</span>'; endif ?></td>
                <?php
                $cs=get_post_meta($pid,'enx_comm_status',true)?:'';
                $cd=get_post_meta($pid,'enx_comm_date',true)?:'';
                $csch=get_post_meta($pid,'enx_comm_schedule',true)?:'';
                $cs_map=[''=>['—','#ccc'],'to_call'=>['📞 To Call','#f59e0b'],'called'=>['✅ Called','#059669'],'no_answer'=>['🔇 No Ans','#dc2626'],'scheduled'=>['📅 Sched.','#3b82f6'],'converted'=>['⭐ Premium','#d97706'],'not_interested'=>['❌ Not Int.','#9ca3af'],'call_back'=>['🔁 Call Back','#8b5cf6']];
                $ci=$cs_map[$cs]??['?','#888'];
                ?>
                <td style="white-space:nowrap;font-size:11px">
                    <span style="color:<?php echo $ci[1] ?>;font-weight:700"><?php echo $ci[0] ?></span>
                    <?php if($cd): ?><br><span style="color:#aaa;font-size:10px"><?php echo date('j M',strtotime($cd)) ?></span><?php endif ?>
                    <?php if($csch): ?><br><span style="color:#3b82f6;font-size:10px">📅<?php echo date('j M',strtotime($csch)) ?></span><?php endif ?>
                </td>
                <td><span class="enx-badge enx-badge-<?php echo esc_attr($et) ?>"><?php echo esc_html(ucfirst($et)) ?></span></td>
                <td><span class="enx-badge enx-badge-<?php echo esc_attr($tier) ?>"><?php echo esc_html(ucfirst($tier)) ?></span></td>
                <td><span class="enx-badge enx-badge-<?php echo esc_attr($status) ?>"><?php echo esc_html($sl[$status]??ucfirst($status)) ?></span></td>
                <td style="white-space:nowrap;color:#888"><?php echo get_the_date('j M Y',$pid) ?></td>
                <td style="white-space:nowrap">
                    <a href="<?php echo esc_url($edit_url) ?>" class="enx-btn enx-btn-dark" style="padding:4px 10px">Edit</a>
                    <?php if ($is_admin): ?>
                        <?php if($status==='pending'): ?>
                        <button class="enx-btn enx-btn-green enx-publish-btn" data-id="<?php echo $pid ?>" data-nonce="<?php echo $nonce ?>" style="padding:4px 10px">✓ Publish</button>
                        <?php endif ?>
                        <?php if($status==='publish'): ?>
                        <a href="<?php echo esc_url(get_permalink($pid)) ?>" target="_blank" class="enx-btn" style="padding:4px 10px;background:#e0e7ff;color:#3730a3;font-weight:700">View</a>
                        <button class="enx-btn enx-btn-primary enx-sync-btn" data-id="<?php echo $pid ?>" data-nonce="<?php echo $nonce ?>" style="padding:4px 10px">Sync→HI</button>
                        <?php endif ?>
                        <a href="<?php echo esc_url(get_delete_post_link($pid)) ?>" onclick="return confirm('Delete candidate?')" class="enx-btn enx-btn-danger" style="padding:4px 10px">Del</a>
                    <?php endif ?>
                </td>
            </tr>
            <?php endwhile; wp_reset_postdata(); ?>
            </tbody>
        </table>
        <?php if($q->max_num_pages>1): ?>
        <div style="padding:12px 16px;display:flex;gap:8px;flex-wrap:wrap">
            <?php for($i=1;$i<=$q->max_num_pages;$i++):
                $pu=add_query_arg(['paged'=>$i,'page'=>'enx-elections'],admin_url('admin.php'));
                if($filter_status)   $pu=add_query_arg('status',$filter_status,$pu);
                if($filter_election) $pu=add_query_arg('election_type',$filter_election,$pu);
                $a=($i==$paged)?'background:#112b4a;color:#fff;':'background:#f0f0f0;color:#333;';
            ?>
            <a href="<?php echo esc_url($pu) ?>" style="padding:5px 11px;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;<?php echo $a ?>"><?php echo $i ?></a>
            <?php endfor ?>
        </div>
        <?php endif ?>
        <?php else: ?>
        <div style="padding:32px;text-align:center;color:#888">No candidates found.</div>
        <?php endif ?>
        </div>
    </div>
    <script>
    document.querySelectorAll('.enx-sync-btn').forEach(btn=>{
        btn.addEventListener('click',function(){
            const id=this.dataset.id,n=this.dataset.nonce;
            this.disabled=true;this.textContent='Syncing...';
            fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:new URLSearchParams({action:'enx_sync_single',post_id:id,nonce:n})
            }).then(r=>r.json()).then(d=>{
                this.textContent=d.success?'✓ Synced':'✗ Failed';
                this.style.background=d.success?'#059669':'#dc2626';
            }).catch(()=>{this.textContent='Error';this.disabled=false;});
        });
    });
    document.querySelectorAll('.enx-publish-btn').forEach(btn=>{
        btn.addEventListener('click',function(){
            const id=this.dataset.id,n=this.dataset.nonce;
            this.disabled=true;this.textContent='Publishing...';
            fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:new URLSearchParams({action:'enx_publish_candidate',post_id:id,nonce:n})
            }).then(r=>r.json()).then(d=>{
                if(d.success){this.textContent='✓ Published';this.style.background='#059669';setTimeout(()=>location.reload(),600);}
                else{this.textContent='Failed';this.disabled=false;}
            });
        });
    });
    </script>
    <?php
}

add_action('wp_ajax_enx_publish_candidate',function(){
    if(!wp_verify_nonce($_POST['nonce']??'','enx_admin')) wp_send_json_error('nonce');
    if(!current_user_can('administrator')&&!current_user_can('editor')) wp_send_json_error('permission');
    $id=absint($_POST['post_id']??0);
    if(!$id||get_post_type($id)!=='candidate') wp_send_json_error('invalid');
    wp_update_post(['ID'=>$id,'post_status'=>'publish']);
    wp_send_json_success(['message'=>'Published']);
});

function enx_page_location_check() {
    $loc=enx_get_location_data(); $ulb=enx_get_ulb_data(); $asm=enx_get_assembly_constituencies();
    echo '<div class="wrap"><h1>Location Data Status</h1>';
    echo '<div class="enx-panel"><h2>Districts ('.count($loc).')</h2>';
    echo '<div class="enx-grid-3" style="gap:10px">';
    foreach($loc as $slug=>$d){
        $b=count($d['blocks']??[]);
        $p=array_sum(array_map(function($bk){ return count(isset($bk['panchayats'])?$bk['panchayats']:[]); }, isset($d['blocks'])?$d['blocks']:[]));
        echo '<div style="background:#d1fae5;border-left:3px solid #059669;padding:8px 12px;border-radius:6px;font-size:12px"><strong>'.esc_html($d['label_en']??$slug).'</strong><br><small>'.$b.' blocks · '.$p.' GPs</small></div>';
    }
    echo '</div></div>';
    echo '<div class="enx-panel"><h2>ULBs ('.array_sum(array_map(function($dv){ return count(isset($dv['ulbs'])?$dv['ulbs']:[]); }, $ulb)).' across '.count($ulb).' districts)</h2><div class="enx-grid-3" style="gap:10px">';
    foreach($ulb as $slug=>$d){ echo '<div style="background:#fce7f3;border-left:3px solid #9d174d;padding:8px 12px;border-radius:6px;font-size:12px"><strong>'.esc_html($d['label_en']??$slug).'</strong><br><small>'.count($d['ulbs']??[]).' ULBs</small></div>'; }
    echo '</div></div>';
    echo '<div class="enx-panel"><h2>Assembly ('.count($asm).' constituencies)</h2><div style="columns:3;font-size:12px;line-height:2">';
    foreach($asm as $slug=>$c) echo '<div>'.esc_html($c['label_en']).'</div>';
    echo '</div></div></div>';
}

function enx_page_sync() { ?>
<div class="wrap enx-wrap"><h1>Sync Candidates → Hindi Site</h1>
<div class="enx-panel">
    <p style="font-size:13px">Use Sync→HI on individual candidates from the list, or sync all published candidates at once.</p>
    <button id="enx-bulk-sync" class="enx-btn enx-btn-primary button-large">Bulk Sync All Published</button>
    <div id="enx-bulk-status" style="margin-top:12px;font-size:13px"></div>
</div></div>
<script>
document.getElementById('enx-bulk-sync').addEventListener('click',function(){
    this.disabled=true;this.textContent='Syncing...';
    const s=document.getElementById('enx-bulk-status');s.innerHTML='Working...';
    fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({action:'enx_bulk_sync',nonce:'<?php echo wp_create_nonce("enx_sync") ?>'})
    }).then(r=>r.json()).then(d=>{ s.innerHTML=d.message||'Done'; document.getElementById('enx-bulk-sync').textContent='Bulk Sync'; document.getElementById('enx-bulk-sync').disabled=false; });
});
</script>
<?php
}
