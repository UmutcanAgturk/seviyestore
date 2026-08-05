<?php

/**
 * Raporlar - its own page (/admin/raporlar, /sube/raporlar, see
 * inc/zones.php's scp_menu_pages()) - "her bir menü için ayrı bir sayfa
 * yap" (bölüm 65). Reached at all already implies scp_view_reports or
 * scp_view_own_reports passed - see scp_menu_pages(). Markup/ids/
 * data-attributes moved here verbatim from templates/zone.php so
 * assets/js/reports-panel.js keeps working unchanged.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Raporlar', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-reports-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('Raporlar', 'seviye-storefront'); ?></h2>
        </div>

        <p class="scp-status" data-scp-reports-status></p>

        <form class="scp-form scp-form--inline" data-scp-report-form>
            <label data-scp-report-branch-field hidden>
                <span><?php esc_html_e('Şube', 'seviye-storefront'); ?></span>
                <select></select>
            </label>
            <label>
                <span><?php esc_html_e('Ürün ID', 'seviye-storefront'); ?></span>
                <input type="number" min="1" name="product_id">
            </label>
            <label>
                <span><?php esc_html_e('Kategori ID', 'seviye-storefront'); ?></span>
                <input type="number" min="1" name="category_id">
            </label>
            <label>
                <span><?php esc_html_e('Başlangıç', 'seviye-storefront'); ?></span>
                <input type="date" name="from">
            </label>
            <label>
                <span><?php esc_html_e('Bitiş', 'seviye-storefront'); ?></span>
                <input type="date" name="to">
            </label>

            <div class="scp-form__actions">
                <button type="submit" class="scp-btn"><?php esc_html_e('Getir', 'seviye-storefront'); ?></button>
                <button type="button" class="scp-btn scp-btn--ghost" data-scp-report-csv>
                    <?php esc_html_e('CSV İndir', 'seviye-storefront'); ?>
                </button>
                <button type="button" class="scp-btn scp-btn--ghost" data-scp-report-xlsx>
                    <?php esc_html_e('Excel İndir', 'seviye-storefront'); ?>
                </button>
            </div>
        </form>

        <div class="scp-table-wrapper">
            <table class="scp-table" data-scp-reports-table hidden>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Şube', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Ürün', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Sipariş Sayısı', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Toplam Tutar (TRY)', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Toplam KDV (TRY)', 'seviye-storefront'); ?></th>
                    </tr>
                </thead>
                <tbody data-scp-reports-body></tbody>
            </table>
        </div>

        <div class="scp-comparison-chart" data-scp-comparison-chart hidden>
            <div class="scp-card__header">
                <h3><?php esc_html_e('Karşılaştırma', 'seviye-storefront'); ?></h3>
                <div class="scp-comparison-chart__toggle">
                    <button
                        type="button"
                        class="scp-btn scp-btn--small scp-btn--active"
                        data-scp-comparison-mode="branch"
                    ><?php esc_html_e('Şubelere Göre', 'seviye-storefront'); ?></button>
                    <button
                        type="button"
                        class="scp-btn scp-btn--small"
                        data-scp-comparison-mode="product"
                    ><?php esc_html_e('Ürünlere Göre', 'seviye-storefront'); ?></button>
                </div>
            </div>
            <div data-scp-comparison-chart-host></div>
        </div>

        <?php if (current_user_can('scp_view_reports')) : ?>
            <div class="scp-card scp-card--nested">
                <div class="scp-card__header">
                    <h3><?php esc_html_e('Depo Raporları', 'seviye-storefront'); ?></h3>
                </div>

                <form class="scp-form scp-form--inline" data-scp-warehouse-report-form>
                    <label>
                        <span><?php esc_html_e('Tedarikçi ID', 'seviye-storefront'); ?></span>
                        <input type="number" min="1" name="supplier_id">
                    </label>
                    <label>
                        <span><?php esc_html_e('Başlangıç', 'seviye-storefront'); ?></span>
                        <input type="date" name="from">
                    </label>
                    <label>
                        <span><?php esc_html_e('Bitiş', 'seviye-storefront'); ?></span>
                        <input type="date" name="to">
                    </label>

                    <div class="scp-form__actions">
                        <button type="submit" class="scp-btn"><?php esc_html_e('Getir', 'seviye-storefront'); ?></button>
                        <button type="button" class="scp-btn scp-btn--ghost" data-scp-warehouse-report-csv>
                            <?php esc_html_e('CSV İndir', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--ghost" data-scp-warehouse-report-xlsx>
                            <?php esc_html_e('Excel İndir', 'seviye-storefront'); ?>
                        </button>
                    </div>
                </form>

                <div class="scp-table-wrapper">
                    <table class="scp-table" data-scp-warehouse-report-table hidden>
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Tedarikçi', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Sipariş Sayısı', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Toplam Tutar (TRY)', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Tamamlanan Sipariş', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Zamanında Teslim Oranı', 'seviye-storefront'); ?></th>
                            </tr>
                        </thead>
                        <tbody data-scp-warehouse-report-body></tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>
