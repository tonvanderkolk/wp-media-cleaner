<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation parameters validated against allowlist.
$current_action = isset( $_GET['log_action'] ) ? sanitize_text_field( $_GET['log_action'] ) : '';
if ( ! in_array( $current_action, array( '', 'scan', 'move', 'restore', 'delete', 'download' ), true ) ) {
    $current_action = '';
}
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$paged          = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page       = 50;
$offset         = ( $paged - 1 ) * $per_page;

$entries = WMC_Logger::get_entries( array(
    'action'   => $current_action,
    'per_page' => $per_page,
    'offset'   => $offset,
) );

$total_entries = WMC_Logger::count_entries( $current_action );
$total_pages   = ceil( $total_entries / $per_page );

$cleared = isset( $_GET['cleared'] ) && $_GET['cleared'] === '1';
?>

<div class="wmc-panel">
    <h2>Logboek</h2>

    <?php if ( $cleared ) : ?>
        <div class="notice notice-success inline">
            <p>Logboek is gewist.</p>
        </div>
    <?php endif; ?>

    <div class="wmc-log-filters">
        <strong>Filter:</strong>
        <?php
        $filters = array(
            ''        => 'Alles',
            'scan'    => 'Scans',
            'move'    => 'Verplaatst',
            'restore' => 'Hersteld',
            'delete'  => 'Verwijderd',
            'download'=> 'Downloads',
        );
        foreach ( $filters as $key => $label ) :
            $url   = admin_url( 'tools.php?page=wp-media-cleaner&tab=log' );
            if ( $key ) {
                $url = add_query_arg( 'log_action', $key, $url );
            }
            $class = $current_action === $key ? 'current' : '';
            ?>
            <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>">
                <?php echo esc_html( $label ); ?>
            </a>
            <?php if ( $key !== 'download' ) echo ' | '; ?>
        <?php endforeach; ?>
    </div>

    <?php if ( empty( $entries ) ) : ?>
        <div class="notice notice-info inline">
            <p>Geen logregels gevonden.</p>
        </div>
    <?php else : ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th class="wmc-col-date">Datum &amp; tijd</th>
                    <th class="wmc-col-action">Actie</th>
                    <th>Bestand</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $entries as $entry ) : ?>
                    <tr>
                        <td class="wmc-col-date">
                            <?php echo esc_html( date_i18n( 'd-m-Y H:i:s', strtotime( $entry->created_at ) ) ); ?>
                        </td>
                        <td class="wmc-col-action">
                            <span class="wmc-badge wmc-badge-<?php echo esc_attr( $entry->action ); ?>">
                                <?php echo esc_html( $entry->action ); ?>
                            </span>
                        </td>
                        <td><code><?php echo esc_html( $entry->file_path ); ?></code></td>
                        <td><?php echo esc_html( $entry->details ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    $base_url = admin_url( 'tools.php?page=wp-media-cleaner&tab=log' );
                    if ( $current_action ) {
                        $base_url = add_query_arg( 'log_action', $current_action, $base_url );
                    }

                    echo paginate_links( array(
                        'base'      => add_query_arg( 'paged', '%#%', $base_url ),
                        'format'    => '',
                        'current'   => $paged,
                        'total'     => $total_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    ) );
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ( $total_entries > 0 ) : ?>
        <form method="post" class="wmc-clear-log-form">
            <?php wp_nonce_field( 'wmc_log_nonce' ); ?>
            <input type="hidden" name="wmc_action" value="clear_log">
            <p>
                <button type="submit" class="button" onclick="return confirm('Weet je zeker dat je het logboek wilt wissen?');">
                    Logboek wissen
                </button>
            </p>
        </form>
    <?php endif; ?>
</div>
