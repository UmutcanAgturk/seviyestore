<?php

/**
 * Vergi Oranları - its own page (/admin/vergi-oranlari, see inc/zones.php's
 * scp_menu_pages()), HQ-only (scp_manage_tax_rates - see
 * plugin/seviye-commerce/src/Rbac/ProductCapability.php). "Ürün ürün
 * vergilendirme": HQ defines named tax rate classes here (e.g. "Standart
 * KDV %20"); the Ürünler edit page's own "Vergi Oranı" dropdown then lets
 * whoever can already edit a product pick one of these for it. Markup
 * style copied from templates/coupons-admin.php.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Vergi Oranları', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-tax-rates-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('Vergi Oranları', 'seviye-storefront'); ?></h2>
            <button type="button" class="scp-btn" data-scp-new-tax-rate>
                <?php esc_html_e('Yeni Oran', 'seviye-storefront'); ?>
            </button>
        </div>

        <p class="scp-hint">
            <?php esc_html_e(
                'Burada tanımladığınız oranlar, Ürünler sayfasındaki "Vergi Oranı" seçeneğinde görünür - her ürüne hangi oranın uygulanacağını orada seçersiniz. "Standart" WooCommerce\'in her zaman var olan varsayılan sınıfıdır, silinemez.',
                'seviye-storefront'
            ); ?>
        </p>

        <p class="scp-status" data-scp-tax-rates-status></p>

        <div class="scp-table-wrapper">
            <table class="scp-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('İsim', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Oran (%)', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Kullanımda mı?', 'seviye-storefront'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-scp-tax-rates-body></tbody>
            </table>
        </div>

        <form class="scp-form" data-scp-tax-rate-form hidden>
            <input type="hidden" name="slug">

            <div class="scp-form__row">
                <label data-scp-tax-rate-name-field>
                    <span><?php esc_html_e('İsim', 'seviye-storefront'); ?></span>
                    <input type="text" name="name" placeholder="<?php esc_attr_e('ör. İndirimli KDV', 'seviye-storefront'); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('Oran (%)', 'seviye-storefront'); ?></span>
                    <input type="number" name="percent" min="0" step="0.01" required>
                </label>
            </div>

            <div class="scp-form__actions">
                <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-tax-rate>
                    <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                </button>
            </div>
        </form>
    </section>
</div>
