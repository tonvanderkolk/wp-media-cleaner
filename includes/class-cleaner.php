<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMC_Cleaner {

    /**
     * Permanently delete a file from the review directory.
     *
     * @param string $relative_path Relative path within the review directory.
     * @return bool True on success.
     */
    public static function delete_file( $relative_path ) {
        $upload_dir = wp_upload_dir();
        $file       = trailingslashit( $upload_dir['basedir'] ) . WMC_REVIEW_DIR . '/' . $relative_path;

        if ( ! file_exists( $file ) ) {
            return false;
        }

        $deleted = unlink( $file );

        if ( $deleted ) {
            WMC_Logger::log( 'delete', $relative_path, 'Definitief verwijderd.' );

            // Clean up empty parent directories.
            self::cleanup_empty_dirs( dirname( $file ) );
        }

        return $deleted;
    }

    /**
     * Delete multiple files from the review directory.
     *
     * @param array $relative_paths Array of relative paths.
     * @return array Array with 'deleted' and 'failed' counts.
     */
    public static function delete_batch( $relative_paths ) {
        $deleted = 0;
        $failed  = 0;

        foreach ( $relative_paths as $path ) {
            if ( self::delete_file( $path ) ) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        return array(
            'deleted' => $deleted,
            'failed'  => $failed,
        );
    }

    /**
     * Create a zip archive of selected files from the review directory.
     *
     * @param array $relative_paths Array of relative paths within review dir.
     * @return string|false Path to the zip file on success, false on failure.
     */
    public static function create_zip( $relative_paths ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return false;
        }

        $upload_dir  = wp_upload_dir();
        $review_base = trailingslashit( $upload_dir['basedir'] ) . WMC_REVIEW_DIR . '/';
        $zip_file    = trailingslashit( $upload_dir['basedir'] ) . 'wmc-download-' . time() . '.zip';

        $zip = new ZipArchive();
        if ( $zip->open( $zip_file, ZipArchive::CREATE ) !== true ) {
            return false;
        }

        $added = 0;
        foreach ( $relative_paths as $relative_path ) {
            $full_path = $review_base . $relative_path;
            if ( file_exists( $full_path ) ) {
                $zip->addFile( $full_path, $relative_path );
                $added++;
            }
        }

        $zip->close();

        if ( $added === 0 ) {
            @unlink( $zip_file );
            return false;
        }

        WMC_Logger::log( 'download', '', sprintf( '%d bestanden gedownload als zip.', $added ) );

        return $zip_file;
    }

    /**
     * Send a zip file as a download and delete it afterwards.
     *
     * @param string $zip_path Absolute path to the zip file.
     */
    public static function send_zip_download( $zip_path ) {
        if ( ! file_exists( $zip_path ) ) {
            return;
        }

        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . basename( $zip_path ) . '"' );
        header( 'Content-Length: ' . filesize( $zip_path ) );
        header( 'Pragma: public' );

        readfile( $zip_path );
        @unlink( $zip_path );
        exit;
    }

    /**
     * Remove empty directories up to the review directory root.
     *
     * @param string $dir Directory to check.
     */
    private static function cleanup_empty_dirs( $dir ) {
        $upload_dir  = wp_upload_dir();
        $review_root = trailingslashit( $upload_dir['basedir'] ) . WMC_REVIEW_DIR;

        while ( $dir !== $review_root && is_dir( $dir ) ) {
            $items = array_diff( scandir( $dir ), array( '.', '..', '.htaccess' ) );
            if ( ! empty( $items ) ) {
                break;
            }
            rmdir( $dir );
            $dir = dirname( $dir );
        }
    }
}
