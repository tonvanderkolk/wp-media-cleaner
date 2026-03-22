<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMC_Admin_Page {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    /**
     * Register the admin menu page.
     */
    public function add_menu_page() {
        add_management_page(
            'WP Media Cleaner',
            'Media Cleaner',
            'manage_options',
            'wp-media-cleaner',
            array( $this, 'render_page' )
        );
    }

    /**
     * Enqueue admin CSS and JS.
     *
     * @param string $hook The admin page hook suffix.
     */
    public function enqueue_assets( $hook ) {
        if ( 'tools_page_wp-media-cleaner' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'wmc-admin',
            WMC_PLUGIN_URL . 'admin/assets/admin.css',
            array(),
            WMC_VERSION
        );

        wp_enqueue_script(
            'wmc-admin',
            WMC_PLUGIN_URL . 'admin/assets/admin.js',
            array(),
            WMC_VERSION,
            true
        );
    }

    /**
     * Render the admin page with tabs.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Geen toegang.' );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab is a navigation parameter, validated against allowlist below.
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'scan';
        if ( ! in_array( $tab, array( 'scan', 'review', 'log' ), true ) ) {
            $tab = 'scan';
        }
        ?>
        <div class="wrap">
            <h1>WP Media Cleaner</h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'tools.php?page=wp-media-cleaner&tab=scan' ) ); ?>"
                   class="nav-tab <?php echo $tab === 'scan' ? 'nav-tab-active' : ''; ?>">
                    Scan
                </a>
                <a href="<?php echo esc_url( admin_url( 'tools.php?page=wp-media-cleaner&tab=review' ) ); ?>"
                   class="nav-tab <?php echo $tab === 'review' ? 'nav-tab-active' : ''; ?>">
                    Te beoordelen
                </a>
                <a href="<?php echo esc_url( admin_url( 'tools.php?page=wp-media-cleaner&tab=log' ) ); ?>"
                   class="nav-tab <?php echo $tab === 'log' ? 'nav-tab-active' : ''; ?>">
                    Logboek
                </a>
            </nav>

            <div class="wmc-tab-content">
                <?php
                switch ( $tab ) {
                    case 'review':
                        include WMC_PLUGIN_DIR . 'admin/views/review-list.php';
                        break;
                    case 'log':
                        include WMC_PLUGIN_DIR . 'admin/views/log-view.php';
                        break;
                    default:
                        include WMC_PLUGIN_DIR . 'admin/views/scan-panel.php';
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Handle form submissions (scan, move, restore, delete, download, clear log).
     */
    public function handle_actions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Handle scan + move.
        if ( isset( $_POST['wmc_action'] ) && $_POST['wmc_action'] === 'scan' ) {
            check_admin_referer( 'wmc_scan_nonce' );

            $scanner = new WMC_Scanner();
            $scan    = $scanner->scan();

            $move_result = array( 'moved' => 0, 'failed' => 0 );
            if ( ! empty( $scan['files'] ) ) {
                $move_result = WMC_Mover::move_batch_to_review( $scan['files'] );
            }

            $scan_result = array(
                'moved'           => $move_result['moved'],
                'failed'          => $move_result['failed'],
                'unattached'      => count( $scan['unattached'] ),
                'files_size'      => $scan['files_size'],
                'unattached_size' => $scan['unattached_size'],
                'total_size'      => $scan['total_size'],
            );

            // Store unattached list for the review page.
            if ( ! empty( $scan['unattached'] ) ) {
                update_option( 'wmc_unattached_items', $scan['unattached'], false );
            } else {
                delete_option( 'wmc_unattached_items' );
            }

            set_transient( 'wmc_scan_result', $scan_result, 60 );
            wp_safe_redirect( admin_url( 'tools.php?page=wp-media-cleaner&tab=scan&scanned=1' ) );
            exit;
        }

        // Handle restore.
        if ( isset( $_POST['wmc_action'] ) && $_POST['wmc_action'] === 'restore' ) {
            check_admin_referer( 'wmc_review_nonce' );

            $files = isset( $_POST['wmc_files'] ) ? array_map( 'sanitize_text_field', $_POST['wmc_files'] ) : array();
            $restored = 0;
            foreach ( $files as $file ) {
                if ( WMC_Mover::restore( $file ) ) {
                    $restored++;
                }
            }

            set_transient( 'wmc_review_result', array( 'action' => 'restore', 'count' => $restored ), 60 );
            wp_safe_redirect( admin_url( 'tools.php?page=wp-media-cleaner&tab=review&done=1' ) );
            exit;
        }

        // Handle delete.
        if ( isset( $_POST['wmc_action'] ) && $_POST['wmc_action'] === 'delete' ) {
            check_admin_referer( 'wmc_review_nonce' );

            $files = isset( $_POST['wmc_files'] ) ? array_map( 'sanitize_text_field', $_POST['wmc_files'] ) : array();
            $result = WMC_Cleaner::delete_batch( $files );

            set_transient( 'wmc_review_result', array( 'action' => 'delete', 'count' => $result['deleted'] ), 60 );
            wp_safe_redirect( admin_url( 'tools.php?page=wp-media-cleaner&tab=review&done=1' ) );
            exit;
        }

        // Handle download as zip.
        if ( isset( $_POST['wmc_action'] ) && $_POST['wmc_action'] === 'download' ) {
            check_admin_referer( 'wmc_review_nonce' );

            $files    = isset( $_POST['wmc_files'] ) ? array_map( 'sanitize_text_field', $_POST['wmc_files'] ) : array();
            $zip_path = WMC_Cleaner::create_zip( $files );

            if ( $zip_path ) {
                WMC_Cleaner::send_zip_download( $zip_path );
            }

            wp_safe_redirect( admin_url( 'tools.php?page=wp-media-cleaner&tab=review' ) );
            exit;
        }

        // Handle delete unattached Media Library items.
        if ( isset( $_POST['wmc_action'] ) && $_POST['wmc_action'] === 'delete_unattached' ) {
            check_admin_referer( 'wmc_review_nonce' );

            $ids     = isset( $_POST['wmc_unattached'] ) ? array_map( 'intval', $_POST['wmc_unattached'] ) : array();
            $deleted = 0;

            foreach ( $ids as $id ) {
                if ( $id > 0 && wp_delete_attachment( $id, true ) ) {
                    $file = get_post_meta( $id, '_wp_attached_file', true );
                    WMC_Logger::log( 'delete', $file ?: "attachment #{$id}", 'Media Library item verwijderd.' );
                    $deleted++;
                }
            }

            // Update the stored unattached list.
            $remaining = get_option( 'wmc_unattached_items', array() );
            $remaining = array_filter( $remaining, function( $item ) use ( $ids ) {
                return ! in_array( (int) $item['id'], $ids, true );
            } );

            if ( empty( $remaining ) ) {
                delete_option( 'wmc_unattached_items' );
            } else {
                update_option( 'wmc_unattached_items', array_values( $remaining ), false );
            }

            set_transient( 'wmc_review_result', array( 'action' => 'delete_unattached', 'count' => $deleted ), 60 );
            wp_safe_redirect( admin_url( 'tools.php?page=wp-media-cleaner&tab=review&done=1' ) );
            exit;
        }

        // Handle clear log.
        if ( isset( $_POST['wmc_action'] ) && $_POST['wmc_action'] === 'clear_log' ) {
            check_admin_referer( 'wmc_log_nonce' );

            WMC_Logger::clear();

            wp_safe_redirect( admin_url( 'tools.php?page=wp-media-cleaner&tab=log&cleared=1' ) );
            exit;
        }
    }
}

new WMC_Admin_Page();
