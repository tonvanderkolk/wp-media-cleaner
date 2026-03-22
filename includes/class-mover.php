<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMC_Mover {

    /**
     * Move a file from uploads to the review directory.
     * Preserves the original subdirectory structure (year/month).
     *
     * @param string $relative_path Relative path within uploads (e.g. 2024/03/photo.jpg).
     * @return bool True on success.
     */
    public static function move_to_review( $relative_path ) {
        $upload_dir  = wp_upload_dir();
        $basedir     = trailingslashit( $upload_dir['basedir'] );
        $source      = $basedir . $relative_path;
        $destination = $basedir . WMC_REVIEW_DIR . '/' . $relative_path;

        if ( ! file_exists( $source ) ) {
            return false;
        }

        $dest_dir = dirname( $destination );
        if ( ! file_exists( $dest_dir ) ) {
            wp_mkdir_p( $dest_dir );
        }

        $moved = rename( $source, $destination );

        if ( $moved ) {
            WMC_Logger::log( 'move', $relative_path, 'Verplaatst naar te-beoordelen.' );
        }

        return $moved;
    }

    /**
     * Move a batch of files to the review directory.
     *
     * @param array $relative_paths Array of relative paths.
     * @return array Array with 'moved' count and 'failed' count.
     */
    public static function move_batch_to_review( $relative_paths ) {
        $moved  = 0;
        $failed = 0;

        foreach ( $relative_paths as $path ) {
            if ( self::move_to_review( $path ) ) {
                $moved++;
            } else {
                $failed++;
            }
        }

        return array(
            'moved'  => $moved,
            'failed' => $failed,
        );
    }

    /**
     * Restore a file from the review directory back to its original location.
     *
     * @param string $relative_path Relative path within the review directory.
     * @return bool True on success.
     */
    public static function restore( $relative_path ) {
        $upload_dir  = wp_upload_dir();
        $basedir     = trailingslashit( $upload_dir['basedir'] );
        $source      = $basedir . WMC_REVIEW_DIR . '/' . $relative_path;
        $destination = $basedir . $relative_path;

        if ( ! file_exists( $source ) ) {
            return false;
        }

        $dest_dir = dirname( $destination );
        if ( ! file_exists( $dest_dir ) ) {
            wp_mkdir_p( $dest_dir );
        }

        $restored = rename( $source, $destination );

        if ( $restored ) {
            WMC_Logger::log( 'restore', $relative_path, 'Hersteld naar originele locatie.' );
        }

        return $restored;
    }

    /**
     * Get all files currently in the review directory.
     *
     * @return array Relative paths (relative to the review dir, which mirrors upload structure).
     */
    public static function get_review_files() {
        $upload_dir = wp_upload_dir();
        $review_dir = trailingslashit( $upload_dir['basedir'] ) . WMC_REVIEW_DIR;

        if ( ! is_dir( $review_dir ) ) {
            return array();
        }

        $files    = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $review_dir,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        $review_base = trailingslashit( $review_dir );

        foreach ( $iterator as $file ) {
            if ( ! $file->isFile() ) {
                continue;
            }

            $basename = $file->getBasename();
            if ( strpos( $basename, '.' ) === 0 ) {
                continue;
            }

            $relative = str_replace( $review_base, '', $file->getPathname() );
            $files[]  = $relative;
        }

        sort( $files );
        return $files;
    }

    /**
     * Get file size in human-readable format.
     *
     * @param string $relative_path Relative path within review dir.
     * @return string
     */
    public static function get_review_file_size( $relative_path ) {
        $upload_dir = wp_upload_dir();
        $file       = trailingslashit( $upload_dir['basedir'] ) . WMC_REVIEW_DIR . '/' . $relative_path;

        if ( ! file_exists( $file ) ) {
            return '0 B';
        }

        return size_format( filesize( $file ) );
    }

    /**
     * Get total size of all files in the review directory (in bytes).
     *
     * @return int Total size in bytes.
     */
    public static function get_review_total_size() {
        $upload_dir = wp_upload_dir();
        $review_dir = trailingslashit( $upload_dir['basedir'] ) . WMC_REVIEW_DIR;

        if ( ! is_dir( $review_dir ) ) {
            return 0;
        }

        $total    = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $review_dir,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ( $iterator as $file ) {
            if ( $file->isFile() && strpos( $file->getBasename(), '.' ) !== 0 ) {
                $total += $file->getSize();
            }
        }

        return $total;
    }
}
