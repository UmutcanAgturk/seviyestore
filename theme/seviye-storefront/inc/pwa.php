<?php

/**
 * "Ana ekrana ekle" (Add to Home Screen) desteği - a dynamically generated
 * web app manifest rather than a static JSON file, since the theme has no
 * static icon asset of its own: the platform's logo is an admin-uploaded
 * WP attachment (see inc/branding.php), so the manifest has to be built at
 * request time from whatever is (or isn't) uploaded. Mirrors inc/zones.php's
 * rewrite-rule-driven virtual endpoint pattern exactly.
 *
 * This file also serves /service-worker.js (same virtual-endpoint pattern)
 * for genuine offline support - see scp_service_worker_js()'s docblock for
 * the caching strategy and why REST responses are deliberately excluded
 * from it.
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

add_action('init', 'scp_register_service_worker_rewrite', 10);
add_action('init', 'scp_maybe_flush_service_worker_rewrite_rule', 20);
add_filter('query_vars', 'scp_register_service_worker_query_var');
add_action('template_redirect', 'scp_render_service_worker', 1);
add_action('wp_footer', 'scp_print_service_worker_registration');

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

function scp_register_service_worker_rewrite(): void
{
    add_rewrite_rule('^service-worker\.js$', 'index.php?scp_service_worker=1', 'top');
}

/**
 * Same self-heal reasoning as scp_maybe_flush_manifest_rewrite_rule().
 */
function scp_maybe_flush_service_worker_rewrite_rule(): void
{
    $rules = get_option('rewrite_rules');

    if (!is_array($rules) || !isset($rules['^service-worker\.js$'])) {
        flush_rewrite_rules();
    }
}

/**
 * @param list<string> $vars
 * @return list<string>
 */
function scp_register_service_worker_query_var(array $vars): array
{
    $vars[] = 'scp_service_worker';

    return $vars;
}

/**
 * Priority 1, same reasoning as scp_render_manifest(): a service worker
 * fetch is the browser's own background request (and, unlike a manifest
 * fetch, MUST be served from the root to get root scope - see
 * https://developer.mozilla.org/docs/Web/API/ServiceWorkerContainer/register#scope),
 * so it must never be caught by access-gate.php's login redirect.
 */
function scp_render_service_worker(): void
{
    if ((string) get_query_var('scp_service_worker') !== '1') {
        return;
    }

    nocache_headers();
    header('Content-Type: application/javascript; charset=UTF-8');
    // Not strictly required for root-scope registration (root already gets
    // full-origin scope by default), but explicit is cheap and future-proofs
    // against ever moving this behind a rewrite that isn't at the root path.
    header('Service-Worker-Allowed: /');
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JS source, not HTML; the one dynamic value in it (site name) goes through wp_json_encode(), not raw interpolation.
    echo scp_service_worker_js();
    exit;
}

/**
 * Cache-busts the service worker's own CACHE_VERSION whenever any theme
 * CSS/JS asset changes, so a stale worker never keeps serving last week's
 * students-panel.js out of its stale-while-revalidate cache indefinitely -
 * same "derive from file mtimes, never a hand-bumped constant" reasoning as
 * scp_asset_version().
 */
function scp_service_worker_cache_version(): string
{
    $files = array_merge(
        glob(SCP_THEME_DIR . '/assets/css/*.css') ?: [],
        glob(SCP_THEME_DIR . '/assets/js/*.js') ?: []
    );

    $mtimes = array_map('filemtime', $files);
    sort($mtimes);

    return substr(md5(implode(',', $mtimes)), 0, 12);
}

/**
 * Deliberately narrow caching strategy - this is a login-gated panel app
 * where almost every screen is a REST-backed view of live financial/
 * inventory/student data (orders, hakediş balances, stock counts...), so
 * "real offline support" here can only ever mean two things:
 *
 * 1. The static app shell (theme CSS/JS) loads instantly and works offline
 *    on a repeat visit, via stale-while-revalidate - serve the cached copy
 *    immediately, refetch in the background to update the cache for next
 *    time.
 * 2. A page navigation that fails outright (no network at all) gets a
 *    readable "you're offline" screen instead of the browser's default
 *    connection-error page.
 *
 * `/wp-json/` REST responses are NEVER cached or intercepted here - every
 * panel screen's actual data (order totals, hakediş balances, stock
 * levels...) must always come from the network. Silently serving a cached
 * REST response while "offline" would show stale money/inventory numbers
 * with no visual indication they're stale, which is worse than the normal
 * loading-error state those screens already handle.
 */
function scp_service_worker_js(): string
{
    $cacheName = 'scp-shell-' . scp_service_worker_cache_version();
    $offlineHtml = scp_service_worker_offline_html();

    $cacheNameJs = wp_json_encode($cacheName);
    $offlineHtmlJs = wp_json_encode($offlineHtml);

    return <<<JS
const CACHE_NAME = {$cacheNameJs};
const OFFLINE_HTML = {$offlineHtmlJs};

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys()
            .then(function (keys) {
                return Promise.all(
                    keys.filter(function (key) {
                        return key !== CACHE_NAME;
                    }).map(function (key) {
                        return caches.delete(key);
                    })
                );
            })
            .then(function () {
                return self.clients.claim();
            })
    );
});

self.addEventListener('fetch', function (event) {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Never intercept the REST API - every panel screen's data must always
    // be live, never a stale cached response served while "offline".
    if (url.pathname.indexOf('/wp-json/') === 0) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(function () {
                return new Response(OFFLINE_HTML, {
                    headers: {'Content-Type': 'text/html; charset=utf-8'},
                });
            })
        );
        return;
    }

    if (url.pathname.indexOf('/wp-content/themes/') !== -1) {
        event.respondWith(
            caches.open(CACHE_NAME).then(function (cache) {
                return cache.match(request).then(function (cached) {
                    const network = fetch(request).then(function (response) {
                        if (response && response.ok) {
                            cache.put(request, response.clone());
                        }
                        return response;
                    }).catch(function () {
                        return cached;
                    });

                    return cached || network;
                });
            })
        );
    }
});
JS;
}

function scp_service_worker_offline_html(): string
{
    $siteName = esc_html(get_bloginfo('name'));

    return '<!doctype html><html lang="tr"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . $siteName . ' - Çevrimdışı</title>'
        . '<style>body{font-family:system-ui,sans-serif;background:#f3f5f9;color:#14326b;'
        . 'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;'
        . 'text-align:center;padding:24px}main{max-width:360px}h1{font-size:1.25rem}</style>'
        . '</head><body><main>'
        . '<h1>Şu anda çevrimdışısınız</h1>'
        . '<p>' . $siteName . ' bu sayfayı görüntülemek için bir internet bağlantısı gerektiriyor. '
        . 'Bağlantınızı kontrol edip tekrar deneyin.</p>'
        . '<p><a href="/">Yeniden dene</a></p>'
        . '</main></body></html>';
}

/**
 * Registered unconditionally (login screen included, like the manifest
 * link) so a repeat visit to the login screen itself also benefits from
 * the cached app shell - most useful for a branch/parent user re-opening
 * the site from a home-screen icon with a flaky connection.
 */
function scp_print_service_worker_registration(): void
{
    ?>
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/service-worker.js');
        });
    }
    </script>
    <?php
}
