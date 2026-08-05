<?php

/**
 * Ürünler - a standalone page (/admin/urunler, /sube/urunler, see
 * inc/zones.php) rather than a section inside the big /admin or /sube
 * dashboard, mirroring templates/orders-admin.php's own split-out
 * ("Sipariş Yönetimi") for the exact same reason: a catalog with create/
 * edit/variant/price-rule sub-structures needs real room, not a card
 * competing for space with a dozen others. Reached at all already implies
 * inc/zones.php's own capability check (scp_manage_products /
 * scp_view_products) passed - see the render branch there. Markup and the
 * `#scp-products-panel` id are otherwise unchanged from when this lived in
 * templates/zone.php, so assets/js/products-panel.js keeps working as-is.
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
                <button type="button" class="scp-btn" data-scp-new-product>
                    <?php esc_html_e('Yeni Ürün', 'seviye-storefront'); ?>
                </button>
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
                            <th></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody data-scp-products-body></tbody>
            </table>
        </div>

        <?php if ($scp_can_manage_products) : ?>
            <form class="scp-form" data-scp-product-form hidden>
                <input type="hidden" name="id">
                <input type="hidden" name="image_id">

                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Ürün Adı', 'seviye-storefront'); ?></span>
                        <input type="text" name="name" required>
                    </label>
                    <label>
                        <span><?php esc_html_e('Fiyat (TRY)', 'seviye-storefront'); ?></span>
                        <input type="number" min="0" step="0.01" name="price" required>
                    </label>
                </div>

                <label>
                    <span><?php esc_html_e('Açıklama', 'seviye-storefront'); ?></span>
                    <textarea name="description" rows="3"></textarea>
                </label>

                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Kategori', 'seviye-storefront'); ?></span>
                        <input type="text" name="category" placeholder="<?php esc_attr_e('ör. Kırtasiye', 'seviye-storefront'); ?>">
                    </label>
                    <label class="scp-checkbox">
                        <input type="checkbox" name="manage_stock" data-scp-manage-stock>
                        <span><?php esc_html_e('Stok takibi yap', 'seviye-storefront'); ?></span>
                    </label>
                    <label data-scp-stock-quantity-field hidden>
                        <span><?php esc_html_e('Stok Adedi', 'seviye-storefront'); ?></span>
                        <input type="number" min="0" step="1" name="stock_quantity">
                    </label>
                    <label data-scp-low-stock-field hidden>
                        <span>
                            <?php esc_html_e('Düşük Stok Eşiği (isteğe bağlı)', 'seviye-storefront'); ?>
                        </span>
                        <input type="number" min="0" step="1" name="low_stock_amount">
                    </label>
                </div>

                <div class="scp-form__row" data-scp-variant-fields>
                    <label>
                        <span><?php esc_html_e('Bedenler (virgülle ayırın, ör. S,M,L,XL)', 'seviye-storefront'); ?></span>
                        <input type="text" name="sizes" placeholder="S,M,L,XL">
                    </label>
                    <label>
                        <span><?php esc_html_e('Renkler (virgülle ayırın, ör. Kırmızı,Mavi)', 'seviye-storefront'); ?></span>
                        <input type="text" name="colors" placeholder="Kırmızı,Mavi">
                    </label>
                </div>
                <p class="scp-form__hint" data-scp-variant-hint>
                    <?php esc_html_e(
                        'Beden ve/veya renk girilirse ürün varyantlı oluşturulur, her kombinasyon için ayrı stok/fiyat sonradan "Varyantları Düzenle" ile ayarlanır. Mevcut bir ürünün varyant yapısı sonradan değiştirilemez.',
                        'seviye-storefront'
                    ); ?>
                </p>

                <p class="scp-form__hint" data-scp-variant-locked-notice hidden>
                    <?php esc_html_e(
                        'Bu ürün varyantlı - fiyat/stok tek tek her varyant için "Varyantları Düzenle" ile ayarlanır.',
                        'seviye-storefront'
                    ); ?>
                </p>

                <label>
                    <span><?php esc_html_e('Görsel', 'seviye-storefront'); ?></span>
                    <img class="scp-product-image-preview" data-scp-product-image-preview hidden alt="">
                    <input type="file" accept="image/*" data-scp-product-image-input>
                    <span class="scp-status" data-scp-product-image-status></span>
                </label>

                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                    <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-product>
                        <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                    </button>
                    <button type="button" class="scp-btn scp-btn--ghost" data-scp-edit-variations hidden>
                        <?php esc_html_e('Varyantları Düzenle', 'seviye-storefront'); ?>
                    </button>
                    <button type="button" class="scp-btn scp-btn--danger" data-scp-delete-product hidden>
                        <?php esc_html_e('Sil', 'seviye-storefront'); ?>
                    </button>
                </div>
            </form>

            <?php if (current_user_can('scp_manage_pricing')) : ?>
                <div class="scp-card scp-card--nested" data-scp-product-pricing-panel hidden>
                    <div class="scp-card__header">
                        <h3><?php esc_html_e('Fiyat Kuralları', 'seviye-storefront'); ?></h3>
                        <button type="button" class="scp-btn scp-btn--small" data-scp-new-product-price-rule>
                            <?php esc_html_e('Yeni Kural', 'seviye-storefront'); ?>
                        </button>
                    </div>

                    <div class="scp-table-wrapper">
                        <table class="scp-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Kapsam', 'seviye-storefront'); ?></th>
                                    <th><?php esc_html_e('Hedef', 'seviye-storefront'); ?></th>
                                    <th><?php esc_html_e('Fiyat (TRY)', 'seviye-storefront'); ?></th>
                                    <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-scp-product-price-rules-body></tbody>
                        </table>
                    </div>

                    <form class="scp-form" data-scp-product-price-rule-form hidden>
                        <input type="hidden" name="id">

                        <div class="scp-form__row">
                            <label data-scp-product-price-scope-field>
                                <span><?php esc_html_e('Kapsam', 'seviye-storefront'); ?></span>
                                <select name="scope">
                                    <option value="general" data-scp-product-scope-general>
                                        <?php esc_html_e('Genel', 'seviye-storefront'); ?>
                                    </option>
                                    <option value="branch"><?php esc_html_e('Şube', 'seviye-storefront'); ?></option>
                                    <option value="student"><?php esc_html_e('Öğrenci', 'seviye-storefront'); ?></option>
                                </select>
                            </label>
                            <label data-scp-product-price-target-field hidden>
                                <span data-scp-product-price-target-label></span>
                                <input type="number" min="1" name="target_id">
                            </label>
                        </div>

                        <div class="scp-form__row">
                            <label>
                                <span><?php esc_html_e('Fiyat (TRY)', 'seviye-storefront'); ?></span>
                                <input type="number" min="0" step="0.01" name="price" required>
                            </label>
                            <label data-scp-product-price-status-field hidden>
                                <span><?php esc_html_e('Durum', 'seviye-storefront'); ?></span>
                                <select name="status">
                                    <option value="active"><?php esc_html_e('Aktif', 'seviye-storefront'); ?></option>
                                    <option value="inactive">
                                        <?php esc_html_e('Pasif', 'seviye-storefront'); ?>
                                    </option>
                                </select>
                            </label>
                        </div>

                        <div class="scp-form__actions">
                            <button type="submit" class="scp-btn scp-btn--small">
                                <?php esc_html_e('Kaydet', 'seviye-storefront'); ?>
                            </button>
                            <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-cancel-product-price-rule>
                                <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                            </button>
                            <button type="button" class="scp-btn scp-btn--danger scp-btn--small" data-scp-delete-product-price-rule hidden>
                                <?php esc_html_e('Sil', 'seviye-storefront'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <div class="scp-card scp-card--nested" data-scp-product-variations-panel hidden>
                <div class="scp-card__header">
                    <h3><?php esc_html_e('Varyantlar', 'seviye-storefront'); ?></h3>
                </div>
                <div class="scp-table-wrapper">
                    <table class="scp-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Varyant', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Fiyat (TRY)', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Stok Adedi', 'seviye-storefront'); ?></th>
                            </tr>
                        </thead>
                        <tbody data-scp-product-variations-list></tbody>
                    </table>
                </div>
                <div class="scp-form__actions">
                    <button type="button" class="scp-btn" data-scp-save-variations>
                        <?php esc_html_e('Kaydet', 'seviye-storefront'); ?>
                    </button>
                    <button type="button" class="scp-btn scp-btn--ghost" data-scp-close-product-variations>
                        <?php esc_html_e('Kapat', 'seviye-storefront'); ?>
                    </button>
                </div>
            </div>

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
