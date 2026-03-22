<?php
/**
 * WP Media Cleaner - Uninstall
 *
 * Ruimt alle plugin-data op wanneer de plugin wordt verwijderd.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Load the logger to access the drop_table method.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-logger.php';

// Drop the log table.
WMC_Logger::drop_table();

// Remove the review directory and all contents.
$upload_dir  = wp_upload_dir();
$review_path = trailingslashit( $upload_dir['basedir'] ) . 'te-beoordelen';

if ( is_dir( $review_path ) ) {
    wmc_remove_directory( $review_path );
}

/**
 * Recursively remove a directory and its contents.
 *
 * @param string $dir Directory path.
 */
function wmc_remove_directory( $dir ) {
    if ( ! is_dir( $dir ) ) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ( $items as $item ) {
        if ( $item->isDir() ) {
            rmdir( $item->getPathname() );
        } else {
            unlink( $item->getPathname() );
        }
    }

    rmdir( $dir );
}
