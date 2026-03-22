<?php
/**
 * WP Media Cleaner - Uninstall
 *
 * Ruimt plugin-data op wanneer de plugin wordt verwijderd.
 * De map 'te-beoordelen' wordt NIET verwijderd als er nog bestanden in staan.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Load the logger to access the drop_table method.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-logger.php';

// Drop the log table.
WMC_Logger::drop_table();

// Remove stored options.
delete_option( 'wmc_unattached_items' );

// Only remove the review directory if it is empty (no media files left).
$upload_dir  = wp_upload_dir();
$review_path = trailingslashit( $upload_dir['basedir'] ) . 'te-beoordelen';

if ( is_dir( $review_path ) ) {
    $has_files = false;
    $iterator  = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $review_path, RecursiveDirectoryIterator::SKIP_DOTS )
    );

    foreach ( $iterator as $item ) {
        if ( $item->isFile() && $item->getBasename() !== '.htaccess' ) {
            $has_files = true;
            break;
        }
    }

    // Only remove if no media files remain.
    if ( ! $has_files ) {
        // Remove .htaccess and empty subdirectories.
        $htaccess = trailingslashit( $review_path ) . '.htaccess';
        if ( file_exists( $htaccess ) ) {
            unlink( $htaccess );
        }
        @rmdir( $review_path );
    }
}
