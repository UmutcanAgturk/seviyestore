<?php

/**
 * Enforces the optional /admin IP allowlist (Seviye Security,
 * scp_settings key security.admin_ip_allowlist - configured via the
 * "Hesap Güvenliği" panel's IP kısıtlaması card, Genel Merkez only).
 *
 * Deliberately the same enforcement boundary as inc/access-gate.php's role
 * -> zone gate: a template_redirect hook that only ever gates page
 * rendering, never REST calls. This is not a new gap IP restriction
 * introduces - the platform's REST endpoints have always relied on their
 * own RBAC capability checks (current_user_can()), not on zone/IP gating,
 * so a stolen session cookie already bypasses both gates equally today.
 * Documented here explicitly so this scope decision doesn't read as an
 * oversight.
 *
 * Priority 6: after inc/access-gate.php's role-zone check (priority 5,
 * which has already confirmed this request is legitimately reaching
 * /admin for this role) and before inc/zones.php's template render
 * (priority 10).
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('template_redirect', 'scp_enforce_admin_ip_allowlist', 6);

function scp_enforce_admin_ip_allowlist(): void
{
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }

    if (scp_current_zone() !== 'admin') {
        return;
    }

    if (!class_exists(\Seviye\Security\Routing\IpAllowlist::class) || !class_exists(\Seviye\Core\Plugin::class)) {
        return;
    }

    $settings = \Seviye\Core\Plugin::instance()->container()->get(
        \Seviye\Core\Settings\SettingsRepositoryInterface::class
    );
    $rawAllowlist = $settings->get(\Seviye\Security\Routing\IpAllowlist::SETTING_KEY);
    $entries = \Seviye\Security\Routing\IpAllowlist::parseEntries($rawAllowlist);
    $ip = \Seviye\Core\Security\ClientIp::resolve();

    if (\Seviye\Security\Routing\IpAllowlist::isAllowed($entries, $ip)) {
        return;
    }

    wp_die(
        esc_html__('Bu IP adresinden Genel Merkez paneline erişim izniniz yok.', 'seviye-storefront'),
        esc_html__('Erişim Engellendi', 'seviye-storefront'),
        ['response' => 403]
    );
}
