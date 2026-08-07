<?php

/**
 * The /admin and /sube ROOT landing view. Included directly by
 * inc/zones.php with $scp_zone_label already set in scope, ONLY when
 * scp_zone_path is empty - every other dashboard section (Öğrenciler,
 * Şubeler, Fiyat Kuralları, Depo, Cari Bakiye, Raporlar, Toplu Duyuru,
 * Destek Talepleri, Hesap Güvenliği, KVKK, IP Kısıtlaması, SMS/E-posta
 * Ayarları, API Anahtarları, Görünüm, Aktivite Günlüğü, plus the
 * already-standalone Ürünler/Siparişler) now lives on its OWN
 * /admin/{slug} or /sube/{slug} page - "burada her bir menü için ayrı bir
 * sayfa yap" (bölüm 65). See inc/zones.php's scp_menu_pages() for the
 * full slug => capability/template table and scp_render_zone_template()'s
 * dispatch loop that reads it.
 *
 * This page itself keeps only "Genel Bakış" (bölüm 37's overview
 * dashboard) - it deliberately did NOT move to its own page like
 * everything else: an /admin or /sube root with nothing on it would be
 * worse UX than a dashboard that shows something the moment you land on
 * it, so it stays the root's own content instead of requiring an extra
 * click.
 *
 * The section-to-section NAV used to live here too (a top "quicknav",
 * bölüm 64) - it moved to a left sidebar rendered from header.php instead
 * (see inc/sidebar.php's scp_render_sidebar()), so it appears on every
 * staff page, not just this root - "menüleri üst tarafa koymak yerine sol
 * tarafa al" (bölüm 66). This file no longer builds or renders any nav.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<div class="scp-panel">
    <h1><?php
        echo esc_html(sprintf(
            /* translators: %s: zone label, e.g. "Genel Merkez" or "Şube" */
            __('%s Paneli', 'seviye-storefront'),
            $scp_zone_label
        ));
        ?></h1>
    <p><?php
        echo esc_html(sprintf(
            /* translators: %s: display name of the logged-in user */
            __('Hoş geldiniz, %s.', 'seviye-storefront'),
            wp_get_current_user()->display_name
        ));
        ?></p>

    <?php if (current_user_can('scp_view_reports') || current_user_can('scp_view_own_reports')) : ?>
        <section class="scp-card" id="scp-overview-panel">
            <div class="scp-card__header">
                <h2><?php esc_html_e('Genel Bakış', 'seviye-storefront'); ?></h2>
            </div>

            <p class="scp-status" data-scp-overview-status></p>

            <?php
            /**
             * "Dashboard widget sürükle-bırak yeniden sıralama" - dört
             * widget'ın (istatistikler/trend/en çok satanlar/şube kırılımı)
             * her biri kendi `data-scp-dashboard-widget` id'siyle
             * işaretlendi; assets/js/overview-panel.js'in
             * initDashboardWidgetReorder()'ı bu id'leri sürükleme
             * sırasında okuyup localStorage'a kaydediyor, sonraki
             * ziyarette AYNI sırayla yeniden diziyor. Sıra, hangi
             * `data-scp-overview-*` alt öğesinin nerede olduğunu
             * DEĞİŞTİRMİYOR - yalnızca bu dört sarmalayıcının kendi
             * aralarındaki sırasını.
             */
            ?>
            <div class="scp-dashboard-widgets" data-scp-dashboard-widgets>
                <div class="scp-dashboard-widget" data-scp-dashboard-widget="stats">
                    <button
                        type="button"
                        class="scp-dashboard-widget__handle"
                        draggable="true"
                        aria-label="<?php esc_attr_e('Widget\'ı sürükle', 'seviye-storefront'); ?>"
                    >⠿</button>
                    <div class="scp-stat-grid" data-scp-overview-stats hidden>
                        <div class="scp-stat-tile">
                            <span class="scp-stat-tile__label"><?php esc_html_e('Bugün', 'seviye-storefront'); ?></span>
                            <span class="scp-stat-tile__value" data-scp-overview-today-total></span>
                            <span class="scp-stat-tile__meta" data-scp-overview-today-count></span>
                        </div>
                        <div class="scp-stat-tile">
                            <span class="scp-stat-tile__label"><?php esc_html_e('Son 7 Gün', 'seviye-storefront'); ?></span>
                            <span class="scp-stat-tile__value" data-scp-overview-week-total></span>
                            <span class="scp-stat-tile__meta" data-scp-overview-week-count></span>
                        </div>
                        <div class="scp-stat-tile">
                            <span class="scp-stat-tile__label"><?php esc_html_e('Son 30 Gün', 'seviye-storefront'); ?></span>
                            <span class="scp-stat-tile__value" data-scp-overview-month-total></span>
                            <span class="scp-stat-tile__meta" data-scp-overview-month-count></span>
                        </div>
                    </div>
                </div>

                <div class="scp-dashboard-widget" data-scp-dashboard-widget="trend">
                    <button
                        type="button"
                        class="scp-dashboard-widget__handle"
                        draggable="true"
                        aria-label="<?php esc_attr_e('Widget\'ı sürükle', 'seviye-storefront'); ?>"
                    >⠿</button>
                    <div class="scp-trend-chart" data-scp-overview-trend hidden>
                        <h3><?php esc_html_e('Günlük Ciro Trendi (Son 30 Gün)', 'seviye-storefront'); ?></h3>
                        <div class="scp-trend-chart__svg-host" data-scp-overview-trend-chart></div>
                        <div class="scp-trend-chart__range">
                            <span data-scp-overview-trend-from></span>
                            <span data-scp-overview-trend-to></span>
                        </div>
                    </div>
                </div>

                <div class="scp-dashboard-widget" data-scp-dashboard-widget="products">
                    <button
                        type="button"
                        class="scp-dashboard-widget__handle"
                        draggable="true"
                        aria-label="<?php esc_attr_e('Widget\'ı sürükle', 'seviye-storefront'); ?>"
                    >⠿</button>
                    <h3><?php esc_html_e('En Çok Satan Ürünler (Son 30 Gün)', 'seviye-storefront'); ?></h3>
                    <div class="scp-table-wrapper">
                        <table class="scp-table" data-scp-overview-products-table hidden>
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Ürün', 'seviye-storefront'); ?></th>
                                    <th><?php esc_html_e('Adet', 'seviye-storefront'); ?></th>
                                    <th><?php esc_html_e('Ciro (TRY)', 'seviye-storefront'); ?></th>
                                </tr>
                            </thead>
                            <tbody data-scp-overview-products-body></tbody>
                        </table>
                    </div>
                </div>

                <div class="scp-dashboard-widget" data-scp-dashboard-widget="branches">
                    <button
                        type="button"
                        class="scp-dashboard-widget__handle"
                        draggable="true"
                        aria-label="<?php esc_attr_e('Widget\'ı sürükle', 'seviye-storefront'); ?>"
                    >⠿</button>
                    <div data-scp-overview-branch-section hidden>
                        <h3><?php esc_html_e('Şube Bazlı Kırılım (Son 30 Gün)', 'seviye-storefront'); ?></h3>
                        <div class="scp-table-wrapper">
                            <table class="scp-table" data-scp-overview-branches-table hidden>
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Şube', 'seviye-storefront'); ?></th>
                                        <th><?php esc_html_e('Sipariş', 'seviye-storefront'); ?></th>
                                        <th><?php esc_html_e('Ciro (TRY)', 'seviye-storefront'); ?></th>
                                    </tr>
                                </thead>
                                <tbody data-scp-overview-branches-body></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <?php else : ?>
        <div class="scp-card scp-empty">
            <p><?php esc_html_e('Sol menüden bir bölüm seçin.', 'seviye-storefront'); ?></p>
        </div>
    <?php endif; ?>

</div>
<?php
get_footer();
