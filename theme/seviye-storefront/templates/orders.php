<?php

/**
 * Veli's own past orders (/siparislerim, see inc/zones.php). Reached at all
 * already implies inc/access-gate.php's role-zone check passed - see
 * templates/parent-dashboard.php's identical reasoning for /profilim.
 * Data comes from Seviye\Commerce\Http\OrdersRestController's
 * seviye/v1/commerce/orders/mine endpoint (assets/js/orders-panel.js).
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Siparişlerim', 'seviye-storefront'); ?></h1>

    <section class="scp-card" id="scp-orders-panel">
        <p class="scp-status" data-scp-orders-status></p>
        <div class="scp-orders-list" data-scp-orders-list></div>
    </section>
</div>
