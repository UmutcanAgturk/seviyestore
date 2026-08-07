<?php

/**
 * Mağaza Vitrini - its own page (/admin/magaza-vitrini, see inc/zones.php's
 * scp_menu_pages()), HQ-only (scp_manage_shop_showcase - see
 * plugin/seviye-commerce/src/Rbac/ProductCapability.php). Mağazanın
 * (is_shop() - kategori arşivleri değil, onların kendi banner'ı var, bkz.
 * inc/woocommerce.php'deki scp_render_category_banner()) en üstünde
 * gösterilen isteğe bağlı hero bölümünü (başlık/alt başlık/arka plan
 * görseli) düzenler. Görsel yükleme kalıbı templates/branding-admin.php'den
 * birebir kopyalandı (aynı /wp/v2/media akışı), farkı bu sayfada görselin
 * yanında iki metin alanı da olması.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Mağaza Vitrini', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-shop-showcase-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('Mağaza Vitrini', 'seviye-storefront'); ?></h2>
        </div>

        <p class="scp-form__hint">
            <?php esc_html_e(
                'Mağaza ana sayfasının en üstünde gösterilen hero bölümü - başlık, alt başlık ve arka plan görseli. Hepsi boş bırakılırsa hiçbir şey gösterilmez.',
                'seviye-storefront'
            ); ?>
        </p>

        <p class="scp-status" data-scp-shop-showcase-status></p>

        <form class="scp-form" data-scp-shop-showcase-form>
            <img class="scp-branding-preview" data-scp-shop-showcase-preview hidden alt="">
            <label>
                <span><?php esc_html_e('Arka Plan Görseli', 'seviye-storefront'); ?></span>
                <input type="file" accept="image/*" data-scp-shop-showcase-image-input>
            </label>
            <div class="scp-form__actions">
                <button type="button" class="scp-btn scp-btn--ghost" data-scp-remove-shop-showcase-image>
                    <?php esc_html_e('Görseli Kaldır', 'seviye-storefront'); ?>
                </button>
            </div>

            <label>
                <span><?php esc_html_e('Başlık', 'seviye-storefront'); ?></span>
                <input type="text" name="heading" placeholder="<?php esc_attr_e('ör. Yeni Sezon Başladı', 'seviye-storefront'); ?>">
            </label>
            <label>
                <span><?php esc_html_e('Alt Başlık', 'seviye-storefront'); ?></span>
                <textarea name="subheading" rows="2" placeholder="<?php esc_attr_e('ör. Okul kıyafetlerinde %20 indirim', 'seviye-storefront'); ?>"></textarea>
            </label>

            <div class="scp-form__actions">
                <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
            </div>
        </form>
    </section>
</div>
