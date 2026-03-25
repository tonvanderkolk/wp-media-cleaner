<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMC_Scanner {

    /**
     * Upload base directory (absolute path).
     */
    private $upload_basedir;

    /**
     * Upload base URL.
     */
    private $upload_baseurl;

    /**
     * Set of file paths known to be in use (relative to upload basedir).
     */
    private $used_files = array();

    /**
     * Media file extensions to scan. Only these types are considered.
     */
    private static $media_extensions = array(
        // Images
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'tiff', 'tif', 'avif',
        // Video
        'mp4', 'mov', 'avi', 'wmv', 'mkv', 'webm', 'flv', 'ogv', 'm4v',
        // Audio
        'mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma', 'aiff', 'aif',
        // Documents
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
        // Archives (uploaded by users)
        'zip', 'rar', 'tar', 'gz',
        // Other common uploads
        'csv', 'rtf', 'txt',
    );

    /**
     * Directories within uploads/ that belong to plugins and should be skipped.
     */
    private static $excluded_dirs = array(
        'te-beoordelen',
        'wc-logs',
        'woocommerce_uploads',
        'sucuri',
        'really-simple-ssl',
        'facebook_for_woocommerce',
        'woo-feed',
        'wp-security-audit-log',
        'wpforms',
        'gravityforms',
        'formidable',
        'elementor',
        'brizy',
        'bb-plugin',
        'cache',
        'et-cache',
        'ai1wm-backups',
        'backups',
        'backup',
        'updraft',
        'backwpup',
        'mainwp',
        'pb_backupbuddy',
        'mailpoet',
        'smush-webp',
        'ShortpixelBackups',
        'starter-templates',
    );

    /**
     * Get all excluded directories: hardcoded defaults merged with user-defined.
     *
     * @return array
     */
    public static function get_all_excluded_dirs() {
        $custom = get_option( 'wmc_custom_excluded_dirs', array() );
        if ( ! is_array( $custom ) ) {
            $custom = array();
        }
        return array_unique( array_merge( self::$excluded_dirs, $custom ) );
    }

    /**
     * Get the hardcoded default excluded directories.
     *
     * @return array
     */
    public static function get_default_excluded_dirs() {
        return self::$excluded_dirs;
    }

    /**
     * Constructor.
     */
    public function __construct() {
        $upload_dir          = wp_upload_dir();
        $this->upload_basedir = trailingslashit( $upload_dir['basedir'] );
        $this->upload_baseurl = trailingslashit( $upload_dir['baseurl'] );
    }

    /**
     * Run the full scan.
     *
     * @return array List of unused file paths (relative to upload basedir).
     */
    public function scan() {
        // 1. Collect all files on disk.
        $all_files = $this->get_all_upload_files();

        if ( empty( $all_files ) ) {
            return array();
        }

        // 2. Build the set of used files from all sources.
        $this->used_files = array();
        $this->collect_media_library_files();
        $this->collect_post_content_files( $all_files );
        $this->collect_featured_images();
        $this->collect_widget_files( $all_files );
        $this->collect_customizer_files( $all_files );
        $this->collect_site_settings_files();
        $this->collect_acf_files( $all_files );
        $this->collect_woocommerce_files();
        $this->collect_options_files( $all_files );

        // 3. Determine unused files and calculate total size.
        $unused     = array();
        $files_size = 0;
        foreach ( $all_files as $relative_path ) {
            if ( ! isset( $this->used_files[ $relative_path ] ) ) {
                $unused[] = $relative_path;
                $abs_path = $this->upload_basedir . $relative_path;
                if ( file_exists( $abs_path ) ) {
                    $files_size += filesize( $abs_path );
                }
            }
        }

        // 4. Find unattached Media Library items (registered but not used anywhere).
        $unattached      = $this->find_unattached_media();
        $unattached_size = 0;
        foreach ( $unattached as $item ) {
            if ( ! empty( $item['file'] ) ) {
                $abs_path = $this->upload_basedir . $item['file'];
                if ( file_exists( $abs_path ) ) {
                    $unattached_size += filesize( $abs_path );
                }
            }
        }

        $total_size = $files_size + $unattached_size;

        WMC_Logger::log( 'scan', '', sprintf(
            'Scan voltooid: %d bestanden gescand, %d ongebruikt op schijf (%s), %d ongebruikt in Media Library (%s). Totaal: %s.',
            count( $all_files ),
            count( $unused ),
            size_format( $files_size ),
            count( $unattached ),
            size_format( $unattached_size ),
            size_format( $total_size )
        ) );

        return array(
            'files'           => $unused,
            'files_size'      => $files_size,
            'unattached'      => $unattached,
            'unattached_size' => $unattached_size,
            'total_size'      => $total_size,
        );
    }

    /**
     * Find Media Library attachments that are not used anywhere on the site.
     *
     * @return array Array of arrays with 'id', 'file', 'title'.
     */
    private function find_unattached_media() {
        global $wpdb;

        // Get all attachment IDs.
        $attachments = $wpdb->get_results(
            "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'attachment'"
        );

        if ( empty( $attachments ) ) {
            return array();
        }

        // Collect all post content for filename matching.
        $all_content = $wpdb->get_var(
            "SELECT GROUP_CONCAT(post_content SEPARATOR '\n') FROM {$wpdb->posts}
             WHERE post_content != '' AND post_status != 'auto-draft' AND post_type != 'attachment'"
        );
        if ( ! $all_content ) {
            $all_content = '';
        }

        // Collect featured image IDs.
        $featured_ids = $wpdb->get_col(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
             WHERE meta_key = '_thumbnail_id' AND meta_value > 0"
        );
        $featured_ids = array_map( 'intval', $featured_ids );

        // Collect WooCommerce gallery IDs.
        $gallery_ids = array();
        $galleries = $wpdb->get_col(
            "SELECT meta_value FROM {$wpdb->postmeta}
             WHERE meta_key = '_product_image_gallery' AND meta_value != ''"
        );
        foreach ( $galleries as $gallery ) {
            $ids = array_filter( array_map( 'intval', explode( ',', $gallery ) ) );
            $gallery_ids = array_merge( $gallery_ids, $ids );
        }
        $gallery_ids = array_unique( $gallery_ids );

        // Site logo/icon/custom logo.
        $site_ids = array();
        $site_logo = get_option( 'site_logo' );
        if ( $site_logo ) $site_ids[] = (int) $site_logo;
        $site_icon = get_option( 'site_icon' );
        if ( $site_icon ) $site_ids[] = (int) $site_icon;
        $custom_logo = get_theme_mod( 'custom_logo' );
        if ( $custom_logo ) $site_ids[] = (int) $custom_logo;

        // Combine all known-used IDs.
        $used_ids = array_merge( $featured_ids, $gallery_ids, $site_ids );
        $used_ids = array_unique( $used_ids );

        // Collect all widget + customizer + options content for broad search.
        $options_content = $wpdb->get_var(
            "SELECT GROUP_CONCAT(option_value SEPARATOR '\n') FROM {$wpdb->options}
             WHERE option_value != ''"
        );
        if ( ! $options_content ) {
            $options_content = '';
        }

        // Collect all postmeta for broad search.
        $meta_content = $wpdb->get_var(
            "SELECT GROUP_CONCAT(meta_value SEPARATOR '\n') FROM {$wpdb->postmeta}
             WHERE meta_value != '' AND meta_value IS NOT NULL"
        );
        if ( ! $meta_content ) {
            $meta_content = '';
        }

        $combined_content = $all_content . "\n" . $options_content . "\n" . $meta_content;

        $unattached = array();

        foreach ( $attachments as $attachment ) {
            $id = (int) $attachment->ID;

            // Skip if used as featured image, gallery, or site setting.
            if ( in_array( $id, $used_ids, true ) ) {
                continue;
            }

            // Skip if attached to a post.
            $parent = $wpdb->get_var( $wpdb->prepare(
                "SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d", $id
            ) );
            if ( (int) $parent > 0 ) {
                continue;
            }

            // Check if the filename appears in any content.
            $file = get_post_meta( $id, '_wp_attached_file', true );
            if ( ! empty( $file ) ) {
                $filename = basename( $file );
                if ( strpos( $combined_content, $filename ) !== false ) {
                    continue; // Referenced somewhere.
                }
            }

            $unattached[] = array(
                'id'    => $id,
                'file'  => $file,
                'title' => $attachment->post_title,
            );
        }

        return $unattached;
    }

    /**
     * Recursively list all files in the uploads directory.
     * Excludes the review directory.
     *
     * @return array Relative paths.
     */
    private function get_all_upload_files() {
        $files    = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->upload_basedir,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        // Build list of absolute excluded directory paths (hardcoded + user-defined).
        $all_excluded = self::get_all_excluded_dirs();
        $excluded_paths = array();
        foreach ( $all_excluded as $dir ) {
            $excluded_paths[] = trailingslashit( $this->upload_basedir . $dir );
        }

        foreach ( $iterator as $file ) {
            if ( ! $file->isFile() ) {
                continue;
            }

            $absolute = $file->getPathname();

            // Skip excluded plugin directories.
            foreach ( $excluded_paths as $excluded ) {
                if ( strpos( $absolute, $excluded ) === 0 ) {
                    continue 2;
                }
            }

            // Skip hidden files and system files.
            $basename = $file->getBasename();
            if ( strpos( $basename, '.' ) === 0 ) {
                continue;
            }

            // Only include media file types.
            $extension = strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) );
            if ( ! in_array( $extension, self::$media_extensions, true ) ) {
                continue;
            }

            $relative = str_replace( $this->upload_basedir, '', $absolute );
            $files[]  = $relative;
        }

        return $files;
    }

    /**
     * Mark a file as used.
     *
     * @param string $relative_path Relative to upload basedir.
     */
    private function mark_used( $relative_path ) {
        $this->used_files[ $relative_path ] = true;
    }

    /**
     * Try to resolve a URL or path to a relative upload path and mark it used.
     *
     * @param string $reference URL, absolute path, or relative path.
     */
    private function resolve_and_mark( $reference ) {
        if ( empty( $reference ) ) {
            return;
        }

        // If it's a URL, strip the base URL.
        if ( strpos( $reference, $this->upload_baseurl ) !== false ) {
            $relative = str_replace( $this->upload_baseurl, '', $reference );
            $this->mark_used( $relative );
            return;
        }

        // If it's an absolute path, strip the base dir.
        if ( strpos( $reference, $this->upload_basedir ) !== false ) {
            $relative = str_replace( $this->upload_basedir, '', $reference );
            $this->mark_used( $relative );
            return;
        }

        // If it looks like a relative upload path (contains year/month pattern).
        if ( preg_match( '#^\d{4}/\d{2}/.+#', $reference ) ) {
            $this->mark_used( $reference );
        }
    }

    /**
     * Mark all thumbnails/sizes for an attachment as used.
     *
     * @param int $attachment_id
     */
    private function mark_attachment_files( $attachment_id ) {
        $file = get_post_meta( $attachment_id, '_wp_attached_file', true );
        if ( ! empty( $file ) ) {
            $this->mark_used( $file );
        }

        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( ! empty( $metadata['sizes'] ) && ! empty( $file ) ) {
            $dir = trailingslashit( dirname( $file ) );
            foreach ( $metadata['sizes'] as $size ) {
                if ( ! empty( $size['file'] ) ) {
                    $this->mark_used( $dir . $size['file'] );
                }
            }
        }

        // Also mark the original if available (WP 5.3+ scaled images).
        if ( ! empty( $metadata['original_image'] ) && ! empty( $file ) ) {
            $dir = trailingslashit( dirname( $file ) );
            $this->mark_used( $dir . $metadata['original_image'] );
        }
    }

    // -------------------------------------------------------------------------
    // Source: WordPress Media Library
    // -------------------------------------------------------------------------

    private function collect_media_library_files() {
        global $wpdb;

        $attachment_ids = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment'"
        );

        foreach ( $attachment_ids as $id ) {
            $this->mark_attachment_files( (int) $id );
        }
    }

    // -------------------------------------------------------------------------
    // Source: Post content (all post types)
    // -------------------------------------------------------------------------

    private function collect_post_content_files( $all_files ) {
        global $wpdb;

        // Get all post content in one query.
        $contents = $wpdb->get_col(
            "SELECT post_content FROM {$wpdb->posts}
             WHERE post_content != '' AND post_status != 'auto-draft'"
        );

        $all_content = implode( "\n", $contents );

        // Check each file against the combined content.
        foreach ( $all_files as $relative_path ) {
            $filename = basename( $relative_path );

            if ( strpos( $all_content, $filename ) !== false ) {
                $this->mark_used( $relative_path );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Source: Featured images (_thumbnail_id)
    // -------------------------------------------------------------------------

    private function collect_featured_images() {
        global $wpdb;

        $thumbnail_ids = $wpdb->get_col(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
             WHERE meta_key = '_thumbnail_id' AND meta_value > 0"
        );

        foreach ( $thumbnail_ids as $id ) {
            $this->mark_attachment_files( (int) $id );
        }
    }

    // -------------------------------------------------------------------------
    // Source: Widget settings
    // -------------------------------------------------------------------------

    private function collect_widget_files( $all_files ) {
        global $wpdb;

        $widget_options = $wpdb->get_col(
            "SELECT option_value FROM {$wpdb->options}
             WHERE option_name LIKE 'widget_%'"
        );

        $combined = implode( "\n", $widget_options );

        foreach ( $all_files as $relative_path ) {
            $filename = basename( $relative_path );
            if ( strpos( $combined, $filename ) !== false ) {
                $this->mark_used( $relative_path );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Source: Customizer settings (theme_mods)
    // -------------------------------------------------------------------------

    private function collect_customizer_files( $all_files ) {
        global $wpdb;

        $theme_mods = $wpdb->get_col(
            "SELECT option_value FROM {$wpdb->options}
             WHERE option_name LIKE 'theme_mods_%'"
        );

        $combined = implode( "\n", $theme_mods );

        foreach ( $all_files as $relative_path ) {
            $filename = basename( $relative_path );
            if ( strpos( $combined, $filename ) !== false ) {
                $this->mark_used( $relative_path );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Source: Site settings (logo, favicon)
    // -------------------------------------------------------------------------

    private function collect_site_settings_files() {
        $site_logo = get_option( 'site_logo' );
        if ( ! empty( $site_logo ) ) {
            $this->mark_attachment_files( (int) $site_logo );
        }

        $site_icon = get_option( 'site_icon' );
        if ( ! empty( $site_icon ) ) {
            $this->mark_attachment_files( (int) $site_icon );
        }

        // Custom logo via theme support.
        $custom_logo = get_theme_mod( 'custom_logo' );
        if ( ! empty( $custom_logo ) ) {
            $this->mark_attachment_files( (int) $custom_logo );
        }
    }

    // -------------------------------------------------------------------------
    // Source: ACF fields
    // -------------------------------------------------------------------------

    private function collect_acf_files( $all_files ) {
        global $wpdb;

        // ACF stores image/file field values as attachment IDs or URLs in postmeta.
        // We scan postmeta values for filenames.
        $meta_values = $wpdb->get_col(
            "SELECT meta_value FROM {$wpdb->postmeta}
             WHERE meta_value != '' AND meta_value IS NOT NULL"
        );

        $combined = implode( "\n", $meta_values );

        foreach ( $all_files as $relative_path ) {
            if ( isset( $this->used_files[ $relative_path ] ) ) {
                continue; // Already marked.
            }

            $filename = basename( $relative_path );
            if ( strpos( $combined, $filename ) !== false ) {
                $this->mark_used( $relative_path );
            }
        }

        // Also check wp_options for ACF option pages.
        $option_values = $wpdb->get_col(
            "SELECT option_value FROM {$wpdb->options}
             WHERE option_name LIKE 'options_%'"
        );

        $combined_options = implode( "\n", $option_values );

        foreach ( $all_files as $relative_path ) {
            if ( isset( $this->used_files[ $relative_path ] ) ) {
                continue;
            }

            $filename = basename( $relative_path );
            if ( strpos( $combined_options, $filename ) !== false ) {
                $this->mark_used( $relative_path );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Source: WooCommerce product images
    // -------------------------------------------------------------------------

    private function collect_woocommerce_files() {
        global $wpdb;

        // Product gallery images stored as comma-separated attachment IDs.
        $galleries = $wpdb->get_col(
            "SELECT meta_value FROM {$wpdb->postmeta}
             WHERE meta_key = '_product_image_gallery' AND meta_value != ''"
        );

        foreach ( $galleries as $gallery ) {
            $ids = array_filter( array_map( 'intval', explode( ',', $gallery ) ) );
            foreach ( $ids as $id ) {
                $this->mark_attachment_files( $id );
            }
        }

        // Variation images.
        $variation_images = $wpdb->get_col(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = 'product_variation'
             AND pm.meta_key = '_thumbnail_id'
             AND pm.meta_value > 0"
        );

        foreach ( $variation_images as $id ) {
            $this->mark_attachment_files( (int) $id );
        }
    }

    // -------------------------------------------------------------------------
    // Source: Broad scan on wp_options for filenames
    // -------------------------------------------------------------------------

    private function collect_options_files( $all_files ) {
        global $wpdb;

        // Get all option values (excluding those already scanned).
        $all_options = $wpdb->get_col(
            "SELECT option_value FROM {$wpdb->options}
             WHERE option_name NOT LIKE 'widget_%'
             AND option_name NOT LIKE 'theme_mods_%'
             AND option_name NOT LIKE 'options_%'
             AND option_value != ''"
        );

        $combined = implode( "\n", $all_options );

        foreach ( $all_files as $relative_path ) {
            if ( isset( $this->used_files[ $relative_path ] ) ) {
                continue;
            }

            $filename = basename( $relative_path );
            if ( strlen( $filename ) > 5 && strpos( $combined, $filename ) !== false ) {
                $this->mark_used( $relative_path );
            }
        }
    }
}
