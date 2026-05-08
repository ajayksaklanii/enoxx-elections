<?php
/**
 * ENOXX Elections — Directory Page Template
 * Forces wide/full layout on SmartMag / Bunyad / any boxed theme.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'bunyad_layout',          function() { return 'wide'; }, 99 );
add_filter( 'ts_layout',              function() { return 'wide'; }, 99 );
add_filter( 'smartmag/layout',        function() { return 'wide'; }, 99 );
add_filter( 'bunyad_sidebar_enabled', function() { return false;  }, 99 );
add_filter( 'smartmag/sidebar/display', function() { return false; }, 99 );
add_filter( 'body_class', function($c){ $c[]='enx-directory-page'; return $c; } );

get_header();
enx_directory_css();
?>
<style>
/* Force full-width on all theme container wrappers */
body.enx-directory-page,
body.enx-directory-page #wrapper,
body.enx-directory-page .ts-site,
body.enx-directory-page .site,
body.enx-directory-page .page-wrap { max-width:100%!important; width:100%!important; box-shadow:none!important; margin:0!important; }
body.enx-directory-page .site-content,
body.enx-directory-page .site-content > .ts-container,
body.enx-directory-page #content,
body.enx-directory-page #primary,
body.enx-directory-page .content-area,
body.enx-directory-page .site-main,
body.enx-directory-page .ts-post-content,
body.enx-directory-page .entry-content,
body.enx-directory-page .post-content { max-width:100%!important; width:100%!important; padding:0!important; margin:0!important; float:none!important; box-shadow:none!important; }
/* Hide theme sidebars and post chrome */
body.enx-directory-page .sidebar-primary,
body.enx-directory-page #secondary,
body.enx-directory-page .sidebar,
body.enx-directory-page .widget-area,
body.enx-directory-page .ts-sidebar,
body.enx-directory-page .bunyad-sidebar { display:none!important; }
body.enx-directory-page .post-header,
body.enx-directory-page .entry-header,
body.enx-directory-page .page-header,
body.enx-directory-page .article-header,
body.enx-directory-page .ts-breadcrumbs,
body.enx-directory-page .bunyad-breadcrumbs,
body.enx-directory-page .ts-post-meta,
body.enx-directory-page .post-meta,
body.enx-directory-page .entry-meta { display:none!important; }
</style>

<?php
/* ── Election Directory Top banner — full-width, centered, rendered once ─ */
if ( is_active_sidebar('enx-directory-top') ):
?>
<div style="width:100%;text-align:center;margin-bottom:0;line-height:0">
    <?php dynamic_sidebar('enx-directory-top'); ?>
</div>
<?php endif; ?>

<?php if ( is_active_sidebar('enx-directory-sidebar') ): ?>
<div style="display:grid;grid-template-columns:1fr 300px;gap:24px;max-width:1200px;margin:0 auto;padding:0 18px;box-sizing:border-box;align-items:start">
    <div id="enx-dir-output">
        <?php enx_render_directory_content(); ?>
    </div>
    <aside style="position:sticky;top:24px;padding:0">
        <?php dynamic_sidebar('enx-directory-sidebar'); ?>
    </aside>
</div>
<?php else: ?>
<div id="enx-dir-output">
    <?php enx_render_directory_content(); ?>
</div>
<?php endif; ?>

<?php get_footer(); ?>
