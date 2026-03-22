<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$review_result = get_transient( 'wmc_review_result' );
if ( $review_result !== false ) {
    delete_transient( 'wmc_review_result' );
}

$files       = WMC_Mover::get_review_files();
$unattached  = get_option( 'wmc_unattached_items', array() );
?>

<div class="wmc-panel">
    <h2>Te beoordelen bestanden</h2>

    <?php if ( $review_result !== false ) : ?>
        <div class="notice notice-success inline">
            <p>
                <?php if ( $review_result['action'] === 'restore' ) : ?>
                    <strong><?php echo intval( $review_result['count'] ); ?></strong> bestand(en) hersteld.
                <?php elseif ( $review_result['action'] === 'delete' ) : ?>
                    <strong><?php echo intval( $review_result['count'] ); ?></strong> bestand(en) definitief verwijderd.
                <?php elseif ( $review_result['action'] === 'delete_unattached' ) : ?>
                    <strong><?php echo intval( $review_result['count'] ); ?></strong> Media Library item(s) verwijderd.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ( empty( $files ) && empty( $unattached ) ) : ?>
        <div class="notice notice-info inline">
            <p>Er zijn geen bestanden om te beoordelen. Voer eerst een scan uit.</p>
        </div>
    <?php endif; ?>

    <!-- ===================== Ongebruikte bestanden op schijf ===================== -->
    <?php if ( ! empty( $files ) ) : ?>
        <h3>Ongebruikte bestanden op schijf</h3>
        <p>Deze bestanden staan in de uploads-map maar worden nergens op de website gebruikt.</p>

        <form method="post" id="wmc-review-form">
            <?php wp_nonce_field( 'wmc_review_nonce' ); ?>
            <input type="hidden" name="wmc_action" id="wmc-review-action" value="">

            <div class="wmc-bulk-actions">
                <label>
                    <input type="checkbox" id="wmc-select-all"> Alles selecteren
                </label>
                <span class="wmc-selected-count">0 geselecteerd</span>
                <button type="submit" class="button" data-action="restore" title="Herstel geselecteerde bestanden">
                    Herstellen
                </button>
                <button type="submit" class="button" data-action="download" title="Download geselecteerde bestanden als zip">
                    Downloaden
                </button>
                <button type="submit" class="button button-link-delete" data-action="delete" title="Verwijder geselecteerde bestanden definitief">
                    Verwijderen
                </button>
            </div>

            <table class="widefat fixed striped wmc-review-table">
                <thead>
                    <tr>
                        <th class="check-column"><span class="screen-reader-text">Selecteer</span></th>
                        <th>Bestand</th>
                        <th class="wmc-col-size">Grootte</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $files as $file ) : ?>
                        <tr>
                            <td class="check-column">
                                <input type="checkbox" name="wmc_files[]"
                                       value="<?php echo esc_attr( $file ); ?>"
                                       class="wmc-file-checkbox">
                            </td>
                            <td>
                                <code><?php echo esc_html( $file ); ?></code>
                            </td>
                            <td class="wmc-col-size">
                                <?php echo esc_html( WMC_Mover::get_review_file_size( $file ) ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="wmc-bulk-actions wmc-bulk-actions-bottom">
                <button type="submit" class="button" data-action="restore">Herstellen</button>
                <button type="submit" class="button" data-action="download">Downloaden</button>
                <button type="submit" class="button button-link-delete" data-action="delete">Verwijderen</button>
            </div>
        </form>
    <?php endif; ?>

    <!-- ===================== Ongebruikte Media Library items ===================== -->
    <?php if ( ! empty( $unattached ) ) : ?>
        <h3>Ongebruikte Media Library items</h3>
        <p>
            Deze items staan in de Media Library maar worden nergens op de website gebruikt
            (niet in pagina's, posts, widgets, WooCommerce, of andere instellingen).
        </p>

        <form method="post" id="wmc-unattached-form">
            <?php wp_nonce_field( 'wmc_review_nonce' ); ?>
            <input type="hidden" name="wmc_action" value="delete_unattached">

            <div class="wmc-bulk-actions">
                <label>
                    <input type="checkbox" id="wmc-select-all-unattached"> Alles selecteren
                </label>
                <span class="wmc-selected-count-unattached">0 geselecteerd</span>
                <button type="submit" class="button button-link-delete"
                        onclick="return confirm('Weet je zeker dat je de geselecteerde Media Library items definitief wilt verwijderen? De bestanden worden ook van schijf verwijderd.');">
                    Verwijderen uit Media Library
                </button>
            </div>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th class="check-column"><span class="screen-reader-text">Selecteer</span></th>
                        <th>Bestand</th>
                        <th>Titel</th>
                        <th class="wmc-col-action">Bekijken</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $unattached as $item ) : ?>
                        <tr>
                            <td class="check-column">
                                <input type="checkbox" name="wmc_unattached[]"
                                       value="<?php echo intval( $item['id'] ); ?>"
                                       class="wmc-unattached-checkbox">
                            </td>
                            <td>
                                <code><?php echo esc_html( $item['file'] ); ?></code>
                            </td>
                            <td>
                                <?php echo esc_html( $item['title'] ); ?>
                            </td>
                            <td class="wmc-col-action">
                                <a href="<?php echo esc_url( admin_url( 'upload.php?item=' . intval( $item['id'] ) ) ); ?>"
                                   target="_blank">Bekijken</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    <?php endif; ?>
</div>
