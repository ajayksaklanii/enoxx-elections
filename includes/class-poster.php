<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('init',function(){add_rewrite_rule('^candidate-poster/?$','index.php?enx_poster=1','top');add_rewrite_tag('%enx_poster%','1');});
add_filter('query_vars',function($v){$v[]='enx_poster';return $v;});
add_action('template_redirect',function(){if(get_query_var('enx_poster')){enx_render_poster();exit;}});

function enx_get_poster_template_url($contest_slug,$site='en',$post_id=0){
    // 1. Per-post override (set individually in candidate edit form)
    if($post_id){
        $post_url=get_post_meta($post_id,'enx_post_poster_tpl_'.$site,true);
        if($post_url) return esc_url($post_url);
    }
    // 2. Per-position global setting
    $pos_url=get_option('enx_poster_tpl_'.$site.'_'.$contest_slug);
    if($pos_url) return esc_url($pos_url);
    // 3. Base template
    return esc_url(get_option('enx_poster_template_'.$site,''));
}

function enx_render_poster(){
    $id   = absint($_GET['candidate_id']??0);
    $post = $id?get_post($id):null;
    if(!$post||$post->post_type!=='candidate') wp_die('Candidate not found.');
    $tier = strtolower(get_post_meta($id,'profile_tier_text',true));
    if($tier!=='premium') wp_die('Poster available for premium candidates only.');

    $lang  = ENX_IS_HI ? 'hi' : 'en';
    $name  = ENX_IS_HI ? (get_post_meta($id,'candidate_name_hi',true)?:get_post_meta($id,'candidate_name_text',true)) : get_post_meta($id,'candidate_name_text',true);
    if(!$name) $name=get_the_title($id);

    $contest_slug = get_post_meta($id,'contest_slug',true);
    $theme        = enx_poster_theme($contest_slug);
    $et           = get_post_meta($id,'election_type',true)?:'panchayat';
    $contest_label= enx_get_contest_label_for_post($id,$lang);
    $loc          = enx_resolve_location($id,$lang);

    $c_slug_p = get_post_meta($id,'contest_slug',true);
    $is_zp_p  = in_array($c_slug_p,['zila-parishad','zila-parishad-member'],true);
    $is_bdc_p = in_array($c_slug_p,['panchayat-samiti','panchayat-samiti-bdc','panchayat-samiti-member','panchayat-samiti-member-bdc','bdc-member'],true);

    if($et==='ulb'){
        $highlight=$loc['ward']?:$loc['ulb'];
        $area_line=implode(' • ',array_filter([$loc['ulb'],$loc['district']]));
    }elseif($et==='assembly'){
        $highlight=$loc['constituency'];
        $area_line=$loc['district'];
    }elseif($is_zp_p){
        // Zila Parishad: show ZP Ward name, only district on location line
        $zp_wd=get_post_meta($id,'zp_ward_text',true)?:$loc['panchayat'];
        $highlight=$zp_wd;
        $area_line=$loc['district'];
    }elseif($is_bdc_p){
        // BDC poster: ward = panchayat selected in form
        $p_sl_bdc  = (string) get_post_meta($id,'panchayat_slug',true);
        $p_sl_text = $p_sl_bdc ? enx_labelize($p_sl_bdc) : '';
        $b_txt     = (string)($loc['block'] ?: get_post_meta($id,'block_text',true));
        $d_txt     = (string)($loc['district'] ?: get_post_meta($id,'district_text',true));

        if ( ($v = trim((string) get_post_meta($id,'bdc_ward_text',true))) !== '' ) {
            $bdc_w = $v;
        } elseif ( ($v = trim((string) get_post_meta($id,'panchayat_text',true))) !== '' ) {
            $bdc_w = $v;
        } elseif ( ($v = trim((string) $loc['panchayat'])) !== '' ) {
            $bdc_w = $v;
        } elseif ( $p_sl_text !== '' ) {
            $bdc_w = $p_sl_text;
        } elseif ( $b_txt !== '' ) {
            $bdc_w = $b_txt;
        } else {
            $bdc_w = '';
        }

        $highlight = $bdc_w !== '' ? $bdc_w.($lang==='hi'?' - पंचायत समिति वार्ड':' - Panchayat Samiti Ward') : '';
        $area_line = implode(' • ', array_filter([$b_txt, $d_txt]));
    }else{
        // Pradhan/Up-Pradhan/Ward Member
        $highlight=$loc['panchayat'];
        $area_line=implode(' • ',array_filter([$loc['block'],$loc['district']]));
        if($highlight) $highlight.=($lang==='hi'?' पंचायत':' Panchayat');
    }

    $template_img = enx_get_poster_template_url($contest_slug,$lang,$id);
    $photo        = enx_photo_url_raw($id,'full');
    if(!$photo) $photo='https://via.placeholder.com/600x600/cccccc/666666?text=Photo';

    // Font settings from admin
    $font_name    = esc_attr(get_option('enx_poster_font_name','inherit'));
    $font_contest = esc_attr(get_option('enx_poster_font_contest','inherit'));
    $font_area    = esc_attr(get_option('enx_poster_font_area','inherit'));
    $font_loc     = esc_attr(get_option('enx_poster_font_loc','inherit'));
    $size_name    = max(20,min(120,(int)get_option('enx_poster_size_name',80)));
    $size_contest = max(12,min(80,(int)get_option('enx_poster_size_contest',34)));
    $size_area    = max(12,min(80,(int)get_option('enx_poster_size_area',36)));
    $size_loc     = max(10,min(60,(int)get_option('enx_poster_size_loc',26)));
    // Position settings
    $pos_name_top    = (int)get_option('enx_poster_pos_name_top',200);
    $pos_name_left   = (int)get_option('enx_poster_pos_name_left',58);
    $pos_name_width  = (int)get_option('enx_poster_pos_name_width',490);
    $pos_contest_top = (int)get_option('enx_poster_pos_contest_top',490);
    $pos_contest_left= (int)get_option('enx_poster_pos_contest_left',58);
    $pos_area_top    = (int)get_option('enx_poster_pos_area_top',598);
    $pos_area_left   = (int)get_option('enx_poster_pos_area_left',58);
    $pos_loc_top     = (int)get_option('enx_poster_pos_loc_top',706);
    $pos_loc_left    = (int)get_option('enx_poster_pos_loc_left',58);
    $lh_name         = esc_attr(get_option('enx_poster_lh_name','1.02'));
    $lh_contest      = esc_attr(get_option('enx_poster_lh_contest','1.2'));
    $lh_area         = esc_attr(get_option('enx_poster_lh_area','1.15'));

    $primary   = $theme['primary'];
    $secondary = $theme['secondary'];
    $back_url  = get_permalink($id);
    $badge_label = ENX_IS_HI ? (enx_contest_labels()[$contest_slug]['hi']??enx_labelize($contest_slug)) : (enx_contest_labels()[$contest_slug]['en']??enx_labelize($contest_slug));
    ?>
<!DOCTYPE html><html <?php language_attributes() ?>><head>
<meta charset="<?php bloginfo('charset') ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo ENX_IS_HI?'उम्मीदवार पोस्टर':'Candidate Poster' ?> — <?php echo esc_html($name) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;700;900&family=Tiro+Devanagari+Hindi&family=Baloo+2:wght@400;700;800&family=Hind:wght@400;700&family=Mukta:wght@400;700&family=Rajdhani:wght@400;700&family=Laila:wght@400;700&family=Yatra+One&family=Rozha+One&family=Karma:wght@400;700&family=Eczar:wght@400;700&family=Roboto:wght@400;700;900&family=Poppins:wght@400;700;900&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#f4f4f4;font-family:sans-serif;padding:18px}
.pw{max-width:1400px;margin:0 auto}
h1{font-size:22px;font-weight:800;margin-bottom:6px}
.badge{display:inline-block;background:<?php echo esc_attr($secondary) ?>;color:#fff;padding:4px 13px;border-radius:999px;font-size:12px;font-weight:800;margin-bottom:14px}
.topbar{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center}
.btn{display:inline-flex;align-items:center;padding:10px 18px;border-radius:9px;font-size:13px;font-weight:800;cursor:pointer;border:none;text-decoration:none}
.btn-dl{background:#f59e0b;color:#fff}.btn-sh{background:#0f172a;color:#fff}.btn-bk{background:#fff;color:#333;border:1px solid #ddd}
.stage{display:flex;justify-content:center;overflow-x:auto}
#poster{position:relative;width:1080px;height:1080px;min-width:1080px;min-height:1080px;overflow:hidden;border-radius:28px;background:<?php echo esc_attr($primary) ?>;box-shadow:0 20px 50px rgba(0,0,0,.15);flex:none}
.p-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:1;pointer-events:none}
.p-accent{position:absolute;left:0;top:0;bottom:0;width:13px;background:<?php echo esc_attr($secondary) ?>;z-index:20}
.p-photo{position:absolute;top:90px;right:46px;width:510px;height:510px;border-radius:50%;border:8px solid <?php echo esc_attr($primary) ?>;background-size:cover;background-position:center top;z-index:6;box-shadow:0 20px 40px rgba(0,0,0,.12)}
.p-name{position:absolute;left:<?php echo $pos_name_left ?>px;top:<?php echo $pos_name_top ?>px;width:<?php echo $pos_name_width ?>px;z-index:10;font-size:<?php echo $size_name ?>px;line-height:<?php echo $lh_name ?>;font-weight:900;color:#fff;word-break:break-word;font-family:<?php echo $font_name ?>}
.p-contest{position:absolute;left:<?php echo $pos_contest_left ?>px;top:<?php echo $pos_contest_top ?>px;width:<?php echo $pos_name_width ?>px;z-index:15;font-size:<?php echo $size_contest ?>px;font-weight:900;color:#fff;line-height:<?php echo $lh_contest ?>;font-family:<?php echo $font_contest ?>}
.p-area{position:absolute;left:<?php echo $pos_area_left ?>px;top:<?php echo $pos_area_top ?>px;width:<?php echo $pos_name_width ?>px;z-index:10;font-size:<?php echo $size_area ?>px;font-weight:900;color:<?php echo esc_attr($primary) ?>;line-height:<?php echo $lh_area ?>;word-break:break-word;font-family:<?php echo $font_area ?>}
.p-loc{position:absolute;left:<?php echo $pos_loc_left ?>px;top:<?php echo $pos_loc_top ?>px;width:<?php echo $pos_name_width ?>px;z-index:10;font-size:<?php echo $size_loc ?>px;font-weight:700;color:#000;font-family:<?php echo $font_loc ?>}
.p-preload{position:absolute;width:1px;height:1px;opacity:0;left:-9999px;top:-9999px}
</style></head><body>
<div class="pw">
<h1><?php echo ENX_IS_HI?'उम्मीदवार पोस्टर':'Candidate Poster' ?></h1>
<div class="badge"><?php echo esc_html($badge_label) ?> Theme</div>
<div class="topbar">
    <button class="btn btn-dl" onclick="dlPoster()"><?php echo ENX_IS_HI?'पोस्टर डाउनलोड':'Download Poster' ?></button>
    <button class="btn btn-sh" onclick="sharePoster()"><?php echo ENX_IS_HI?'पोस्टर शेयर':'Share Poster' ?></button>
    <a href="<?php echo esc_url($back_url) ?>" class="btn btn-bk"><?php echo ENX_IS_HI?'← वापस':'← Back' ?></a>
</div>
<?php if ( is_user_logged_in() && current_user_can('administrator') ): ?>
<div style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:18px;margin-bottom:18px;max-width:1100px">
    <strong style="font-size:14px;display:block;margin-bottom:12px">🎨 Admin: Live Poster Adjustments — <em style="font-weight:400;color:#888">Click Apply then Save to Settings</em></strong>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;align-items:end;margin-bottom:12px">
        <div><label style="font-size:10px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:3px">Name Size (px)</label><input type="number" id="adj-name-size" value="<?php echo $size_name ?>" min="16" max="120" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
        <div><label style="font-size:10px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:3px">Position Size</label><input type="number" id="adj-contest-size" value="<?php echo $size_contest ?>" min="10" max="80" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
        <div><label style="font-size:10px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:3px">Area Size</label><input type="number" id="adj-area-size" value="<?php echo $size_area ?>" min="10" max="80" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
        <div><label style="font-size:10px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:3px">Location Size</label><input type="number" id="adj-loc-size" value="<?php echo $size_loc ?>" min="8" max="60" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
        <div><label style="font-size:10px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:3px">Name Line Height</label><input type="number" id="adj-lh-name" value="<?php echo $lh_name ?>" min="0.8" max="3" step="0.05" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
        <div><label style="font-size:10px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:3px">Position Line Height</label><input type="number" id="adj-lh-contest" value="<?php echo $lh_contest ?>" min="0.8" max="3" step="0.05" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
        <div><label style="font-size:10px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:3px">Area Line Height</label><input type="number" id="adj-lh-area" value="<?php echo $lh_area ?>" min="0.8" max="3" step="0.05" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
        <div><label style="font-size:10px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:3px">Text Width (px)</label><input type="number" id="adj-width" value="<?php echo $pos_name_width ?>" min="100" max="900" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
    </div>
    <div style="background:#f8f5ef;border-radius:8px;padding:12px;margin-bottom:12px">
        <strong style="font-size:11px;text-transform:uppercase;display:block;margin-bottom:8px;color:#555">📍 Text Positions (px from top/left of poster)</strong>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
            <div><label style="font-size:10px;font-weight:700;display:block;margin-bottom:3px;color:#888">Name Top</label><input type="number" id="adj-name-top" value="<?php echo $pos_name_top ?>" min="0" max="1000" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
            <div><label style="font-size:10px;font-weight:700;display:block;margin-bottom:3px;color:#888">Name Left</label><input type="number" id="adj-name-left" value="<?php echo $pos_name_left ?>" min="0" max="900" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
            <div><label style="font-size:10px;font-weight:700;display:block;margin-bottom:3px;color:#888">Position Top</label><input type="number" id="adj-contest-top" value="<?php echo $pos_contest_top ?>" min="0" max="1000" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
            <div><label style="font-size:10px;font-weight:700;display:block;margin-bottom:3px;color:#888">Position Left</label><input type="number" id="adj-contest-left" value="<?php echo $pos_contest_left ?>" min="0" max="900" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
            <div><label style="font-size:10px;font-weight:700;display:block;margin-bottom:3px;color:#888">Area Top</label><input type="number" id="adj-area-top" value="<?php echo $pos_area_top ?>" min="0" max="1000" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
            <div><label style="font-size:10px;font-weight:700;display:block;margin-bottom:3px;color:#888">Area Left</label><input type="number" id="adj-area-left" value="<?php echo $pos_area_left ?>" min="0" max="900" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
            <div><label style="font-size:10px;font-weight:700;display:block;margin-bottom:3px;color:#888">Location Top</label><input type="number" id="adj-loc-top" value="<?php echo $pos_loc_top ?>" min="0" max="1000" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
            <div><label style="font-size:10px;font-weight:700;display:block;margin-bottom:3px;color:#888">Location Left</label><input type="number" id="adj-loc-left" value="<?php echo $pos_loc_left ?>" min="0" max="900" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px"></div>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:12px">
        <?php
        $gf_fonts = ['inherit'=>'Default','Noto Sans Devanagari, sans-serif'=>'Noto Devanagari','Tiro Devanagari Hindi, serif'=>'Tiro Hindi','Baloo 2, cursive'=>'Baloo 2','Hind, sans-serif'=>'Hind','Mukta, sans-serif'=>'Mukta','Rajdhani, sans-serif'=>'Rajdhani','Yatra One, cursive'=>'Yatra One','Rozha One, serif'=>'Rozha One','Roboto, sans-serif'=>'Roboto','Poppins, sans-serif'=>'Poppins','Arial, sans-serif'=>'Arial','Georgia, serif'=>'Georgia'];
        foreach([['adj-name-font','enx_poster_font_name','Name Font'],['adj-contest-font','enx_poster_font_contest','Position Font'],['adj-area-font','enx_poster_font_area','Area Font'],['adj-loc-font','enx_poster_font_loc','Location Font']] as [$id,$opt,$label]):
        $cur=get_option($opt,'inherit');
        ?>
        <div>
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;display:block;margin-bottom:4px"><?php echo $label ?></label>
            <select id="<?php echo $id ?>" style="width:100%;padding:7px;border:1px solid #ddd;border-radius:6px;font-size:12px">
                <?php foreach($gf_fonts as $fv=>$fl): ?>
                <option value="<?php echo esc_attr($fv) ?>"<?php selected($cur,$fv) ?>><?php echo esc_html($fl) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <?php endforeach ?>
        <div style="grid-column:1/-1">
            <button id="adj-apply" style="background:#f59e0b;color:#fff;padding:9px 20px;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px">Apply Changes</button>
            <button id="adj-save" style="background:#059669;color:#fff;padding:9px 20px;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px;margin-left:8px">💾 Save to Settings</button>
            <span id="adj-save-status" style="margin-left:10px;font-size:13px;color:#888"></span>
        </div>
    </div>
</div>
<?php endif ?>
<div class="stage">
<div id="poster">
    <?php if($template_img): ?>
    <img class="p-bg" src="<?php echo esc_url($template_img) ?>" crossorigin="anonymous" alt="">
    <?php else: ?>
    <!-- No template set — using gradient background -->
    <div style="position:absolute;inset:0;background:<?php echo esc_attr($theme['gradient']) ?>;z-index:0"></div>
    <?php endif ?>
    <div class="p-accent"></div>
    <div class="p-photo" style="background-image:url('<?php echo esc_url($photo) ?>')"></div>
    <div class="p-name" id="p-name"><?php echo nl2br(esc_html($name)) ?></div>
    <div class="p-contest" id="p-contest"><?php echo esc_html($contest_label) ?></div>
    <div class="p-area" id="p-area"><?php echo esc_html($highlight) ?></div>
    <div class="p-loc"><?php echo esc_html($area_line) ?></div>
    <div class="p-preload">
        <img src="<?php echo esc_url($photo) ?>" crossorigin="anonymous" alt="">
        <?php if($template_img): ?><img src="<?php echo esc_url($template_img) ?>" crossorigin="anonymous" alt=""><?php endif ?>
    </div>
</div>
</div>

<?php if(!$template_img): ?>
<div style="margin-top:14px;background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:12px 16px;font-size:13px;max-width:500px">
    ⚠️ No poster template set for this position. <a href="<?php echo admin_url('admin.php?page=enx-settings') ?>">Upload templates in Settings → Poster Templates</a>
</div>
<?php endif ?>

</div>
<script>
function autoFit(id,maxF,minF,maxH){
    const el=document.getElementById(id);if(!el)return;
    let f=maxF;el.style.fontSize=f+'px';
    while(f>minF&&el.scrollHeight>maxH){f-=2;el.style.fontSize=f+'px';}
}
window.addEventListener('load',function(){
    autoFit('p-name',<?php echo $size_name ?>,36,200);
    autoFit('p-contest',<?php echo $size_contest ?>,18,80);
    autoFit('p-area',<?php echo $size_area ?>,20,90);
});
async function waitForImages(){
    const imgs=[...document.querySelectorAll('#poster img')];
    await Promise.all(imgs.map(img=>new Promise(r=>{
        if(!img)return r();if(img.complete&&img.naturalWidth>0)return r();
        img.onload=r;img.onerror=r;
    })));
}
async function getCanvas(){
    await waitForImages();
    const p=document.getElementById('poster');
    const orig=p.getAttribute('style')||'';
    Object.assign(p.style,{width:'1080px',height:'1080px',transform:'none',margin:'0'});
    const c=await html2canvas(p,{useCORS:true,allowTaint:false,backgroundColor:null,scale:2,width:1080,height:1080,imageTimeout:20000});
    p.setAttribute('style',orig);return c;
}
async function dlPoster(){
    try{const c=await getCanvas(),a=document.createElement('a');a.download='<?php echo sanitize_title($name) ?>-poster.png';a.href=c.toDataURL('image/png');a.click();}
    catch(e){alert('<?php echo ENX_IS_HI?"डाउनलोड विफल। पुनः प्रयास करें।":"Download failed. Try again." ?>');}
}
// Admin live adjustments
var adjApply = document.getElementById('adj-apply');
var adjSave  = document.getElementById('adj-save');
if (adjApply) {
    adjApply.addEventListener('click', function(){
        var nameEl = document.getElementById('p-name');
        var contEl = document.getElementById('p-contest');
        var areaEl = document.getElementById('p-area');
        var locEl  = document.querySelector('.p-loc');
        var g = function(id){ var el=document.getElementById(id); return el?el.value:''; };
        // Font sizes
        if(nameEl) nameEl.style.fontSize = g('adj-name-size')+'px';
        if(contEl) contEl.style.fontSize = g('adj-contest-size')+'px';
        if(areaEl) areaEl.style.fontSize = g('adj-area-size')+'px';
        if(locEl)  locEl.style.fontSize  = g('adj-loc-size')+'px';
        // Line heights
        if(nameEl) nameEl.style.lineHeight = g('adj-lh-name');
        if(contEl) contEl.style.lineHeight = g('adj-lh-contest');
        if(areaEl) areaEl.style.lineHeight = g('adj-lh-area');
        // Width
        var w = g('adj-width')+'px';
        [nameEl,contEl,areaEl,locEl].forEach(function(el){ if(el) el.style.width=w; });
        // Positions
        if(nameEl){ nameEl.style.top=g('adj-name-top')+'px'; nameEl.style.left=g('adj-name-left')+'px'; }
        if(contEl){ contEl.style.top=g('adj-contest-top')+'px'; contEl.style.left=g('adj-contest-left')+'px'; }
        if(areaEl){ areaEl.style.top=g('adj-area-top')+'px'; areaEl.style.left=g('adj-area-left')+'px'; }
        if(locEl){  locEl.style.top=g('adj-loc-top')+'px';   locEl.style.left=g('adj-loc-left')+'px'; }
        // Fonts
        var elMap = {'adj-name-font':nameEl,'adj-contest-font':contEl,'adj-area-font':areaEl,'adj-loc-font':locEl};
        Object.keys(elMap).forEach(function(id){ var el=elMap[id]; if(el){ var sel=document.getElementById(id); if(sel) el.style.fontFamily=sel.value==='inherit'?'':sel.value; } });
    });
}
if (adjSave) {
    adjSave.addEventListener('click', function(){
        var status = document.getElementById('adj-save-status');
        status.textContent = 'Saving...';
        var g2 = function(id){ var el=document.getElementById(id); return el?el.value:''; };
        var data = new URLSearchParams({
            action: 'enx_save_poster_settings',
            nonce: '<?php echo wp_create_nonce("enx_admin") ?>',
            enx_poster_size_name:    g2('adj-name-size'),
            enx_poster_size_contest: g2('adj-contest-size'),
            enx_poster_size_area:    g2('adj-area-size'),
            enx_poster_size_loc:     g2('adj-loc-size'),
            enx_poster_lh_name:      g2('adj-lh-name'),
            enx_poster_lh_contest:   g2('adj-lh-contest'),
            enx_poster_lh_area:      g2('adj-lh-area'),
            enx_poster_pos_name_top:     g2('adj-name-top'),
            enx_poster_pos_name_left:    g2('adj-name-left'),
            enx_poster_pos_name_width:   g2('adj-width'),
            enx_poster_pos_contest_top:  g2('adj-contest-top'),
            enx_poster_pos_contest_left: g2('adj-contest-left'),
            enx_poster_pos_area_top:     g2('adj-area-top'),
            enx_poster_pos_area_left:    g2('adj-area-left'),
            enx_poster_pos_loc_top:      g2('adj-loc-top'),
            enx_poster_pos_loc_left:     g2('adj-loc-left'),
            enx_poster_font_name:    g2('adj-name-font'),
            enx_poster_font_contest: g2('adj-contest-font'),
            enx_poster_font_area:    g2('adj-area-font'),
            enx_poster_font_loc:     g2('adj-loc-font'),
        });
        fetch('<?php echo esc_js(admin_url("admin-ajax.php")) ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data})
        .then(function(r){return r.json();}).then(function(d){
            status.textContent = d.success ? '✅ Saved! All posters updated.' : '❌ Save failed';
            status.style.color = d.success ? '#059669' : '#dc2626';
        });
    });
}

async function sharePoster(){
    const url='<?php echo esc_js(get_permalink($id)) ?>',title='<?php echo esc_js($name.' - '.$contest_label) ?>';
    try{
        const c=await getCanvas(),blob=await new Promise(r=>c.toBlob(r,'image/png'));
        const file=new File([blob],'poster.png',{type:'image/png'});
        if(navigator.canShare?.({files:[file]})){await navigator.share({title,files:[file]});return;}
    }catch(e){}
    if(navigator.share){try{await navigator.share({title,url});}catch(e){}}
    else{navigator.clipboard?.writeText(url).then(()=>alert('<?php echo ENX_IS_HI?"लिंक कॉपी हो गया।":"Link copied!" ?>'));}
}
</script>
</body></html>
<?php
}
