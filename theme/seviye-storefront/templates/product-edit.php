<?php

/**
 * A single product's own edit/create page (/admin/urunler/{id},
 * /admin/urunler/yeni, /sube/urunler/{id}, /sube/urunler/yeni - see
 * inc/zones.php) - "ürüne tıklandığında o ürünün düzenleme sayfası gelsin,
 * tüm düzenlemeler orada yapılabilsin". Everything that touches the
 * product's OWN fields (name/price/stock/image/category/variants) plus its
 * price rules (bkz. Seviye Pricing) lives on this one page; only the
 * per-branch active/passive toggle grid stays on
 * templates/products-admin.php's list (a quick visibility flip, not really
 * "editing the product" - see that template's own note).
 *
 * Reached at all already implies inc/zones.php's own capability check
 * (scp_manage_products - not scp_view_products, this page WRITES) and id
 * validation passed - see the render branch there, which sets
 * $scp_product_id (null for /yeni, a positive int for /{id}).
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$scp_is_new_product = $scp_product_id === null;

?>
<div class="scp-panel">
    <h1>
        <?php echo $scp_is_new_product
            ? esc_html__('Yeni Ürün', 'seviye-storefront')
            : esc_html__('Ürünü Düzenle', 'seviye-storefront'); ?>
    </h1>
    <p>
        <a href="<?php echo esc_url(scp_admin_products_path()); ?>">
            <?php esc_html_e('← Ürünlere Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-product-edit-panel" data-scp-product-id="<?php echo esc_attr((string) ($scp_product_id ?? '')); ?>">
        <p class="scp-status" data-scp-product-edit-status></p>

        <form class="scp-form" data-scp-product-form>
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
                    'Beden ve/veya renk girilirse ürün varyantlı oluşturulur, her kombinasyon için ayrı stok/fiyat sonradan aşağıdaki "Varyantlar" bölümünden ayarlanır. Kaydettikten sonra varyant yapısı değiştirilemez.',
                    'seviye-storefront'
                ); ?>
            </p>

            <p class="scp-form__hint" data-scp-variant-locked-notice hidden>
                <?php esc_html_e(
                    'Bu ürün varyantlı - fiyat/stok tek tek her varyant için aşağıdaki "Varyantlar" bölümünden ayarlanır.',
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
                <button type="button" class="scp-btn scp-btn--danger" data-scp-delete-product hidden>
                    <?php esc_html_e('Ürünü Sil', 'seviye-storefront'); ?>
                </button>
            </div>
        </form>

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
                    <?php esc_html_e('Varyantları Kaydet', 'seviye-storefront'); ?>
                </button>
            </div>
        </div>

        <?php if (current_user_can('scp_manage_pricing')) : ?>
            <div class="scp-card scp-card--nested" data-scp-product-pricing-panel<?php echo $scp_is_new_product ? ' hidden' : ''; ?>>
                <div class="scp-card__header">
                    <h3><?php esc_html_e('Fiyat Kuralları', 'seviye-storefront'); ?></h3>
                    <button type="button" class="scp-btn scp-btn--small" data-scp-new-product-price-rule>
                        <?php esc_html_e('Yeni Kural', 'seviye-storefront'); ?>
                    </button>
                </div>

                <?php if ($scp_is_new_product) : ?>
                    <p class="scp-form__hint">
                        <?php esc_html_e(
                            'Fiyat kuralları eklemeden önce ürünü bir kez kaydedin.',
                            'seviye-storefront'
                        ); ?>
                    </p>
                <?php endif; ?>

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
    </section>
</div>
