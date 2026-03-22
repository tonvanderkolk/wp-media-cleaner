<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$scan_result = get_transient( 'wmc_scan_result' );
if ( $scan_result !== false ) {
    delete_transient( 'wmc_scan_result' );
}

$review_files = WMC_Mover::get_review_files();
$review_count = count( $review_files );
$review_size  = WMC_Mover::get_review_total_size();
?>

<div class="wmc-panel">
    <h2>Scan uitvoeren</h2>
    <p>
        Scan de uploads-map op bestanden die nergens op je website worden gebruikt.
        Ongebruikte bestanden worden verplaatst naar de map <code>te-beoordelen</code>
        waar je ze kunt bekijken voordat je ze verwijdert.
    </p>

    <?php if ( $scan_result !== false ) : ?>
        <div class="notice notice-success inline">
            <p>
                Scan voltooid.
                <strong><?php echo intval( $scan_result['moved'] ); ?></strong> bestand(en) verplaatst naar te-beoordelen
                (<?php echo esc_html( size_format( $scan_result['files_size'] ) ); ?>).
                <?php if ( ! empty( $scan_result['unattached'] ) ) : ?>
                    <strong><?php echo intval( $scan_result['unattached'] ); ?></strong> ongebruikt(e) Media Library item(s) gevonden
                    (<?php echo esc_html( size_format( $scan_result['unattached_size'] ) ); ?>).
                <?php endif; ?>
                <?php if ( ! empty( $scan_result['failed'] ) ) : ?>
                    <strong><?php echo intval( $scan_result['failed'] ); ?></strong> bestand(en) konden niet worden verplaatst.
                <?php endif; ?>
            </p>
            <p>
                <strong>Totaal gevonden rommel: <?php echo esc_html( size_format( $scan_result['total_size'] ) ); ?></strong>
            </p>
        </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field( 'wmc_scan_nonce' ); ?>
        <input type="hidden" name="wmc_action" value="scan">
        <p>
            <button type="submit" class="button button-primary button-hero" id="wmc-scan-btn">
                Scan starten
            </button>
        </p>
    </form>

    <hr>

    <h3>Samenvatting</h3>
    <table class="widefat fixed striped">
        <tbody>
            <tr>
                <td><strong>Bestanden in te-beoordelen</strong></td>
                <td>
                    <?php echo intval( $review_count ); ?>
                    (<?php echo esc_html( size_format( $review_size ) ); ?>)
                    <?php if ( $review_count > 0 ) : ?>
                        &mdash; <a href="<?php echo esc_url( admin_url( 'tools.php?page=wp-media-cleaner&tab=review' ) ); ?>">Bekijken</a>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Totaal scans uitgevoerd</strong></td>
                <td><?php echo intval( WMC_Logger::count_entries( 'scan' ) ); ?></td>
            </tr>
            <tr>
                <td><strong>Totaal bestanden verwijderd</strong></td>
                <td><?php echo intval( WMC_Logger::count_entries( 'delete' ) ); ?></td>
            </tr>
            <tr>
                <td><strong>Totaal bestanden hersteld</strong></td>
                <td><?php echo intval( WMC_Logger::count_entries( 'restore' ) ); ?></td>
            </tr>
        </tbody>
    </table>
</div>
