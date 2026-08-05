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
 * This page itself keeps only two things: the quicknav (every section's
 * entry point, whether it lives here or on its own page) and "Genel
 * Bakış" (bölüm 37's overview dashboard). Genel Bakış deliberately did
 * NOT move to its own page like everything else - an /admin or /sube
 * root that shows nothing but a nav bar would be worse UX than a
 * dashboard that shows something the moment you land on it, so it stays
 * the root's own content instead of requiring an extra click.
 *
 * The quicknav is built from the exact same capability (+ zone, where
 * relevant) checks scp_menu_pages() itself gates each page on - it never
 * invents a link a page wouldn't actually let you into, and only appears
 * once there is more than one entry to jump between. Every href is now a
 * real URL (scp_menu_page_path()/scp_admin_products_path()/
 * scp_admin_orders_path()), not an in-page anchor - the one exception is
 * "Genel Bakış" itself, which still targets #scp-overview-panel since
 * that section really does live on this same page.
 *
 * "Genel menü yapısı daha anlaşılır bir yapıda olsun. Kullanıcı odaklı."
 * (bölüm 64) - $scp_sections is grouped (genel/katalog/operasyon/kisiler/
 * finans/iletisim/hesap) instead of one flat list, so a role with many
 * capabilities (Genel Merkez) sees related entries clustered together
 * instead of a dozen same-looking pills in registration order. Grouping is
 * PURELY presentational (a heading between clusters of already-existing
 * `<a>` tags, still direct children of `.scp-quicknav`) - it doesn't touch
 * scpQuicknavReorder()'s drag logic (assets/js/scp-ui-kit.js), which only
 * ever moves `<a>` elements and ignores the group-label `<span>`s, nor the
 * command palette's `.scp-quicknav a` scrape.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Quicknav is built from the exact same capability (+ zone, where relevant)
 * checks each section below already gates on - it never invents a link a
 * section wouldn't actually render. Only shown when there is more than one
 * section to jump between; a single-section page gains nothing from it.
 *
 * Grouped ($scp_sections[$group][$href] = $label) rather than one flat list -
 * see this file's own docblock (bölüm 64). $scp_group_labels gives each
 * group its heading; 'genel' has none (Genel Bakış stands alone at the top,
 * a heading over a single item would be noise).
 */
$scp_sections = [
    'genel' => [],
    'katalog' => [],
    'operasyon' => [],
    'kisiler' => [],
    'finans' => [],
    'iletisim' => [],
    'hesap' => [],
];

$scp_group_labels = [
    'genel' => null,
    'katalog' => __('Katalog', 'seviye-storefront'),
    'operasyon' => __('Operasyon', 'seviye-storefront'),
    'kisiler' => __('Kişiler', 'seviye-storefront'),
    'finans' => __('Finans', 'seviye-storefront'),
    'iletisim' => __('İletişim', 'seviye-storefront'),
    'hesap' => __('Hesap ve Sistem', 'seviye-storefront'),
];

if (current_user_can('scp_view_reports') || current_user_can('scp_view_own_reports')) {
    $scp_sections['genel']['#scp-overview-panel'] = __('Genel Bakış', 'seviye-storefront');
}

if (current_user_can('scp_manage_students')) {
    $scp_sections['kisiler'][scp_menu_page_path('ogrenciler')] = __('Öğrenciler', 'seviye-storefront');
}

if (scp_current_zone() === 'admin' && current_user_can('scp_manage_branches')) {
    $scp_sections['kisiler'][scp_menu_page_path('subeler')] = __('Şubeler', 'seviye-storefront');
}

if (current_user_can('scp_manage_products') || current_user_can('scp_view_products')) {
    $scp_sections['katalog'][scp_admin_products_path()] = __('Ürünler', 'seviye-storefront');
}

if (current_user_can('scp_view_orders') || current_user_can('scp_view_own_branch_orders')) {
    $scp_sections['operasyon'][scp_admin_orders_path()] = __('Siparişler', 'seviye-storefront');
}

if (current_user_can('scp_manage_pricing')) {
    $scp_sections['katalog'][scp_menu_page_path('fiyatlandirma')] = __('Fiyat Kuralları', 'seviye-storefront');
}

if (current_user_can('scp_manage_coupons')) {
    $scp_sections['katalog'][scp_menu_page_path('kampanyalar')] = __('Kampanya Kodları', 'seviye-storefront');
}

if (current_user_can('scp_manage_purchase_orders')) {
    $scp_sections['operasyon'][scp_menu_page_path('depo')] = __('Depo', 'seviye-storefront');
}

if (current_user_can('scp_view_hakedis') || current_user_can('scp_view_own_hakedis')) {
    $scp_sections['finans'][scp_menu_page_path('cari-bakiye')] = __('Cari Bakiye', 'seviye-storefront');
}

if (current_user_can('scp_view_reports') || current_user_can('scp_view_own_reports')) {
    $scp_sections['finans'][scp_menu_page_path('raporlar')] = __('Raporlar', 'seviye-storefront');
}

if (current_user_can('scp_send_broadcast') || current_user_can('scp_send_own_branch_broadcast')) {
    $scp_sections['iletisim'][scp_menu_page_path('duyuru')] = __('Toplu Duyuru', 'seviye-storefront');
}

if (current_user_can('scp_manage_support_tickets')) {
    $scp_sections['iletisim'][scp_menu_page_path('destek-talepleri')] = __('Destek Talepleri', 'seviye-storefront');
}

$scp_sections['hesap'][scp_menu_page_path('hesap-guvenligi')] = __('Hesap Güvenliği', 'seviye-storefront');
$scp_sections['hesap'][scp_menu_page_path('kvkk')] = __('Verilerim (KVKK)', 'seviye-storefront');

if (scp_current_zone() === 'admin' && current_user_can('scp_manage_privacy_requests')) {
    $scp_sections['hesap'][scp_menu_page_path('kvkk-talepleri')] = __('KVKK Talepleri', 'seviye-storefront');
}

if (scp_current_zone() === 'admin' && current_user_can('scp_manage_security_settings')) {
    $scp_sections['hesap'][scp_menu_page_path('ip-kisitlamasi')] = __('IP Kısıtlaması', 'seviye-storefront');
}

if (scp_current_zone() === 'admin' && current_user_can('scp_manage_notification_settings')) {
    $scp_sections['hesap'][scp_menu_page_path('sms-ayarlari')] = __('SMS Ayarları', 'seviye-storefront');
    $scp_sections['hesap'][scp_menu_page_path('eposta-ayarlari')] = __('E-posta Ayarları', 'seviye-storefront');
}

if (scp_current_zone() === 'admin' && current_user_can('scp_manage_api_keys')) {
    $scp_sections['hesap'][scp_menu_page_path('api-anahtarlari')] = __('API Anahtarları', 'seviye-storefront');
}

if (scp_current_zone() === 'admin' && current_user_can('scp_manage_core_settings')) {
    $scp_sections['hesap'][scp_menu_page_path('gorunum')] = __('Görünüm', 'seviye-storefront');
}

if (scp_current_zone() === 'admin' && current_user_can('scp_view_audit_logs')) {
    $scp_sections['hesap'][scp_menu_page_path('aktivite-gunlugu')] = __('Aktivite Günlüğü', 'seviye-storefront');
}

$scp_sections_total = array_sum(array_map('count', $scp_sections));

/*
 * Module-identity tiles (.scp-module-tile in panel.css) only exist for the
 * platform's core modules - everything else in $scp_sections (account
 * security, KVKK, broadcast, ...) renders as a plain quicknav link, same as
 * before this map existed.
 */
$scp_section_variants = [
    scp_menu_page_path('ogrenciler') => 'students',
    scp_menu_page_path('subeler') => 'branches',
    scp_admin_products_path() => 'products',
    scp_admin_orders_path() => 'orders',
    scp_menu_page_path('fiyatlandirma') => 'pricing',
    scp_menu_page_path('depo') => 'depo',
    scp_menu_page_path('cari-bakiye') => 'hakedis',
    scp_menu_page_path('raporlar') => 'reports',
    scp_menu_page_path('destek-talepleri') => 'support',
    scp_menu_page_path('api-anahtarlari') => 'settings',
];

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

    <?php if ($scp_sections_total > 1) : ?>
        <nav class="scp-quicknav" aria-label="<?php esc_attr_e('Bölüm kısayolları', 'seviye-storefront'); ?>">
            <?php foreach ($scp_sections as $scp_group_key => $scp_group_items) :
                if (empty($scp_group_items)) {
                    continue;
                }
                ?>
                <?php if (!empty($scp_group_labels[$scp_group_key])) : ?>
                    <span class="scp-quicknav__group-label"><?php echo esc_html($scp_group_labels[$scp_group_key]); ?></span>
                <?php endif; ?>
                <?php foreach ($scp_group_items as $scp_href => $scp_label) :
                    $scp_variant = $scp_section_variants[$scp_href] ?? null;
                    ?>
                    <?php if ($scp_variant) : ?>
                        <a href="<?php echo esc_url($scp_href); ?>" class="scp-module-tile scp-module-tile--<?php echo esc_attr($scp_variant); ?>">
                            <span class="scp-module-tile__icon"><?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- scp_module_icon_svg() returns one of a fixed set of hardcoded inline SVG strings (templates/partials/icon.php), no user input reaches it.
                                echo scp_module_icon_svg($scp_variant);
                            ?></span>
                            <span class="scp-module-tile__label"><?php echo esc_html($scp_label); ?></span>
                        </a>
                    <?php else : ?>
                        <a href="<?php echo esc_url($scp_href); ?>"><?php echo esc_html($scp_label); ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if (current_user_can('scp_view_reports') || current_user_can('scp_view_own_reports')) : ?>
        <section class="scp-card" id="scp-overview-panel">
            <div class="scp-card__header">
                <h2><?php esc_html_e('Genel Bakış', 'seviye-storefront'); ?></h2>
            </div>

            <p class="scp-status" data-scp-overview-status></p>

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

            <div class="scp-trend-chart" data-scp-overview-trend hidden>
                <h3><?php esc_html_e('Günlük Ciro Trendi (Son 30 Gün)', 'seviye-storefront'); ?></h3>
                <div class="scp-trend-chart__svg-host" data-scp-overview-trend-chart></div>
                <div class="scp-trend-chart__range">
                    <span data-scp-overview-trend-from></span>
                    <span data-scp-overview-trend-to></span>
                </div>
            </div>

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
        </section>
    <?php else : ?>
        <div class="scp-card scp-empty">
            <p><?php esc_html_e('Yukarıdaki menüden bir bölüm seçin.', 'seviye-storefront'); ?></p>
        </div>
    <?php endif; ?>

</div>
<?php
get_footer();
