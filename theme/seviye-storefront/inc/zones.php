<?php

/**
 * Registers the /admin and /sube front-end routes (Genel Merkez / Şube
 * panels) as virtual, rewrite-rule-driven zones, and renders a minimal
 * honest landing view for them: the real panel content only exists once
 * the Branches/Students/Commerce modules are built (see docs/ROADMAP.md).
 * Reaching one of these routes at all already implies
 * inc/access-gate.php's role-zone check has passed.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'scp_register_zone_rewrites', 10);
add_action('init', 'scp_maybe_flush_zone_rewrite_rules', 20);
add_filter('query_vars', 'scp_register_zone_query_vars');
add_action('after_switch_theme', 'flush_rewrite_rules');

/*
 * Priority 10: runs after inc/access-gate.php's template_redirect handler
 * (priority 5), which has already confirmed the current user is logged in
 * and allowed into this zone.
 */
add_action('template_redirect', 'scp_render_zone_template', 10);

function scp_register_zone_rewrites(): void
{
    add_rewrite_rule('^admin/?$', 'index.php?scp_zone=admin', 'top');
    add_rewrite_rule('^admin/(.+)/?$', 'index.php?scp_zone=admin&scp_zone_path=$matches[1]', 'top');
    add_rewrite_rule('^sube/?$', 'index.php?scp_zone=sube', 'top');
    add_rewrite_rule('^sube/(.+)/?$', 'index.php?scp_zone=sube&scp_zone_path=$matches[1]', 'top');
    add_rewrite_rule('^profilim/?$', 'index.php?scp_zone=profilim', 'top');
    add_rewrite_rule('^siparislerim/?$', 'index.php?scp_zone=siparislerim', 'top');
    // "Tedarikçi portalı" - RoleRouter'ın bilmediği tek zone: erişimi Role
    // enum'ın kapalı kümesi yerine scp_depo_supplier_id_for_user filtresi
    // kararlaştırır, bkz. inc/access-gate.php'nin scp_enforce_role_zone()'u.
    add_rewrite_rule('^tedarikci/?$', 'index.php?scp_zone=tedarikci', 'top');
}

/**
 * `after_switch_theme` only fires the ONE time this theme is activated -
 * every zone rule added to scp_register_zone_rewrites() afterwards (this
 * is the third: /tedarikci landed after /siparislerim was already live)
 * never reaches the site's cached `rewrite_rules` option on an existing
 * install, so the new path 404s/falls through to index.php's shop
 * redirect until someone manually re-saves Settings -> Permalinks. Self-heal
 * instead: if the newest rule isn't in the cached set yet, flush once:
 * every rule registered above is always added together in the same
 * function, so the newest one's presence stands in for the whole set's
 * freshness without an explicit version counter to maintain.
 */
function scp_maybe_flush_zone_rewrite_rules(): void
{
    $rules = get_option('rewrite_rules');

    if (!is_array($rules) || !isset($rules['^tedarikci/?$'])) {
        flush_rewrite_rules();
    }
}

/**
 * @param list<string> $vars
 * @return list<string>
 */
function scp_register_zone_query_vars(array $vars): array
{
    $vars[] = 'scp_zone';
    $vars[] = 'scp_zone_path';

    return $vars;
}

function scp_render_zone_template(): void
{
    $zone = (string) get_query_var('scp_zone');

    if ($zone === '' || !is_user_logged_in()) {
        return;
    }

    // /profilim is the Veli's own profile page (children, iletişim
    // tercihleri, hesap güvenliği - moved here from the root '/', which now
    // redirects straight to the shop, see index.php). Reached at all only
    // implies inc/access-gate.php's role-zone check passed - the same as
    // /admin and /sube below - since RoleRouter has no special case for it,
    // it falls under the same "parent" zone every non-/admin, non-/sube
    // path does, which is exactly the Veli's own zone.
    if ($zone === 'profilim') {
        get_header();
        include SCP_THEME_DIR . '/templates/parent-dashboard.php';
        get_footer();
        exit;
    }

    // /siparislerim - Veli's own past orders (see templates/orders.php,
    // plugin/seviye-commerce/src/Http/OrdersRestController.php's /mine
    // endpoint). Same "reaching it at all already implies the role-zone
    // check passed" reasoning as /profilim above.
    if ($zone === 'siparislerim') {
        get_header();
        include SCP_THEME_DIR . '/templates/orders.php';
        get_footer();
        exit;
    }

    // /tedarikci - "Tedarikçi portalı": kendi WP hesabına scp_suppliers.user_id
    // ile bağlı bir tedarikçinin kendi satın alma siparişlerini görüp
    // "gönderildi" işaretleyebileceği ayrı bir yüzey (bkz.
    // plugin/seviye-depo/src/Http/PurchaseOrdersRestController.php'nin
    // /mine ve /{id}/mark-shipped endpoint'leri). Buraya erişim,
    // RoleRouter'ın rol->zone politikasından TAMAMEN bağımsız - bkz.
    // inc/access-gate.php'nin scp_enforce_role_zone()'u.
    if ($zone === 'tedarikci') {
        get_header();
        include SCP_THEME_DIR . '/templates/supplier-dashboard.php';
        get_footer();
        exit;
    }

    // /admin/siparisler and /sube/siparisler - Sipariş Yönetimi as its own
    // page rather than a section inside the big /admin or /sube dashboard
    // (originally a section there; moved out on explicit request - "arama
    // ile değil direkt siparişleri ayrı bir sayfa olarak göster"). Reuses
    // the `^admin/(.+)/?$` / `^sube/(.+)/?$` rewrite rules already
    // registered above (captured into scp_zone_path) rather than adding
    // yet another top-level rewrite rule: RoleRouter::isPathAllowedForRoles()
    // ties a Genel Merkez/Bölge Müdürü/Sistem user's allowed zone to
    // paths starting with /admin, and a Şube Müdürü/Muhasebe/Depo/Satış
    // Danışmanı/Rehberlik user's to /sube - a bare top-level `/siparisler`
    // (like veli's /siparislerim above) would be redirected away for
    // either group, since neither's role maps to RoleRouter's "parent"
    // zone. `rtrim` handles the trailing-slash variant PCRE's greedy
    // `(.+)` capture leaves in scp_zone_path for a `/admin/siparisler/`
    // request.
    $zonePath = rtrim((string) get_query_var('scp_zone_path'), '/');

    if (in_array($zone, ['admin', 'sube'], true) && $zonePath === 'siparisler') {
        if (!current_user_can('scp_view_orders') && !current_user_can('scp_view_own_branch_orders')) {
            wp_safe_redirect(home_url('/' . $zone));
            exit;
        }

        get_header();
        include SCP_THEME_DIR . '/templates/orders-admin.php';
        get_footer();
        exit;
    }

    // /admin/urunler and /sube/urunler - "Ürünler için ayrı bir sayfa" -
    // Ürünler as its own page rather than a section inside the big /admin
    // or /sube dashboard, the exact same split-out templates/orders-admin.php
    // got above ("Sipariş Yönetimi") and for the same reason: a catalog
    // with create/edit/variant/price-rule sub-structures needs real room.
    if (in_array($zone, ['admin', 'sube'], true) && $zonePath === 'urunler') {
        if (!current_user_can('scp_manage_products') && !current_user_can('scp_view_products')) {
            wp_safe_redirect(home_url('/' . $zone));
            exit;
        }

        get_header();
        include SCP_THEME_DIR . '/templates/products-admin.php';
        get_footer();
        exit;
    }

    $labels = [
        'admin' => __('Genel Merkez', 'seviye-storefront'),
        'sube' => __('Şube', 'seviye-storefront'),
    ];

    if (!isset($labels[$zone])) {
        return;
    }

    $scp_zone_label = $labels[$zone];

    include SCP_THEME_DIR . '/templates/zone.php';
    exit;
}

/**
 * Convenience for templates/inc files that need the raw zone key ('admin'
 * or 'sube') rather than its display label, e.g. to gate a section that
 * only makes sense in one of the two zones.
 */
function scp_current_zone(): string
{
    return (string) get_query_var('scp_zone');
}

/**
 * The Sipariş Yönetimi page's URL for the CURRENT zone - /admin/siparisler
 * inside /admin, /sube/siparisler inside /sube. Only meaningful from
 * within one of those two zones (see scp_render_zone_template()'s
 * /admin/siparisler /sube/siparisler branch above).
 */
function scp_admin_orders_path(): string
{
    return home_url('/' . scp_current_zone() . '/siparisler');
}

/**
 * The Ürünler page's URL for the CURRENT zone - /admin/urunler inside
 * /admin, /sube/urunler inside /sube. Only meaningful from within one of
 * those two zones (see scp_render_zone_template()'s /admin/urunler
 * /sube/urunler branch above).
 */
function scp_admin_products_path(): string
{
    return home_url('/' . scp_current_zone() . '/urunler');
}

/**
 * The URL the current user's own role lands them on - the same policy
 * inc/access-gate.php enforces on every request, reused here (not
 * reimplemented) so header.php can link "back home" from anywhere
 * (e.g. the WooCommerce shop/product pages, which have no zone of their
 * own) without guessing at a role->zone mapping the theme doesn't own.
 */
function scp_current_user_landing_path(): string
{
    // "Tedarikçi portalı" - bkz. inc/access-gate.php'nin scp_enforce_role_zone()'u
    // ile aynı öncelik: bağlı bir tedarikçi için RoleRouter'a hiç sorulmaz.
    $supplierId = apply_filters('scp_depo_supplier_id_for_user', null, get_current_user_id());

    if ($supplierId !== null) {
        return home_url('/tedarikci');
    }

    if (!class_exists(\Seviye\Security\Routing\RoleRouter::class)) {
        return home_url('/');
    }

    $roles = array_values(wp_get_current_user()->roles);

    return home_url(\Seviye\Security\Routing\RoleRouter::landingPathFor($roles));
}
