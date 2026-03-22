<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WMC_Logger {

    /**
     * Create the custom log table.
     */
    public static function create_table() {
        global $wpdb;

        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            action     VARCHAR(50)  NOT NULL,
            file_path  TEXT         NOT NULL,
            details    TEXT         NULL,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action (action),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get the full table name including prefix.
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wmc_log';
    }

    /**
     * Log an action.
     *
     * @param string $action    Action type: scan, move, restore, delete, download.
     * @param string $file_path Relative path of the file.
     * @param string $details   Optional extra details.
     */
    public static function log( $action, $file_path, $details = '' ) {
        global $wpdb;

        $wpdb->insert(
            self::table_name(),
            array(
                'action'    => sanitize_text_field( $action ),
                'file_path' => sanitize_text_field( $file_path ),
                'details'   => sanitize_text_field( $details ),
            ),
            array( '%s', '%s', '%s' )
        );
    }

    /**
     * Get log entries with optional filters.
     *
     * @param array $args Optional. Filters: action, per_page, offset, orderby, order.
     * @return array
     */
    public static function get_entries( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'action'   => '',
            'per_page' => 50,
            'offset'   => 0,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );

        $table = self::table_name();
        $where = '';

        if ( ! empty( $args['action'] ) ) {
            $where = $wpdb->prepare( ' WHERE action = %s', $args['action'] );
        }

        $allowed_orderby = array( 'id', 'action', 'file_path', 'created_at' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}{$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                intval( $args['per_page'] ),
                intval( $args['offset'] )
            )
        );
    }

    /**
     * Count log entries with optional action filter.
     *
     * @param string $action Optional action filter.
     * @return int
     */
    public static function count_entries( $action = '' ) {
        global $wpdb;

        $table = self::table_name();

        if ( ! empty( $action ) ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE action = %s", $action )
            );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    /**
     * Clear all log entries.
     */
    public static function clear() {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE " . self::table_name() );
    }

    /**
     * Drop the log table (used on uninstall).
     */
    public static function drop_table() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS " . self::table_name() );
    }
}
