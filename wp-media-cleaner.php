<?php
/**
 * Plugin Name: WP Media Cleaner
 * Plugin URI:  https://github.com/tonvanderkolk/wp-media-cleaner
 * Description: Scant de uploads-map op ongebruikte bestanden en verplaatst ze naar een beoordelingsmap.
 * Version:     1.2.0
 * Author:      TvdK Apps
 * Author URI:  https://tvdk.nl
 * License:     GPL-2.0-or-later
 * Text Domain: wp-media-cleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WMC_VERSION', '1.2.0' );
define( 'WMC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WMC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WMC_REVIEW_DIR', 'te-beoordelen' );

// Autoload classes.
require_once WMC_PLUGIN_DIR . 'includes/class-logger.php';
require_once WMC_PLUGIN_DIR . 'includes/class-scanner.php';
require_once WMC_PLUGIN_DIR . 'includes/class-mover.php';
require_once WMC_PLUGIN_DIR . 'includes/class-cleaner.php';

if ( is_admin() ) {
    require_once WMC_PLUGIN_DIR . 'admin/class-admin-page.php';
}

/**
 * Create the log table on plugin activation.
 */
function wmc_activate() {
    WMC_Logger::create_table();

    // Protect the review directory with .htaccess.
    $upload_dir  = wp_upload_dir();
    $review_path = trailingslashit( $upload_dir['basedir'] ) . WMC_REVIEW_DIR;

    if ( ! file_exists( $review_path ) ) {
        wp_mkdir_p( $review_path );
    }

    $htaccess = trailingslashit( $review_path ) . '.htaccess';
    if ( ! file_exists( $htaccess ) ) {
        file_put_contents( $htaccess, "Order Deny,Allow\nDeny from all\n" );
    }
}
register_activation_hook( __FILE__, 'wmc_activate' );
