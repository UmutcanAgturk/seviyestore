<?php

/**
 * "Ana ekrana ekle" (Add to Home Screen) desteği - a dynamically generated
 * web app manifest rather than a static JSON file, since the theme has no
 * static icon asset of its own: the platform's logo is an admin-uploaded
 * WP attachment (see inc/branding.php), so the manifest has to be built at
 * request time from whatever is (or isn't) uploaded. Mirrors inc/zones.php's
 * rewrite-rule-driven virtual endpoint pattern exactly.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'scp_register_manifest_rewrite', 10);
add_action('init', 'scp_maybe_flush_manifest_rewrite_rule', 20);
add_filter('query_vars', 'scp_register_manifest_query_var');
add_action('template_redirect', 'scp_render_manifest', 1);
add_action('wp_head', 'scp_print_manifest_link');

function scp_register_manifest_rewrite(): void
{
    add_rewrite_rule('^manifest\.webmanifest$', 'index.php?scp_manifest=1', 'top');
}

/**
 * This rule landed after inc/zones.php's own rules did, on an install that
 * may already have flushed once - same self-heal reasoning as
 * scp_maybe_flush_zone_rewrite_rules(), applied to this rule specifically
 * so an existing site picks it up without a manual Settings > Permalinks
 * re-save.
 */
function scp_maybe_flush_manifest_rewrite_rule(): void
{
    $rules = get_option('rewrite_rules');

    if (!is_array($rules) || !isset($rules['^manifest\.webmanifest$'])) {
        flush_rewrite_rules();
    }
}

/**
 * @param list<string> $vars
 * @return list<string>
 */
function scp_register_manifest_query_var(array $vars): array
{
    $vars[] = 'scp_manifest';

    return $vars;
}

/**
 * Priority 1: must run before inc/access-gate.php's template_redirect
 * handler (priority 5), which otherwise renders the login screen (and
 * exits) for every logged-out request - a manifest fetch is the browser's
 * own background request, never a user navigation, so it must never be
 * caught by that gate.
 */
function scp_render_manifest(): void
{
    if ((string) get_query_var('scp_manifest') !== '1') {
        return;
    }

    nocache_headers();
    header('Content-Type: application/manifest+json; charset=UTF-8');
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() output, not HTML.
    echo wp_json_encode(scp_manifest_data());
    exit;
}

/**
 * @return array<string, mixed>
 */
function scp_manifest_data(): array
{
    $name = get_bloginfo('name');

    return [
        'name' => $name,
        'short_name' => mb_substr($name, 0, 12),
        'start_url' => home_url('/'),
        'display' => 'standalone',
        'background_color' => '#f3f5f9',
        'theme_color' => '#14326b',
        'icons' => scp_manifest_icons(),
    ];
}

/**
 * Empty when no logo has been uploaded yet (Settings > Görünüm, Genel
 * Merkez only) - a manifest with no icons is still valid, browsers just
 * fall back to a generic app icon for "Add to Home Screen".
 *
 * @return list<array{src: string, sizes: string, type: string}>
 */
function scp_manifest_icons(): array
{
    $attachmentId = scp_logo_attachment_id();

    if ($attachmentId <= 0) {
        return [];
    }

    $icons = [];

    foreach (['thumbnail', 'full'] as $size) {
        $image = wp_get_attachment_image_src($attachmentId, $size);

        if ($image === false) {
            continue;
        }

        [$url, $width, $height] = $image;
        $mimeType = get_post_mime_type($attachmentId);

        $icons[] = [
            'src' => $url,
            'sizes' => $width . 'x' . $height,
            'type' => is_string($mimeType) && $mimeType !== '' ? $mimeType : 'image/png',
        ];
    }

    return $icons;
}

/**
 * iOS Safari does not read the web manifest's icons array at all (its
 * "Add to Home Screen" only ever looks at a plain <link rel="apple-touch-icon">)
 * so this is printed alongside the manifest link, not instead of it.
 */
function scp_print_manifest_link(): void
{
    printf(
        '<link rel="manifest" href="%s">' . "\n",
        esc_url(home_url('/manifest.webmanifest'))
    );
    printf('<meta name="theme-color" content="%s">' . "\n", esc_attr('#14326b'));

    $touchIconUrl = scp_logo_url('full');

    if ($touchIconUrl !== null) {
        printf('<link rel="apple-touch-icon" href="%s">' . "\n", esc_url($touchIconUrl));
    }
}
