<?php

/**
 * Depo - its own page (/admin/depo, /sube/depo, see inc/zones.php's
 * scp_menu_pages()) - "her bir menü için ayrı bir sayfa yap" (bölüm 65).
 * Reached at all already implies scp_menu_pages()'in 'depo' girişindeki
 * capability'lerden biri geçti. Markup/ids/data-attributes moved here
 * verbatim from templates/zone.php so assets/js/depo-panel.js keeps
 * working unchanged.
 *
 * Faz 4: "Genel Merkez'in kendi deposu devam eder, şube kendi ürününü
 * eklemişse şubenin kendi deposundan görünür" - data-scp-depo-branch-field
 * yalnızca platform-wide (scpPanel.canViewAllBranches) kullanıcıya
 * gösterilir (bkz. depo-panel.js), own-branch kullanıcı hiç görmez -
 * REST tarafı zaten onu kendi şubesine kilitliyor (bkz.
 * PurchaseOrdersRestController::resolveBranchScope()).
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Depo', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-depo-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('Depo', 'seviye-storefront'); ?></h2>
        </div>

        <p class="scp-status" data-scp-depo-status></p>

        <div class="scp-form__row" data-scp-depo-branch-field hidden>
            <label>
                <span><?php esc_html_e('Depo', 'seviye-storefront'); ?></span>
                <select></select>
            </label>
        </div>

        <div class="scp-card scp-card--nested">
            <div class="scp-card__header">
                <h3><?php esc_html_e('Tedarikçiler', 'seviye-storefront'); ?></h3>
                <button type="button" class="scp-btn" data-scp-new-supplier>
                    <?php esc_html_e('Yeni Tedarikçi', 'seviye-storefront'); ?>
                </button>
            </div>

            <div class="scp-table-wrapper">
                <table class="scp-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Ad', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('İletişim', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Telefon', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('E-posta', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Portal', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-scp-suppliers-body></tbody>
                </table>
            </div>

            <form class="scp-form" data-scp-supplier-form hidden>
                <input type="hidden" name="id">

                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Ad', 'seviye-storefront'); ?></span>
                        <input type="text" name="name" required>
                    </label>
                    <label>
                        <span><?php esc_html_e('Yetkili', 'seviye-storefront'); ?></span>
                        <input type="text" name="contact_name">
                    </label>
                    <label>
                        <span><?php esc_html_e('Telefon', 'seviye-storefront'); ?></span>
                        <input type="text" name="phone">
                    </label>
                </div>

                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('E-posta', 'seviye-storefront'); ?></span>
                        <input type="email" name="email">
                    </label>
                    <label>
                        <span><?php esc_html_e('Vergi No', 'seviye-storefront'); ?></span>
                        <input type="text" name="tax_number">
                    </label>
                    <label data-scp-supplier-status-field hidden>
                        <span><?php esc_html_e('Durum', 'seviye-storefront'); ?></span>
                        <select name="status">
                            <option value="active"><?php esc_html_e('Aktif', 'seviye-storefront'); ?></option>
                            <option value="passive"><?php esc_html_e('Pasif', 'seviye-storefront'); ?></option>
                        </select>
                    </label>
                </div>

                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Adres', 'seviye-storefront'); ?></span>
                        <input type="text" name="address">
                    </label>
                </div>

                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Tedarikçi portalı hesabı (e-posta veya kullanıcı adı)', 'seviye-storefront'); ?></span>
                        <input type="text" name="user_email" autocomplete="off">
                    </label>
                </div>
                <p class="scp-hint">
                    <?php esc_html_e(
                        'Buraya girilen e-posta/kullanıcı adı zaten var olan bir WordPress hesabına ait olmalı. Bu tedarikçi o hesapla giriş yaptığında kendi satın alma siparişlerini gördüğü /tedarikci portalına yönlendirilir. Boş bırakılırsa bağlantı kaldırılır.',
                        'seviye-storefront'
                    ); ?>
                </p>

                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                    <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-supplier>
                        <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                    </button>
                    <button type="button" class="scp-btn scp-btn--danger" data-scp-delete-supplier hidden>
                        <?php esc_html_e('Sil', 'seviye-storefront'); ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="scp-card scp-card--nested">
            <div class="scp-card__header">
                <h3><?php esc_html_e('Satın Alma Siparişleri', 'seviye-storefront'); ?></h3>
                <button type="button" class="scp-btn" data-scp-new-purchase-order>
                    <?php esc_html_e('Yeni Sipariş', 'seviye-storefront'); ?>
                </button>
            </div>

            <div class="scp-table-wrapper">
                <table class="scp-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Kod', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Tedarikçi', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Depo', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Beklenen Tarih', 'seviye-storefront'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-scp-purchase-orders-body></tbody>
                </table>
            </div>

            <form class="scp-form" data-scp-purchase-order-form hidden>
                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Tedarikçi', 'seviye-storefront'); ?></span>
                        <select name="supplier_id" required></select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Beklenen Tarih (isteğe bağlı)', 'seviye-storefront'); ?></span>
                        <input type="date" name="expected_date">
                    </label>
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
                                <th><?php esc_html_e('Birim Maliyet (isteğe bağlı)', 'seviye-storefront'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody data-scp-purchase-order-items></tbody>
                    </table>
                </div>
                <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-add-po-item>
                    <?php esc_html_e('Kalem Ekle', 'seviye-storefront'); ?>
                </button>

                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                    <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-purchase-order>
                        <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                    </button>
                </div>
            </form>

            <div class="scp-card scp-card--nested" data-scp-po-detail hidden>
                <div class="scp-card__header">
                    <h4 data-scp-po-detail-title></h4>
                    <div>
                        <button type="button" class="scp-btn scp-btn--small" data-scp-po-send hidden>
                            <?php esc_html_e('Gönder', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-po-cancel hidden>
                            <?php esc_html_e('İptal Et', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-close-po-detail>
                            <?php esc_html_e('Kapat', 'seviye-storefront'); ?>
                        </button>
                    </div>
                </div>

                <div class="scp-table-wrapper">
                    <table class="scp-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Ürün ID', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Sipariş', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Teslim Alınan', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Kalan', 'seviye-storefront'); ?></th>
                                <th data-scp-po-receive-header hidden>
                                    <?php esc_html_e('Şimdi Teslim Al', 'seviye-storefront'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody data-scp-po-detail-items></tbody>
                    </table>
                </div>

                <button type="button" class="scp-btn" data-scp-po-receive hidden>
                    <?php esc_html_e('Mal Kabul Et', 'seviye-storefront'); ?>
                </button>
            </div>
        </div>

        <div class="scp-card scp-card--nested">
            <div class="scp-card__header">
                <h3><?php esc_html_e('Stok Sayımı', 'seviye-storefront'); ?></h3>
                <button type="button" class="scp-btn" data-scp-new-stock-count>
                    <?php esc_html_e('Yeni Sayım Başlat', 'seviye-storefront'); ?>
                </button>
            </div>

            <div class="scp-table-wrapper">
                <table class="scp-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Depo', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Başlangıç', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Kalem Sayısı', 'seviye-storefront'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-scp-stock-counts-body></tbody>
                </table>
            </div>

            <div class="scp-card scp-card--nested" data-scp-stock-count-detail hidden>
                <div class="scp-card__header">
                    <h4 data-scp-stock-count-detail-title></h4>
                    <div>
                        <button type="button" class="scp-btn scp-btn--small" data-scp-stock-count-complete hidden>
                            <?php esc_html_e('Tamamla', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-close-stock-count-detail>
                            <?php esc_html_e('Kapat', 'seviye-storefront'); ?>
                        </button>
                    </div>
                </div>

                <div class="scp-table-wrapper">
                    <table class="scp-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Ürün ID', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Beklenen', 'seviye-storefront'); ?></th>
                                <th data-scp-stock-count-input-header><?php esc_html_e('Sayılan', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Fark', 'seviye-storefront'); ?></th>
                            </tr>
                        </thead>
                        <tbody data-scp-stock-count-items></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="scp-card scp-card--nested">
            <div class="scp-card__header">
                <h3><?php esc_html_e('Satın Alma Önerileri', 'seviye-storefront'); ?></h3>
            </div>

            <p class="scp-hint"><?php esc_html_e('Düşük stok uyarısı tetiklendiğinde burada otomatik olarak bir öneri açılır.', 'seviye-storefront'); ?></p>

            <div class="scp-table-wrapper">
                <table class="scp-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Ürün ID', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Depo', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Önerilen Miktar', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Neden', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-scp-purchase-suggestions-body></tbody>
                </table>
            </div>

            <form class="scp-form" data-scp-convert-suggestion-form hidden>
                <input type="hidden" name="suggestion_id">
                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Tedarikçi', 'seviye-storefront'); ?></span>
                        <select name="supplier_id" required></select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Miktar', 'seviye-storefront'); ?></span>
                        <input type="number" name="quantity" min="1">
                    </label>
                </div>
                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('Siparişe Çevir', 'seviye-storefront'); ?></button>
                    <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-convert-suggestion>
                        <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="scp-card scp-card--nested">
            <div class="scp-card__header">
                <h3><?php esc_html_e('Depo Transferleri', 'seviye-storefront'); ?></h3>
                <button type="button" class="scp-btn" data-scp-new-stock-transfer>
                    <?php esc_html_e('Yeni Transfer', 'seviye-storefront'); ?>
                </button>
            </div>

            <p class="scp-hint">
                <?php esc_html_e(
                    'Bir depodan fazla stoğu başka bir depoya (Genel Merkez\'e ya da bir şubeye) aktarın. Hedef depo teslim aldığını onaylayana kadar stok değişmez.',
                    'seviye-storefront'
                ); ?>
            </p>

            <div class="scp-table-wrapper">
                <table class="scp-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Kaynak Ürün', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Kaynak Depo', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Hedef Ürün', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Hedef Depo', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Miktar', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-scp-stock-transfers-body></tbody>
                </table>
            </div>

            <form class="scp-form" data-scp-stock-transfer-form hidden>
                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Kaynak Ürün ID', 'seviye-storefront'); ?></span>
                        <input type="number" min="1" name="from_product_id" required>
                    </label>
                    <label>
                        <span><?php esc_html_e('Hedef Ürün ID', 'seviye-storefront'); ?></span>
                        <input type="number" min="1" name="to_product_id" required>
                    </label>
                    <label>
                        <span><?php esc_html_e('Miktar', 'seviye-storefront'); ?></span>
                        <input type="number" min="1" name="quantity" required>
                    </label>
                </div>
                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Not (isteğe bağlı)', 'seviye-storefront'); ?></span>
                        <input type="text" name="note">
                    </label>
                </div>
                <p class="scp-hint">
                    <?php esc_html_e(
                        'Kaynak ve hedef ürün, WooCommerce kataloğundaki iki AYRI ürün kaydı olmalı - genelde hedef şubenin kendi kataloğuna daha önce eklediği "aynı ürün"ün kendi kaydı.',
                        'seviye-storefront'
                    ); ?>
                </p>
                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('Transfer Aç', 'seviye-storefront'); ?></button>
                    <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-stock-transfer-form>
                        <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>
