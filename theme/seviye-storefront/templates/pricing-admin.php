<?php

/**
 * Fiyat Kuralları - its own page (/admin/fiyatlandirma, /sube/fiyatlandirma,
 * see inc/zones.php's scp_menu_pages()) - "her bir menü için ayrı bir
 * sayfa yap" (bölüm 65). Reached at all already implies scp_manage_pricing
 * passed - see scp_menu_pages().
 *
 * Two changes from the original (bölüm 67): the lookup form now searches
 * products BY NAME (a <datalist>-backed text input, populated from
 * commerce/products) instead of requiring a raw numeric id typed by hand -
 * "ürün id'si girmek yerine direkt ürün seçilip fiyat güncellemesi
 * yapılsın". And "Toplu İçe Aktarma" moved into its own nested card
 * (`scp-card--nested`, the same visual separation depo-admin.php's
 * "Tedarikçiler"/"Satın Alma Siparişleri" sections use) - "ayrı bir
 * yapıda olsun" - and now accepts an .xlsx workbook alongside the
 * existing .csv (see Seviye\Pricing\Support\XlsxToCsvConverter, which
 * converts it server-side so PriceRuleImportParser only ever has to
 * understand ONE format).
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Fiyat Kuralları', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-pricing-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('Fiyat Kuralları', 'seviye-storefront'); ?></h2>
        </div>

        <form class="scp-form scp-form--inline" data-scp-price-lookup-form>
            <label>
                <span><?php esc_html_e('Ürün', 'seviye-storefront'); ?></span>
                <input
                    type="text"
                    name="product_search"
                    list="scp-pricing-product-options"
                    autocomplete="off"
                    placeholder="<?php esc_attr_e('Ürün adı veya ID ile arayın', 'seviye-storefront'); ?>"
                    required
                >
                <datalist id="scp-pricing-product-options"></datalist>
            </label>
            <button type="submit" class="scp-btn"><?php esc_html_e('Fiyatları Getir', 'seviye-storefront'); ?></button>
        </form>

        <p class="scp-status" data-scp-pricing-status></p>

        <div class="scp-card scp-card--nested">
            <div class="scp-card__header">
                <h3><?php esc_html_e('Toplu İçe Aktarma', 'seviye-storefront'); ?></h3>
            </div>

            <p class="scp-form__hint">
                <?php esc_html_e(
                    'İlk satır başlık olmalı: product_id, scope (general, branch veya student), price, target_id (branch/student kapsamında zorunlu, şube yetkilileri için otomatik kendi şubeleri kullanılır). CSV (.csv) veya Excel (.xlsx) dosyası yükleyebilirsiniz.',
                    'seviye-storefront'
                ); ?>
                <a href="#" data-scp-download-price-import-template>
                    <?php esc_html_e('Örnek şablonu indir', 'seviye-storefront'); ?>
                </a>
            </p>

            <form class="scp-form scp-form--inline" data-scp-price-import-form>
                <label>
                    <span><?php esc_html_e('CSV veya Excel Dosyası', 'seviye-storefront'); ?></span>
                    <input
                        type="file"
                        accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        name="import_file"
                        required
                    >
                </label>
                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('İçe Aktar', 'seviye-storefront'); ?></button>
                </div>
            </form>

            <div data-scp-price-import-result hidden>
                <p data-scp-price-import-summary></p>
                <ul class="scp-list" data-scp-price-import-errors></ul>
            </div>
        </div>

        <div data-scp-price-rules-results hidden>
            <div class="scp-card__header">
                <span></span>
                <button type="button" class="scp-btn" data-scp-new-price-rule>
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
                    <tbody data-scp-price-rules-body></tbody>
                </table>
            </div>

            <form class="scp-form" data-scp-price-rule-form hidden>
                <input type="hidden" name="id">

                <div class="scp-form__row">
                    <label data-scp-price-scope-field>
                        <span>
                            <?php esc_html_e('Kapsam', 'seviye-storefront'); ?>
                            <?php
                            $priceScopeTip = __(
                                'Aynı ürün için birden fazla kural varsa Öğrenci kuralı Şube kuralını, Şube kuralı da Genel kuralı geçersiz kılar. Genel, hiçbir özel kural yoksa varsayılan fiyattır.',
                                'seviye-storefront'
                            );
                            ?>
                            <span
                                class="scp-help-tip"
                                tabindex="0"
                                data-tip="<?php echo esc_attr($priceScopeTip); ?>"
                                aria-label="<?php echo esc_attr($priceScopeTip); ?>"
                            >?</span>
                        </span>
                        <select name="scope">
                            <option value="general" data-scp-scope-general><?php esc_html_e('Genel', 'seviye-storefront'); ?></option>
                            <option value="branch"><?php esc_html_e('Şube', 'seviye-storefront'); ?></option>
                            <option value="student"><?php esc_html_e('Öğrenci', 'seviye-storefront'); ?></option>
                        </select>
                    </label>
                    <label data-scp-price-target-field hidden>
                        <span data-scp-price-target-label></span>
                        <input type="number" min="1" name="target_id">
                    </label>
                </div>

                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Fiyat (TRY)', 'seviye-storefront'); ?></span>
                        <input type="number" min="0" step="0.01" name="price" required>
                    </label>
                    <label data-scp-price-status-field hidden>
                        <span><?php esc_html_e('Durum', 'seviye-storefront'); ?></span>
                        <select name="status">
                            <option value="active"><?php esc_html_e('Aktif', 'seviye-storefront'); ?></option>
                            <option value="inactive"><?php esc_html_e('Pasif', 'seviye-storefront'); ?></option>
                        </select>
                    </label>
                </div>

                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                    <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-price-rule>
                        <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                    </button>
                    <button type="button" class="scp-btn scp-btn--danger" data-scp-delete-price-rule hidden>
                        <?php esc_html_e('Sil', 'seviye-storefront'); ?>
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>
