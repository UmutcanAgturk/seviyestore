<?php

/**
 * Sipariş Yönetimi - a standalone page (/admin/siparisler, /sube/siparisler,
 * see inc/zones.php) rather than a section inside the big /admin or /sube
 * dashboard, per explicit request. Reached at all already implies
 * inc/zones.php's own capability check (scp_view_orders /
 * scp_view_own_branch_orders) passed - see the render branch there.
 * Data comes from
 * Seviye\Commerce\Http\AdminOrdersRestController's seviye/v1/commerce/orders
 * endpoint (assets/js/admin-orders-panel.js).
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Siparişler', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-admin-orders-panel">
        <p class="scp-status" data-scp-admin-orders-status></p>

        <nav
            class="scp-status-tabs"
            data-scp-admin-orders-status-tabs
            aria-label="<?php esc_attr_e('Durum', 'seviye-storefront'); ?>"
        ></nav>

        <div class="scp-bulk-actions" data-scp-admin-orders-bulk-actions hidden></div>

        <div class="scp-view-toggle" role="group" aria-label="<?php esc_attr_e('Görünüm', 'seviye-storefront'); ?>">
            <button type="button" class="scp-btn scp-btn--ghost is-active" data-scp-admin-orders-view="list">
                <?php esc_html_e('Liste', 'seviye-storefront'); ?>
            </button>
            <button type="button" class="scp-btn scp-btn--ghost" data-scp-admin-orders-view="calendar">
                <?php esc_html_e('Takvim', 'seviye-storefront'); ?>
            </button>
        </div>

        <form class="scp-form scp-form--inline" data-scp-admin-orders-form>
            <label data-scp-admin-orders-branch-field hidden>
                <span><?php esc_html_e('Şube', 'seviye-storefront'); ?></span>
                <select></select>
            </label>
            <label>
                <span><?php esc_html_e('Ürün ID', 'seviye-storefront'); ?></span>
                <input type="number" min="1" name="product_id">
            </label>
            <label>
                <span><?php esc_html_e('Öğrenci ID', 'seviye-storefront'); ?></span>
                <input type="number" min="1" name="student_id">
            </label>
            <label>
                <span><?php esc_html_e('Durum', 'seviye-storefront'); ?></span>
                <select name="status">
                    <option value=""><?php esc_html_e('Tümü', 'seviye-storefront'); ?></option>
                    <option value="pending"><?php esc_html_e('Ödeme Bekliyor', 'seviye-storefront'); ?></option>
                    <option value="processing"><?php esc_html_e('Hazırlanıyor', 'seviye-storefront'); ?></option>
                    <option value="completed"><?php esc_html_e('Tamamlandı', 'seviye-storefront'); ?></option>
                    <option value="on-hold"><?php esc_html_e('Beklemede', 'seviye-storefront'); ?></option>
                    <option value="cancelled"><?php esc_html_e('İptal Edildi', 'seviye-storefront'); ?></option>
                    <option value="refunded"><?php esc_html_e('İade Edildi', 'seviye-storefront'); ?></option>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('Başlangıç', 'seviye-storefront'); ?></span>
                <input type="date" name="from">
            </label>
            <label>
                <span><?php esc_html_e('Bitiş', 'seviye-storefront'); ?></span>
                <input type="date" name="to">
            </label>
            <label>
                <span><?php esc_html_e('Ara (Sipariş No / Veli)', 'seviye-storefront'); ?></span>
                <input type="text" name="search">
            </label>

            <div class="scp-form__actions">
                <button type="submit" class="scp-btn"><?php esc_html_e('Getir', 'seviye-storefront'); ?></button>
                <button type="button" class="scp-btn scp-btn--ghost" data-scp-admin-orders-export>
                    <?php esc_html_e('CSV İndir', 'seviye-storefront'); ?>
                </button>
            </div>
        </form>

        <div class="scp-orders-list" data-scp-admin-orders-list></div>

        <div class="scp-order-calendar" data-scp-admin-orders-calendar hidden>
            <div class="scp-order-calendar__header">
                <button type="button" class="scp-btn scp-btn--ghost" data-scp-admin-orders-calendar-prev>‹</button>
                <span data-scp-admin-orders-calendar-title></span>
                <button type="button" class="scp-btn scp-btn--ghost" data-scp-admin-orders-calendar-next>›</button>
            </div>
            <div class="scp-order-calendar__weekdays">
                <span><?php esc_html_e('Pzt', 'seviye-storefront'); ?></span>
                <span><?php esc_html_e('Sal', 'seviye-storefront'); ?></span>
                <span><?php esc_html_e('Çar', 'seviye-storefront'); ?></span>
                <span><?php esc_html_e('Per', 'seviye-storefront'); ?></span>
                <span><?php esc_html_e('Cum', 'seviye-storefront'); ?></span>
                <span><?php esc_html_e('Cmt', 'seviye-storefront'); ?></span>
                <span><?php esc_html_e('Paz', 'seviye-storefront'); ?></span>
            </div>
            <div class="scp-order-calendar__grid" data-scp-admin-orders-calendar-grid></div>
            <div class="scp-order-calendar__day-detail" data-scp-admin-orders-calendar-day-detail hidden>
                <div class="scp-card__header">
                    <h3 data-scp-admin-orders-calendar-day-title></h3>
                    <button type="button" class="scp-btn scp-btn--ghost" data-scp-admin-orders-calendar-day-close>
                        <?php esc_html_e('Kapat', 'seviye-storefront'); ?>
                    </button>
                </div>
                <div data-scp-admin-orders-calendar-day-list></div>
            </div>
        </div>
    </section>
</div>
