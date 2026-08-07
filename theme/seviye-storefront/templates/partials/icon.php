<?php

/**
 * Small inline SVG icon set for module-identity tiles (see .scp-module-tile
 * in assets/css/panel.css). Hand-rolled outline paths rather than a bundled
 * icon font/library - this theme has no build step, so pulling in a full
 * icon set for ~10 glyphs would be a heavier dependency than writing them
 * directly (the same "no external dependency for a narrow, well-understood
 * need" reasoning XlsxExporter's own zip writer documents, see
 * docs/ARCHITECTURE.md bölüm 16).
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string $name one of: students, branches, products, orders, pricing,
 *                      depo, hakedis, reports, support, settings. Unknown
 *                      names render nothing (caller decides the fallback).
 */
function scp_module_icon_svg(string $name): string
{
    $paths = [
        'students' => '<path d="M12 4 3 8l9 4 9-4-9-4Z"/>'
            . '<path d="M7 10v5c0 1.7 2.2 3 5 3s5-1.3 5-3v-5"/>'
            . '<path d="M21 8v6"/>',
        'branches' => '<path d="M12 4 4 9h16L12 4Z"/>'
            . '<rect x="4" y="9" width="16" height="11" rx="1"/>'
            . '<path d="M9 20v-5h6v5"/>',
        'products' => '<path d="M3 7l9-4 9 4-9 4-9-4Z"/>'
            . '<path d="M3 7v10l9 4 9-4V7"/>'
            . '<path d="M12 11v10"/>',
        'orders' => '<rect x="4" y="5" width="16" height="16" rx="2"/>'
            . '<path d="M9 3v4M15 3v4M4 10h16"/>',
        'pricing' => '<path d="M12 2v20"/>'
            . '<path d="M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'depo' => '<path d="M3 10 12 4l9 6"/>'
            . '<path d="M5 10v10h14V10"/>'
            . '<path d="M9 20v-7h6v7"/>',
        'hakedis' => '<rect x="3" y="6" width="18" height="13" rx="2"/>'
            . '<path d="M16.5 12h2.5v3h-2.5a1.5 1.5 0 0 1 0-3Z"/>'
            . '<path d="M3 9h18"/>',
        'reports' => '<path d="M4 20V10M10 20V4M16 20v-7M2 20h20"/>',
        'support' => '<path d="M4 13v-1a8 8 0 0 1 16 0v1"/>'
            . '<rect x="3" y="13" width="4" height="6" rx="1"/>'
            . '<rect x="17" y="13" width="4" height="6" rx="1"/>'
            . '<path d="M19 19a4 4 0 0 1-4 3h-2"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/>'
            . '<path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1'
            . 'M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        // "Mobilde alt gezinme çubuğu" (bkz. header.php) - veli için üç yeni
        // glif, aynı hand-rolled outline SVG ilkesiyle.
        'home' => '<path d="M4 11 12 4l8 7"/>'
            . '<path d="M6 10v9a1 1 0 0 0 1 1h4v-6h2v6h4a1 1 0 0 0 1-1v-9"/>',
        'cart' => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/>'
            . '<path d="M3 4h2l2.2 11.5a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 8H6"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/>'
            . '<path d="M4 20c0-3.9 3.6-7 8-7s8 3.1 8 7"/>',
    ];

    if (! isset($paths[$name])) {
        return '';
    }

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}
