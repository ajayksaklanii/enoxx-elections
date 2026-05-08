<?php
if (!defined('ABSPATH')) exit;

/* Disable common "related posts" / "similar posts" outputs that SmartMag, Jetpack,
   YARPP, Yoast, and other plugins inject after the_content on singular posts.
   We do this server-side AND via CSS so nothing leaks below our profile. */
if ( ! function_exists('enx_disable_related_on_candidate') ) {
    function enx_disable_related_on_candidate() {
        if ( ! is_singular('candidate') ) return;
        // Jetpack Related Posts
        add_filter( 'jetpack_relatedposts_filter_enabled_for_request', '__return_false' );
        // YARPP — block automatic display on this post type
        add_filter( 'yarpp_results', '__return_empty_array' );
        // SmartMag / Bunyad theme related posts — try common option/filter names
        add_filter( 'bunyad_related_posts_enabled', '__return_false' );
        add_filter( 'smartmag_related_posts_enabled', '__return_false' );
        // RankMath / Yoast schema sometimes adds suggestion blocks — leave SEO alone
        // Defensive: ensure we never inject contextual related blocks via the_content_more
        add_filter( 'the_content_more_link', '__return_empty_string' );
    }
    add_action( 'wp', 'enx_disable_related_on_candidate' );
}
get_header();
?>
<style>
/* Hide ALL known related/similar/suggested post modules on candidate singulars.
   We cast a wide net because different themes (SmartMag, Bunyad, GeneratePress,
   Newspaper, Astra) and plugins (Jetpack, YARPP, Contextual Related Posts,
   Related Posts for WordPress) use different markup. */
body.single-candidate .sidebar,
body.single-candidate .main-sidebar,
body.single-candidate .post-meta,
body.single-candidate .entry-meta,
body.single-candidate .post-header,
body.single-candidate .entry-header,
body.single-candidate .breadcrumbs,
body.single-candidate .author-box,
body.single-candidate .post-share,
body.single-candidate .post-tags,
body.single-candidate .comments-area,
body.single-candidate #comments,
/* Related posts modules — wide net */
body.single-candidate .related-posts,
body.single-candidate .related-posts-block,
body.single-candidate .related-posts-section,
body.single-candidate .bunyad-related-posts,
body.single-candidate .bunyad-related,
body.single-candidate .bs-related-posts,
body.single-candidate .bs-listing-related,
body.single-candidate .smartmag-related,
body.single-candidate .smartmag-related-posts,
body.single-candidate .tie-related-posts,
body.single-candidate .post-related,
body.single-candidate .single-related,
body.single-candidate .related-articles,
body.single-candidate .related-stories,
body.single-candidate .yarpp-related,
body.single-candidate #yarpp-related-posts,
body.single-candidate .jp-relatedposts,
body.single-candidate #jp-relatedposts,
body.single-candidate .crp_related,
body.single-candidate .related,
body.single-candidate section.related,
body.single-candidate aside.related,
body.single-candidate div.related,
/* "Similar post" / "You may also like" headings */
body.single-candidate h3.related-title,
body.single-candidate .you-may-also-like,
body.single-candidate .similar-posts,
body.single-candidate .related-section{display:none!important}

body.single-candidate .content,
body.single-candidate .post-content,
body.single-candidate .entry-content,
body.single-candidate .main-content{padding:0!important;margin:0!important;max-width:100%!important;box-shadow:none!important;border:none!important;background:transparent!important}
</style>
<main id="main"><?php while(have_posts()){the_post();echo apply_filters('the_content','');} ?></main>
<?php
/* Candidate Profile Bottom widget — rendered BEFORE footer so it appears above it */
if ( is_active_sidebar('enx-candidate-bottom') ):
?>
<div style="width:100%;clear:both;margin-top:0;background:#fff">
    <?php dynamic_sidebar('enx-candidate-bottom'); ?>
</div>
<?php
endif;
get_footer();
