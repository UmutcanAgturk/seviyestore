<?php

/**
 * Enforces "no product, no page, nothing is visible without a login" and,
 * once logged in, that a user only ever sees the zone their role owns
 * (Veli: /, Şube: /sube, Genel Merkez: /admin) - see
 * Seviye\Security\Routing\RoleRouter, which owns the actual role -> zone
 * policy so it stays unit-tested outside of WordPress.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Priority 5: must run before inc/zones.php's template_redirect handler
 * (priority 10), so a wrong-zone request is redirected away before that
 * zone's template ever gets a chance to render.
 */
add_action('template_redirect', 'scp_enforce_access_gate', 5);

function scp_enforce_access_gate(): void
{
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }

    if (!is_user_logged_in()) {
        scp_render_login_screen();
        exit;
    }

    scp_enforce_role_zone();
}

function scp_render_login_screen(): void
{
    status_header(200);
    nocache_headers();

    include SCP_THEME_DIR . '/templates/login.php';
}

function scp_enforce_role_zone(): void
{
    if (!class_exists(\Seviye\Security\Routing\RoleRouter::class)) {
        return;
    }

    $roles = array_values(wp_get_current_user()->roles);
    $requestPath = scp_current_request_path();

    if (\Seviye\Security\Routing\RoleRouter::isPathAllowedForRoles($requestPath, $roles)) {
        return;
    }

    wp_safe_redirect(home_url(\Seviye\Security\Routing\RoleRouter::landingPathFor($roles)));
    exit;
}

function scp_current_request_path(): string
{
    $requestUri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';

    return (string) wp_parse_url($requestUri, PHP_URL_PATH);
}
