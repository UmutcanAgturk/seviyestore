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

add_action('init', 'scp_register_zone_rewrites');
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
