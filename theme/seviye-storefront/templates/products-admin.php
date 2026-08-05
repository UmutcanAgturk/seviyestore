<?php

/**
 * Ürünler - the catalog LIST page (/admin/urunler, /sube/urunler, see
 * inc/zones.php) rather than a section inside the big /admin or /sube
 * dashboard, mirroring templates/orders-admin.php's own split-out
 * ("Sipariş Yönetimi"). Reached at all already implies inc/zones.php's own
 * capability check (scp_manage_products / scp_view_products) passed - see
 * the render branch there.
 *
 * All PER-PRODUCT editing (name/price/stock/image/category/variants/price
 * rules) now lives on its own page instead - "ürüne tıklandığında o
 * ürünün düzenleme sayfası gelsin" - see templates/product-edit.php and
 * scp_admin_product_edit_path()/scp_admin_product_new_path(). This list
 * keeps only the per-branch active/passive toggle grid (the "Durum"
 * column below): a quick visibility flip a Şube Müdürü/Genel Merkez may
 * want without leaving the list, not really "editing the product" the way
 * everything on the edit page is.
 *
 * The trailing "Detay"/"Düzenle" column is UNCONDITIONAL (unlike
 * Oluşturan/Durum, which stay scp_manage_products-only) - a
 * scp_view_products-only viewer (Muhasebe/Depo/Sistem) can open any
 * product's own page read-only too ("ürünleri görebiliyorum ama
 * tıklanacak bir yer yok" fix), they just never get the extra manage-only
 * columns/buttons around it. See assets/js/products-panel.js.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$scp_can_manage_products = current_user_can('scp_manage_products');

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Ürünler', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-products-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('Ürünler', 'seviye-storefront'); ?></h2>
            <?php if ($scp_can_manage_products) : ?>
                <a href="<?php echo esc_url(scp_admin_product_new_path()); ?>" class="scp-btn">
                    <?php esc_html_e('Yeni Ürün', 'seviye-storefront'); ?>
                </a>
            <?php endif; ?>
        </div>

        <p class="scp-form__hint">
            <?php esc_html_e(
                'Ürünler ortak katalogdadır. Genel Merkez ürünleri her şubede görünür; bir şubenin kendi oluşturduğu ürün yalnızca o şube ve o şubenin velilerine/öğrencilerine görünür. Genel Merkez ürünlerine bir şube kendi fiyatını verebilir ama Genel Merkez\'in belirlediği fiyatın altına inemez.',
                'seviye-storefront'
            ); ?>
        </p>

        <p class="scp-status" data-scp-products-status></p>

        <div class="scp-table-wrapper">
            <table class="scp-table">
                <thead>
                    <tr>
                        <th></th>
                        <th><?php esc_html_e('ID', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Ürün', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Fiyat (TRY)', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Kategori', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Stok', 'seviye-storefront'); ?></th>
                        <?php if ($scp_can_manage_products) : ?>
                            <th><?php esc_html_e('Oluşturan', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                        <?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-scp-products-body></tbody>
            </table>
        </div>

        <?php if ($scp_can_manage_products) : ?>
            <div class="scp-card scp-card--nested" data-scp-product-branches-panel hidden>
                <div class="scp-card__header">
                    <h3><?php esc_html_e('Şube Bazlı Durum', 'seviye-storefront'); ?></h3>
                </div>
                <p class="scp-form__hint">
                    <?php esc_html_e(
                        'Listelenmeyen bir şube bu ürün için varsayılan olarak aktiftir.',
                        'seviye-storefront'
                    ); ?>
                </p>
                <ul class="scp-list" data-scp-product-branches-list></ul>
                <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-close-product-branches>
                    <?php esc_html_e('Kapat', 'seviye-storefront'); ?>
                </button>
            </div>
        <?php endif; ?>
    </section>
</div>
