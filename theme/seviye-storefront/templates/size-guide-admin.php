<?php

/**
 * Beden Rehberi - its own page (/admin/beden-rehberi, see inc/zones.php's
 * scp_menu_pages()), HQ-only (scp_manage_size_guide - see
 * plugin/seviye-commerce/src/Rbac/ProductCapability.php). Tek bir yaş/boy →
 * beden tablosu tanımlanır (mağaza geneli, ürün başına değil) - ürün
 * sayfasındaki tetikleyici (inc/woocommerce.php'deki
 * scp_render_size_guide_trigger()) bunu velilere gösterir.
 *
 * Vergi Oranları'ndan farklı olarak satırların doğal bir id/slug'ı yok, bu
 * yüzden tek tek POST/PUT/DELETE yerine tüm liste tek bir PUT ile
 * kaydediliyor - bkz. Http\SizeGuideRestController'ın kendi docblock'u.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Beden Rehberi', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-size-guide-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('Beden Rehberi', 'seviye-storefront'); ?></h2>
            <button type="button" class="scp-btn" data-scp-add-size-guide-row>
                <?php esc_html_e('Yeni Satır', 'seviye-storefront'); ?>
            </button>
        </div>

        <p class="scp-hint">
            <?php esc_html_e(
                'Burada tanımladığınız yaş/boy aralıkları, ürün sayfasındaki "Beden Rehberi" penceresinde velilere gösterilir. Bir alanı boş bırakmak "sınırsız" anlamına gelir.',
                'seviye-storefront'
            ); ?>
        </p>

        <p class="scp-status" data-scp-size-guide-status></p>

        <div class="scp-table-wrapper">
            <table class="scp-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Beden', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Yaş (min)', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Yaş (max)', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Boy cm (min)', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Boy cm (max)', 'seviye-storefront'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-scp-size-guide-body></tbody>
            </table>
        </div>

        <div class="scp-form__actions">
            <button type="button" class="scp-btn" data-scp-save-size-guide>
                <?php esc_html_e('Kaydet', 'seviye-storefront'); ?>
            </button>
        </div>
    </section>
</div>
