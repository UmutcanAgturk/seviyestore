<?php

/**
 * Şube Siparişleri - its own page (/admin/sube-siparisleri,
 * /sube/sube-siparisleri, see inc/zones.php's scp_menu_pages()).
 *
 * Two very different roles share this one template, toggled via JS off
 * scpPanel.canManageAll/canManageOwn (see assets/js/sube-siparisleri-panel.js):
 * Genel Merkez/Bölge Müdürü manages the free-quota table and approves/
 * rejects submitted orders; Şube Müdürü drafts/submits their own branch's
 * orders and, once approved with an overage, pays the WooCommerce-generated
 * balance with a card via WooCommerce's own "Pay for order" page. The order
 * list + detail section at the bottom is shared by both roles - only the
 * action buttons inside it differ by capability.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Şube Siparişleri', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-sube-siparisleri-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('Şube Siparişleri', 'seviye-storefront'); ?></h2>
        </div>

        <p class="scp-status" data-scp-sso-status></p>

        <div class="scp-form__row" data-scp-sso-branch-field hidden>
            <label>
                <span><?php esc_html_e('Şube', 'seviye-storefront'); ?></span>
                <select></select>
            </label>
        </div>

        <div class="scp-card scp-card--nested" data-scp-sso-quota-section hidden>
            <div class="scp-card__header">
                <h3><?php esc_html_e('Ücretsiz Kota Yönetimi', 'seviye-storefront'); ?></h3>
                <button type="button" class="scp-btn" data-scp-new-quota>
                    <?php esc_html_e('Yeni Kota', 'seviye-storefront'); ?>
                </button>
            </div>

            <p class="scp-hint">
                <?php esc_html_e(
                    'Her şube × ürün çifti için ne kadar ücretsiz hak tanındığını burada belirleyin. Bu hak tükendikten sonraki miktar, sipariş onaylandığında gerçek bir WooCommerce siparişine dönüşür ve şube müdürü kart ile öder.',
                    'seviye-storefront'
                ); ?>
            </p>

            <div class="scp-table-wrapper">
                <table class="scp-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Şube', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Ürün ID', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Ücretsiz Hak', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Tüketilen', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Kalan', 'seviye-storefront'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-scp-quotas-body></tbody>
                </table>
            </div>

            <form class="scp-form" data-scp-quota-form hidden>
                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Şube', 'seviye-storefront'); ?></span>
                        <select name="branch_id" required></select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Ürün ID', 'seviye-storefront'); ?></span>
                        <input type="number" min="1" name="product_id" required>
                    </label>
                    <label>
                        <span><?php esc_html_e('Ücretsiz Hak', 'seviye-storefront'); ?></span>
                        <input type="number" min="0" name="free_quantity" required>
                    </label>
                </div>
                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                    <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-quota>
                        <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="scp-card scp-card--nested" data-scp-sso-create-section hidden>
            <div class="scp-card__header">
                <h3><?php esc_html_e('Sipariş Oluştur', 'seviye-storefront'); ?></h3>
                <button type="button" class="scp-btn" data-scp-new-branch-order>
                    <?php esc_html_e('Yeni Sipariş', 'seviye-storefront'); ?>
                </button>
            </div>

            <form class="scp-form" data-scp-branch-order-form hidden>
                <input type="hidden" name="id">
                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Not (isteğe bağlı)', 'seviye-storefront'); ?></span>
                        <input type="text" name="note">
                    </label>
                </div>

                <h4><?php esc_html_e('Kalemler', 'seviye-storefront'); ?></h4>
                <div class="scp-table-wrapper">
                    <table class="scp-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Ürün ID', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Adet', 'seviye-storefront'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody data-scp-branch-order-items></tbody>
                    </table>
                </div>
                <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-add-bo-item>
                    <?php esc_html_e('Kalem Ekle', 'seviye-storefront'); ?>
                </button>

                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn">
                        <?php esc_html_e('Taslak Olarak Kaydet', 'seviye-storefront'); ?>
                    </button>
                    <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-branch-order-form>
                        <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="scp-card scp-card--nested">
            <div class="scp-card__header">
                <h3><?php esc_html_e('Siparişler', 'seviye-storefront'); ?></h3>
                <select data-scp-bo-status-filter>
                    <option value=""><?php esc_html_e('Tüm Durumlar', 'seviye-storefront'); ?></option>
                    <option value="draft"><?php esc_html_e('Taslak', 'seviye-storefront'); ?></option>
                    <option value="submitted"><?php esc_html_e('Onay Bekliyor', 'seviye-storefront'); ?></option>
                    <option value="rejected"><?php esc_html_e('Reddedildi', 'seviye-storefront'); ?></option>
                    <option value="awaiting_payment"><?php esc_html_e('Ödeme Bekliyor', 'seviye-storefront'); ?></option>
                    <option value="completed"><?php esc_html_e('Tamamlandı', 'seviye-storefront'); ?></option>
                    <option value="cancelled"><?php esc_html_e('İptal Edildi', 'seviye-storefront'); ?></option>
                </select>
            </div>

            <div class="scp-table-wrapper">
                <table class="scp-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'seviye-storefront'); ?></th>
                            <th data-scp-bo-branch-col-header hidden>
                                <?php esc_html_e('Şube', 'seviye-storefront'); ?>
                            </th>
                            <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Oluşturma', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Ücretli Tutar', 'seviye-storefront'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-scp-branch-orders-body></tbody>
                </table>
            </div>

            <div class="scp-card scp-card--nested" data-scp-bo-detail hidden>
                <div class="scp-card__header">
                    <h4 data-scp-bo-detail-title></h4>
                    <div>
                        <button type="button" class="scp-btn scp-btn--small" data-scp-bo-submit hidden>
                            <?php esc_html_e('Onaya Gönder', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-bo-edit hidden>
                            <?php esc_html_e('Kalemleri Düzenle', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-bo-cancel hidden>
                            <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--small" data-scp-bo-approve hidden>
                            <?php esc_html_e('Onayla', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-bo-reject hidden>
                            <?php esc_html_e('Reddet', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--small" data-scp-bo-pay hidden>
                            <?php esc_html_e('Kart ile Öde', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-close-bo-detail>
                            <?php esc_html_e('Kapat', 'seviye-storefront'); ?>
                        </button>
                    </div>
                </div>

                <p class="scp-status scp-status--error" data-scp-bo-rejected-reason hidden></p>

                <div class="scp-table-wrapper">
                    <table class="scp-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Ürün ID', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('İstenen', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Ücretsiz', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Ücretli', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Birim Fiyat', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Tutar', 'seviye-storefront'); ?></th>
                            </tr>
                        </thead>
                        <tbody data-scp-bo-detail-items></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
