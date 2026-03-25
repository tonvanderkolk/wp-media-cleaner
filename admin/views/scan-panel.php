<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$scan_result = get_transient( 'wmc_scan_result' );
if ( $scan_result !== false ) {
    delete_transient( 'wmc_scan_result' );
}

$settings_saved = isset( $_GET['saved'] ) && $_GET['saved'] === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

    <?php if ( $settings_saved ) : ?>
        <div class="notice notice-success inline">
            <p>Uitgesloten mappen opgeslagen.</p>
        </div>
    <?php endif; ?>

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

    <h3>Uitgesloten mappen</h3>
    <p>
        Voeg hier submappen van <code>wp-content/uploads/</code> toe die de scanner moet overslaan.
        Eén mapnaam per regel. Standaard plugin-mappen worden altijd overgeslagen.
    </p>
    <form method="post">
        <?php wp_nonce_field( 'wmc_settings_nonce' ); ?>
        <input type="hidden" name="wmc_action" value="save_excluded_dirs">
        <?php
        $custom_dirs = get_option( 'wmc_custom_excluded_dirs', array() );
        $custom_text = is_array( $custom_dirs ) ? implode( "\n", $custom_dirs ) : '';
        ?>
        <textarea name="wmc_custom_excluded_dirs" rows="5" class="large-text code" placeholder="bijv. mm-artiesten&#10;mm-intake"><?php echo esc_textarea( $custom_text ); ?></textarea>
        <p>
            <button type="submit" class="button">Opslaan</button>
        </p>
        <?php
        $defaults = WMC_Scanner::get_default_excluded_dirs();
        if ( ! empty( $defaults ) ) : ?>
            <details>
                <summary>Standaard uitgesloten mappen (<?php echo count( $defaults ); ?>)</summary>
                <ul style="margin-top: 0.5em;">
                    <?php foreach ( $defaults as $dir ) : ?>
                        <li><code><?php echo esc_html( $dir ); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
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
