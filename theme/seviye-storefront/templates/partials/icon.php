<?php

/**
 * Small inline SVG icon set. Hand-rolled outline paths rather than a
 * bundled icon font/library - this theme has no build step, so pulling in
 * a full icon set for a few dozen glyphs would be a heavier dependency
 * than writing them directly (the same "no external dependency for a
 * narrow, well-understood need" reasoning XlsxExporter's own zip writer
 * documents, see docs/ARCHITECTURE.md bölüm 16).
 *
 * İKİ FARKLI KULLANIM: (1) module-identity tiles (`.scp-module-tile`,
 * panel.css) - yalnızca platformun ~10 çekirdek modülü için, renkli bir
 * karo arka planıyla; (2) "site genelinde eksik ikonları bul/ekle"
 * turunda eklenen düz kenar çubuğu bağlantıları için nötr, küçük bir ikon
 * (`.scp-sidebar-nav__icon`, bkz. inc/sidebar.php'nin
 * scp_render_sidebar_link()'i) - kasıtlı olarak module-tile'ların renkli
 * karo görünümünü TAŞIMIYOR (bölüm 174'ün "yalnızca çekirdek moduller"
 * kararı hâlâ geçerli - Hesap ve Sistem grubundaki 9 ayar sayfasının her
 * biri kendi rengiyle bir karo olsaydı görsel olarak gürültülü olurdu),
 * yalnızca aynı hand-rolled SVG glif setini paylaşıyor.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string $name see the $paths array below for the full glyph list.
 *                      Unknown names render nothing (caller decides the
 *                      fallback).
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
        // "Site genelinde iconlara bak, koyulmamış iconlar var mı yoksa
        // koy" - kenar çubuğunda daha önce ikonu olmayan her bağlantı
        // için yeni bir glif (bkz. inc/sidebar.php'nin $plainIcons'u).
        'overview' => '<rect x="3" y="3" width="7" height="9" rx="1"/>'
            . '<rect x="14" y="3" width="7" height="5" rx="1"/>'
            . '<rect x="14" y="12" width="7" height="9" rx="1"/>'
            . '<rect x="3" y="16" width="7" height="5" rx="1"/>',
        'coupon' => '<path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5'
            . 'a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V8Z"/><path d="M9 6v12" stroke-dasharray="2 2"/>',
        'tax' => '<circle cx="7" cy="7" r="2.5"/><circle cx="17" cy="17" r="2.5"/><path d="M18 6 6 18"/>',
        'sizeguide' => '<path d="M3 16 16 3l5 5L8 21H3v-5Z"/><path d="M13 6l2 2M10 9l2 2M7 12l2 2"/>',
        'showcase' => '<rect x="3" y="4" width="18" height="14" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/>'
            . '<path d="M21 15l-5-5-4 4-3-3-6 6"/>',
        'broadcast' => '<path d="M3 10v4a1 1 0 0 0 1 1h2l4 4V5L6 9H4a1 1 0 0 0-1 1Z"/>'
            . '<path d="M14 8a4 4 0 0 1 0 8M17 5a8 8 0 0 1 0 14"/>',
        'security' => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3Z"/><path d="M9 12l2 2 4-4"/>',
        'privacy' => '<path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/>'
            . '<path d="M14 3v5h5"/><rect x="9" y="13" width="6" height="5" rx="1"/>'
            . '<path d="M10 13v-1.5a2 2 0 0 1 4 0V13"/>',
        'ip' => '<circle cx="12" cy="12" r="9"/>'
            . '<path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>',
        'sms' => '<path d="M4 4h16v12H8l-4 4V4Z"/><path d="M8 9h8M8 12h5"/>',
        'whatsapp' => '<path d="M4 20l1.2-3.6A8 8 0 1 1 8.4 19.8L4 20Z"/><path d="M9 10c0 3 2 5 5 5"/>',
        'email' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'appearance' => '<circle cx="12" cy="12" r="9"/><circle cx="8" cy="10" r="1.3"/>'
            . '<circle cx="12" cy="8" r="1.3"/><circle cx="16" cy="10" r="1.3"/>'
            . '<path d="M8 15a4 4 0 0 0 4 4c1.5 0 2.5-.8 2.5-2 0-.6-.5-1-1.2-1H13a2 2 0 0 1 0-4h4a4 4 0 0 0-4-4"/>',
        'activity' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        // Üst menü (header.php) için - önceden Bul/Çıkış Yap düğmelerinin
        // hiç ikonu yoktu.
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'logout' => '<path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4"/>'
            . '<path d="M16 17l5-5-5-5M21 12H9"/>',
        'bell' => '<path d="M6 9a6 6 0 0 1 12 0v5l2 3H4l2-3V9Z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
    ];

    if (! isset($paths[$name])) {
        return '';
    }

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}
