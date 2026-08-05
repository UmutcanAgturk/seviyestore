<?php

/**
 * Left sidebar navigation for /admin and /sube - "menüleri üst tarafa
 * koymak yerine sol tarafa al" (bölüm 66). Rendered from header.php (not
 * templates/zone.php) so it appears on EVERY staff page - the standalone
 * pages bölüm 65 split out (Öğrenciler, Fiyat Kuralları, Depo, ...) had no
 * menu of their own once you left the /admin,/sube root; only "← Panele
 * Dön" - see header.php's own docblock note on scp_render_sidebar().
 *
 * This REPLACES zone.php's own top quicknav (bölüm 64's grouping), not
 * just relocates it - $scp_sections/$scp_group_labels/$scp_section_variants
 * used to be built inline in templates/zone.php; that logic now lives here
 * as scp_sidebar_sections() so both header.php (every page) and nothing
 * else need it. zone.php itself no longer builds or renders a nav.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Same grouping/capability checks bölüm 64/65 already established, just
 * relocated - see this file's own docblock for why. "Genel Bakış" is the
 * one entry whose href is NOT scp_menu_page_path() - it still lives on the
 * /admin,/sube ROOT itself (see templates/zone.php), not its own sub-page,
 * so its link is the bare zone root rather than a slug.
 *
 * @return array{
 *     groups: array<string, array<string, string>>,
 *     labels: array<string, string|null>,
 *     variants: array<string, string>,
 *     total: int
 * }
 */
function scp_sidebar_sections(): array
{
    $groups = [
        'genel' => [],
        'katalog' => [],
        'operasyon' => [],
        'kisiler' => [],
        'finans' => [],
        'iletisim' => [],
        'hesap' => [],
    ];

    $labels = [
        'genel' => null,
        'katalog' => __('Katalog', 'seviye-storefront'),
        'operasyon' => __('Operasyon', 'seviye-storefront'),
        'kisiler' => __('Kişiler', 'seviye-storefront'),
        'finans' => __('Finans', 'seviye-storefront'),
        'iletisim' => __('İletişim', 'seviye-storefront'),
        'hesap' => __('Hesap ve Sistem', 'seviye-storefront'),
    ];

    if (current_user_can('scp_view_reports') || current_user_can('scp_view_own_reports')) {
        $groups['genel'][home_url('/' . scp_current_zone())] = __('Genel Bakış', 'seviye-storefront');
    }

    if (current_user_can('scp_manage_students')) {
        $groups['kisiler'][scp_menu_page_path('ogrenciler')] = __('Öğrenciler', 'seviye-storefront');
    }

    if (scp_current_zone() === 'admin' && current_user_can('scp_manage_branches')) {
        $groups['kisiler'][scp_menu_page_path('subeler')] = __('Şubeler', 'seviye-storefront');
    }

    if (current_user_can('scp_manage_products') || current_user_can('scp_view_products')) {
        $groups['katalog'][scp_admin_products_path()] = __('Ürünler', 'seviye-storefront');
    }

    if (current_user_can('scp_view_orders') || current_user_can('scp_view_own_branch_orders')) {
        $groups['operasyon'][scp_admin_orders_path()] = __('Siparişler', 'seviye-storefront');
    }

    if (current_user_can('scp_manage_pricing')) {
        $groups['katalog'][scp_menu_page_path('fiyatlandirma')] = __('Fiyat Kuralları', 'seviye-storefront');
    }

    if (current_user_can('scp_manage_coupons')) {
        $groups['katalog'][scp_menu_page_path('kampanyalar')] = __('Kampanya Kodları', 'seviye-storefront');
    }

    if (current_user_can('scp_manage_purchase_orders')) {
        $groups['operasyon'][scp_menu_page_path('depo')] = __('Depo', 'seviye-storefront');
    }

    if (current_user_can('scp_view_hakedis') || current_user_can('scp_view_own_hakedis')) {
        $groups['finans'][scp_menu_page_path('cari-bakiye')] = __('Cari Bakiye', 'seviye-storefront');
    }

    if (current_user_can('scp_view_reports') || current_user_can('scp_view_own_reports')) {
        $groups['finans'][scp_menu_page_path('raporlar')] = __('Raporlar', 'seviye-storefront');
    }

    if (current_user_can('scp_send_broadcast') || current_user_can('scp_send_own_branch_broadcast')) {
        $groups['iletisim'][scp_menu_page_path('duyuru')] = __('Toplu Duyuru', 'seviye-storefront');
    }

    if (current_user_can('scp_manage_support_tickets')) {
        $groups['iletisim'][scp_menu_page_path('destek-talepleri')] = __('Destek Talepleri', 'seviye-storefront');
    }

    $groups['hesap'][scp_menu_page_path('hesap-guvenligi')] = __('Hesap Güvenliği', 'seviye-storefront');
    $groups['hesap'][scp_menu_page_path('kvkk')] = __('Verilerim (KVKK)', 'seviye-storefront');

    if (scp_current_zone() === 'admin' && current_user_can('scp_manage_privacy_requests')) {
        $groups['hesap'][scp_menu_page_path('kvkk-talepleri')] = __('KVKK Talepleri', 'seviye-storefront');
    }

    if (scp_current_zone() === 'admin' && current_user_can('scp_manage_security_settings')) {
        $groups['hesap'][scp_menu_page_path('ip-kisitlamasi')] = __('IP Kısıtlaması', 'seviye-storefront');
    }

    if (scp_current_zone() === 'admin' && current_user_can('scp_manage_notification_settings')) {
        $groups['hesap'][scp_menu_page_path('sms-ayarlari')] = __('SMS Ayarları', 'seviye-storefront');
        $groups['hesap'][scp_menu_page_path('eposta-ayarlari')] = __('E-posta Ayarları', 'seviye-storefront');
    }

    if (scp_current_zone() === 'admin' && current_user_can('scp_manage_api_keys')) {
        $groups['hesap'][scp_menu_page_path('api-anahtarlari')] = __('API Anahtarları', 'seviye-storefront');
    }

    if (scp_current_zone() === 'admin' && current_user_can('scp_manage_core_settings')) {
        $groups['hesap'][scp_menu_page_path('gorunum')] = __('Görünüm', 'seviye-storefront');
    }

    if (scp_current_zone() === 'admin' && current_user_can('scp_view_audit_logs')) {
        $groups['hesap'][scp_menu_page_path('aktivite-gunlugu')] = __('Aktivite Günlüğü', 'seviye-storefront');
    }

    // Module-identity tiles (.scp-module-tile in panel.css) only exist for
    // the platform's core modules - everything else (account security,
    // KVKK, broadcast, ...) renders as a plain sidebar link.
    $variants = [
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

    return [
        'groups' => $groups,
        'labels' => $labels,
        'variants' => $variants,
        'total' => array_sum(array_map('count', $groups)),
    ];
}

/**
 * True only on the two zones that have a sidebar at all - Veli's own
 * zones (profilim, siparislerim, the WooCommerce shop, ...) and the
 * Tedarikçi portalı keep header.php's plain top nav, unchanged.
 */
function scp_has_sidebar(): bool
{
    return in_array(scp_current_zone(), ['admin', 'sube'], true);
}

/**
 * Renders the sidebar `<aside>` - a no-op (echoes nothing) outside
 * /admin,/sube, or when there's nothing to show (a role with exactly one
 * reachable section gains nothing from a nav to itself, same threshold
 * zone.php's old quicknav used).
 *
 * "Sıralı olsun, menünün üzerine geldiğimizde alt menüler görünsün" -
 * groups are collapsed list items; .scp-sidebar-nav's own CSS (theme.css)
 * reveals a group's submenu on hover (mouse) AND :focus-within (keyboard),
 * assets/js/scp-ui-kit.js also toggles an `is-open` class on click/tap for
 * touch devices, where hover doesn't exist.
 */
function scp_render_sidebar(): void
{
    if (!scp_has_sidebar()) {
        return;
    }

    $sections = scp_sidebar_sections();

    if ($sections['total'] < 1) {
        return;
    }

    $groups = $sections['groups'];
    $labels = $sections['labels'];
    $variants = $sections['variants'];

    ?>
    <aside class="scp-sidebar">
        <nav
            class="scp-quicknav scp-sidebar-nav"
            aria-label="<?php esc_attr_e('Bölüm menüsü', 'seviye-storefront'); ?>"
        >
            <ul class="scp-sidebar-nav__list">
                <?php foreach ($groups as $groupKey => $items) :
                    if (empty($items)) {
                        continue;
                    }

                    $groupLabel = $labels[$groupKey] ?? null;
                    ?>
                    <?php if ($groupLabel === null) : ?>
                        <?php foreach ($items as $href => $label) :
                            $variant = $variants[$href] ?? null;
                            ?>
                            <li class="scp-sidebar-nav__item">
                                <?php scp_render_sidebar_link($href, $label, $variant); ?>
                            </li>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <li class="scp-sidebar-nav__item scp-sidebar-nav__item--group">
                            <button type="button" class="scp-sidebar-nav__group-toggle" data-scp-sidebar-toggle>
                                <?php echo esc_html($groupLabel); ?>
                                <span class="scp-sidebar-nav__chevron" aria-hidden="true"></span>
                            </button>
                            <ul class="scp-sidebar-nav__submenu">
                                <?php foreach ($items as $href => $label) :
                                    $variant = $variants[$href] ?? null;
                                    ?>
                                    <li><?php scp_render_sidebar_link($href, $label, $variant); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </nav>
    </aside>
    <?php
}

function scp_render_sidebar_link(string $href, string $label, ?string $variant): void
{
    if ($variant) :
        ?>
        <a
            href="<?php echo esc_url($href); ?>"
            class="scp-module-tile scp-module-tile--<?php echo esc_attr($variant); ?>"
        >
            <span class="scp-module-tile__icon"><?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- scp_module_icon_svg() returns one of a fixed set of hardcoded inline SVG strings (templates/partials/icon.php), no user input reaches it.
                echo scp_module_icon_svg($variant);
            ?></span>
            <span class="scp-sidebar-nav__label"><?php echo esc_html($label); ?></span>
        </a>
        <?php
    else :
        ?>
        <a href="<?php echo esc_url($href); ?>" class="scp-sidebar-nav__label"><?php echo esc_html($label); ?></a>
        <?php
    endif;
}
