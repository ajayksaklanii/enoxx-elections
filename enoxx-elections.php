<?php
/**
 * Plugin Name:  ENOXX Elections
 * Description:  Standalone HP Election Platform — Panchayat, ULB, Assembly
 * Version:      3.5.4
 * Author:       ENOXX News
 * Text Domain:  enoxx-elections
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ENX_VERSION', '3.5.4' );
define( 'ENX_FILE',    __FILE__ );
define( 'ENX_DIR',     plugin_dir_path( __FILE__ ) );
define( 'ENX_URL',     plugin_dir_url( __FILE__ ) );

$_site = get_site_url();
define( 'ENX_IS_HI',
    strpos( $_site, 'enoxxnews.in' ) !== false ||
    strpos( $_site, 'demo.enoxxnews.in' ) !== false
);

function enx_load( $file ) {
    $p = ENX_DIR . $file;
    if ( file_exists( $p ) ) { require_once $p; return true; }
    return false;
}

enx_load( 'includes/class-post-types.php'  );
enx_load( 'includes/class-location.php'    );
enx_load( 'includes/class-helpers.php'     );
enx_load( 'includes/class-seo.php'         );
enx_load( 'includes/class-sync.php'        );
enx_load( 'includes/class-poster.php'      );
// Location addenda
enx_load( 'location/data/kangra-zp-wards.php' );
// Admin
enx_load( 'admin/class-admin.php'          );
enx_load( 'admin/class-candidate-form.php' );
enx_load( 'admin/class-settings.php'       );
enx_load( 'admin/class-import-export.php'  );
enx_load( 'admin/class-location-import.php' );
enx_load( 'admin/class-poster-studio.php'   );
enx_load( 'admin/class-donations.php'       );
enx_load( 'frontend/class-public-form.php'  );
// Frontend
enx_load( 'frontend/class-profile.php'     );
enx_load( 'frontend/class-directory.php'   );

register_activation_hook( __FILE__, 'enx_on_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

function enx_on_activate() {
    // Set default permissions on first install
    if ( get_option( 'enx_perm_editor_edit' ) === false ) {
        update_option( 'enx_perm_editor_edit',          '1' );
        update_option( 'enx_perm_editor_delete',        '0' );
        update_option( 'enx_perm_contributor_edit',     '1' );
        update_option( 'enx_perm_contributor_delete',   '0' );
    }
    // Schedule flush — do NOT call do_action('init') or flush_rewrite_rules() directly here
    // as the CPT is not registered yet at activation time; use the footer hook instead
    update_option( 'enx_flush_on_next_load', '1' );
}

// Enqueue frontend CSS
add_action( 'wp_enqueue_scripts', function() {
    // Load on candidate profiles, directories, and any page with our shortcodes
    $load_css = is_singular('candidate')
             || is_post_type_archive('candidate')
             || ( is_page() && has_shortcode( get_post()->post_content ?? '', 'enx_candidate_form' ) );
    if ( $load_css ) {
        wp_enqueue_style( 'enx-profile', ENX_URL.'frontend/profile.css', [], ENX_VERSION );
    }
    // Always load on pages that use directory template
    if ( is_page() ) {
        global $post;
        if ( $post && strpos( $post->post_content, 'enx_candidate_form' ) !== false ) {
            wp_enqueue_style( 'enx-profile', ENX_URL.'frontend/profile.css', [], ENX_VERSION );
        }
    }
} );

// Flush rewrite rules on first page load after activation
add_action( 'init', function() {
    if ( get_option( 'enx_flush_on_next_load' ) ) {
        delete_option( 'enx_flush_on_next_load' );
        flush_rewrite_rules();
    }
}, 99 );
