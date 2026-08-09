<?php

/**
 * Kampanya Kodları - its own page (/admin/kampanyalar, see inc/zones.php's
 * scp_menu_pages()) - "her bir menü için ayrı bir sayfa yap" (bölüm 65).
 * Reached at all already implies scp_manage_coupons AND the admin zone
 * passed - see scp_menu_pages(). Markup/ids/data-attributes moved here
 * verbatim from templates/zone.php so assets/js/coupons-panel.js keeps
 * working unchanged.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Kampanya Kodları', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-coupons-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('Kampanya Kodları', 'seviye-storefront'); ?></h2>
            <button type="button" class="scp-btn" data-scp-new-coupon>
                <?php esc_html_e('Yeni Kod', 'seviye-storefront'); ?>
            </button>
        </div>

        <p class="scp-status" data-scp-coupons-status></p>

        <div class="scp-table-wrapper">
            <table class="scp-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Kod', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('İndirim', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Kullanım', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Son Geçerlilik', 'seviye-storefront'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-scp-coupons-body></tbody>
            </table>
        </div>

        <form class="scp-form" data-scp-coupon-form hidden>
            <input type="hidden" name="id">

            <div class="scp-form__row">
                <label>
                    <span><?php esc_html_e('Kod', 'seviye-storefront'); ?></span>
                    <input type="text" name="code" required placeholder="OKUL2026">
                </label>
                <label>
                    <span><?php esc_html_e('İndirim Türü', 'seviye-storefront'); ?></span>
                    <select name="discount_type" required>
                        <option value="percent"><?php esc_html_e('Yüzde (%)', 'seviye-storefront'); ?></option>
                        <option value="fixed_cart">
                            <?php esc_html_e('Sabit Tutar (Sepet)', 'seviye-storefront'); ?>
                        </option>
                        <option value="fixed_product">
                            <?php esc_html_e('Sabit Tutar (Ürün)', 'seviye-storefront'); ?>
                        </option>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Tutar', 'seviye-storefront'); ?></span>
                    <input type="number" name="amount" min="0" step="0.01" required>
                </label>
            </div>

            <div class="scp-form__row">
                <label>
                    <span>
                        <?php esc_html_e('Kullanım Limiti (isteğe bağlı)', 'seviye-storefront'); ?>
                        <?php
                        $couponUsageLimitTip = __(
                            'Bu kod TÜM velilerin toplamda kaç kez kullanabileceğini belirler - kişi başına bir limit değildir.',
                            'seviye-storefront'
                        );
                        ?>
                        <span
                            class="scp-help-tip"
                            tabindex="0"
                            data-tip="<?php echo esc_attr($couponUsageLimitTip); ?>"
                            aria-label="<?php echo esc_attr($couponUsageLimitTip); ?>"
                        >?</span>
                    </span>
                    <input type="number" name="usage_limit" min="1" step="1" placeholder="1">
                </label>
                <label>
                    <span><?php esc_html_e('Son Geçerlilik Tarihi (isteğe bağlı)', 'seviye-storefront'); ?></span>
                    <input type="date" name="expiry_date">
                </label>
            </div>

            <div class="scp-form__row">
                <label>
                    <span><?php esc_html_e('Açıklama (isteğe bağlı)', 'seviye-storefront'); ?></span>
                    <input type="text" name="description">
                </label>
            </div>

            <div class="scp-form__actions">
                <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-coupon>
                    <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                </button>
            </div>
        </form>
    </section>
</div>
