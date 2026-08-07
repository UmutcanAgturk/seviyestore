<?php

/**
 * Veli's own past orders (/siparislerim, see inc/zones.php). Reached at all
 * already implies inc/access-gate.php's role-zone check passed - see
 * templates/parent-dashboard.php's identical reasoning for /profilim.
 * Data comes from Seviye\Commerce\Http\OrdersRestController's
 * seviye/v1/commerce/orders/mine endpoint (assets/js/orders-panel.js).
 *
 * "Yıllık harcama özeti" - the toolbar below (year select + button) stays
 * `hidden` in markup; orders-panel.js only reveals it once at least one
 * order has loaded (a year select with nothing to summarize would be
 * confusing), then fills the select from the distinct years present in
 * the veli's own order history.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Siparişlerim', 'seviye-storefront'); ?></h1>

    <section class="scp-card" id="scp-orders-panel">
        <div class="scp-spending-summary-toolbar" data-scp-spending-summary-toolbar hidden>
            <label for="scp-spending-summary-year"><?php esc_html_e('Yıllık Harcama Özeti:', 'seviye-storefront'); ?></label>
            <select id="scp-spending-summary-year" data-scp-spending-summary-year></select>
            <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-spending-summary-button>
                <?php esc_html_e('Özeti İndir (Yazdır)', 'seviye-storefront'); ?>
            </button>
        </div>

        <p class="scp-status" data-scp-orders-status></p>
        <div class="scp-orders-list" data-scp-orders-list></div>
    </section>
</div>
