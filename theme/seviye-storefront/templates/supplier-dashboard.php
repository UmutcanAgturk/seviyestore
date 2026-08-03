<?php

/**
 * "Tedarikçi portalı" (/tedarikci, see inc/zones.php) - a supplier's own
 * purchase orders, read-only except for "gönderildi" (shipped). Reached at
 * all already implies inc/access-gate.php's supplier-link check passed -
 * same "reaching it at all implies the gate passed" reasoning as
 * templates/parent-dashboard.php's /profilim. Data comes from
 * plugin/seviye-depo/src/Http/PurchaseOrdersRestController.php's
 * seviye/v1/depo/purchase-orders/mine and /{id}/mark-shipped endpoints
 * (assets/js/supplier-panel.js).
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Satın Alma Siparişlerim', 'seviye-storefront'); ?></h1>

    <section class="scp-card" id="scp-supplier-panel">
        <p class="scp-status" data-scp-supplier-status></p>
        <div class="scp-table-wrapper">
            <table class="scp-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Sipariş No', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Beklenen Tarih', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Kargo Bilgisi', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Kalemler', 'seviye-storefront'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-scp-supplier-orders-body></tbody>
            </table>
        </div>
    </section>
</div>
