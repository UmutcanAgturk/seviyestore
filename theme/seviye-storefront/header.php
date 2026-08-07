<?php

/**
 * Header for authenticated views only: inc/access-gate.php renders
 * templates/login.php (its own standalone document) for every logged-out
 * request, so by the time this file runs, is_user_logged_in() is true.
 *
 * scp_render_sidebar() (inc/sidebar.php) renders /admin,/sube's left nav
 * HERE - not in templates/zone.php, where it used to live as a top
 * "quicknav" (bölüm 64) - so it appears on EVERY staff page (Öğrenciler,
 * Fiyat Kuralları, Depo, ...bölüm 65's ~20 standalone pages), not just the
 * /admin,/sube root. "Menüleri üst tarafa koymak yerine sol tarafa al"
 * (bölüm 66). It's a no-op (echoes nothing) outside those two zones, so
 * .scp-layout still wraps <main> unconditionally below - simpler than
 * conditionally wrapping, and an empty <aside> that scp_has_sidebar()
 * skips means .scp-layout with no sidebar child just behaves like a plain
 * block, no visual difference from before this round.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php scp_theme_preload_script(); ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="scp-skip-link" href="#scp-main-content">
    <?php esc_html_e('İçeriğe geç', 'seviye-storefront'); ?>
</a>
<header class="scp-site-header">
    <?php
    // 'thumbnail' is WordPress' one HARD-CROPPED image size (square by
    // default, regardless of the uploaded logo's own aspect ratio) - a wide
    // logo came out visibly clipped ("logoyu da tam görünür şekilde
    // olsun"), and no amount of CSS on the <img> can recover pixels the
    // crop already discarded server-side. 'medium' (like templates/login.php
    // already uses) is a proportional resize, no cropping - object-fit:
    // contain + the header's own max-height/max-width below still keep it
    // small on screen.
    $scp_header_logo_url = scp_logo_url('medium');
    ?>
    <div class="scp-site-header__brand">
        <a class="scp-site-header__brand-link" href="<?php echo esc_url(scp_current_user_landing_path()); ?>">
            <?php if ($scp_header_logo_url !== null) : ?>
                <img class="scp-site-header__logo" src="<?php echo esc_url($scp_header_logo_url); ?>" alt="">
            <?php else : ?>
                <span class="scp-site-header__mark" aria-hidden="true">S</span>
            <?php endif; ?>
            <?php bloginfo('name'); ?>
        </a>
    </div>
    <?php
    /**
     * "Site genelinde iconlara bak, koyulmamış iconlar var mı yoksa koy" -
     * üst menünün her bağlantısı/düğmesi önceden düz metindi, hepsine
     * `.scp-site-header__nav-icon` ile küçük, nötr bir glif eklendi -
     * kenar çubuğunun `.scp-sidebar-nav__icon`'uyla AYNI ilke
     * (renkli module-tile DEĞİL, currentColor'la metin rengini takip
     * eden düz bir ikon).
     */
    ?>
    <nav class="scp-site-header__nav">
        <button
            type="button"
            class="scp-command-trigger"
            data-scp-command-trigger
            aria-label="<?php esc_attr_e('Bul (Cmd+K)', 'seviye-storefront'); ?>"
        >
            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- scp_module_icon_svg() returns one of a fixed set of hardcoded inline SVG strings (templates/partials/icon.php), no user input reaches it. ?>
            <span class="scp-site-header__nav-icon"><?php echo scp_module_icon_svg('search'); ?></span>
            <?php esc_html_e('Bul', 'seviye-storefront'); ?>
            <kbd aria-hidden="true">⌘K</kbd>
        </button>
        <?php if (function_exists('wc_get_page_permalink') && current_user_can('scp_view_own_children')) : ?>
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">
                <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- scp_module_icon_svg() returns one of a fixed set of hardcoded inline SVG strings (templates/partials/icon.php), no user input reaches it. ?>
                <span class="scp-site-header__nav-icon"><?php echo scp_module_icon_svg('home'); ?></span>
                <?php esc_html_e('Mağaza', 'seviye-storefront'); ?>
            </a>
            <?php $scp_cart_count = function_exists('WC') && WC()->cart ? WC()->cart->get_cart_contents_count() : 0; ?>
            <a href="<?php echo esc_url(wc_get_cart_url()); ?>" data-scp-mini-cart-trigger>
                <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- scp_module_icon_svg() returns one of a fixed set of hardcoded inline SVG strings (templates/partials/icon.php), no user input reaches it. ?>
                <span class="scp-site-header__nav-icon"><?php echo scp_module_icon_svg('cart'); ?></span>
                <?php esc_html_e('Sepetim', 'seviye-storefront'); ?>
                <?php if ($scp_cart_count > 0) : ?>
                    <span class="scp-cart-count"><?php echo esc_html((string) $scp_cart_count); ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (current_user_can('scp_view_own_children') || current_user_can('scp_manage_own_profile')) : ?>
            <a href="<?php echo esc_url(home_url('/siparislerim')); ?>">
                <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- scp_module_icon_svg() returns one of a fixed set of hardcoded inline SVG strings (templates/partials/icon.php), no user input reaches it. ?>
                <span class="scp-site-header__nav-icon"><?php echo scp_module_icon_svg('orders'); ?></span>
                <?php esc_html_e('Siparişlerim', 'seviye-storefront'); ?>
            </a>
            <a href="<?php echo esc_url(home_url('/profilim')); ?>">
                <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- scp_module_icon_svg() returns one of a fixed set of hardcoded inline SVG strings (templates/partials/icon.php), no user input reaches it. ?>
                <span class="scp-site-header__nav-icon"><?php echo scp_module_icon_svg('profile'); ?></span>
                <?php esc_html_e('Profilim', 'seviye-storefront'); ?>
            </a>
        <?php endif; ?>
        <div class="scp-notif-bell" id="scp-notifications-bell">
            <button
                type="button"
                class="scp-notif-bell__toggle"
                data-scp-notif-toggle
                aria-haspopup="true"
                aria-expanded="false"
            >
                <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- scp_module_icon_svg() returns one of a fixed set of hardcoded inline SVG strings (templates/partials/icon.php), no user input reaches it. ?>
                <span class="scp-site-header__nav-icon"><?php echo scp_module_icon_svg('bell'); ?></span>
                <?php esc_html_e('Bildirimler', 'seviye-storefront'); ?>
                <span class="scp-notif-bell__badge" data-scp-notif-badge hidden></span>
            </button>
            <div class="scp-notif-bell__panel" data-scp-notif-panel hidden>
                <p class="scp-status" data-scp-notif-status></p>
                <ul class="scp-list" data-scp-notif-list></ul>
            </div>
        </div>
        <button type="button" class="scp-theme-toggle" data-scp-theme-toggle></button>
        <span class="scp-site-header__user">
            <?php scp_render_avatar(wp_get_current_user()->display_name); ?>
            <?php echo esc_html(wp_get_current_user()->display_name); ?>
        </span>
        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">
            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- scp_module_icon_svg() returns one of a fixed set of hardcoded inline SVG strings (templates/partials/icon.php), no user input reaches it. ?>
            <span class="scp-site-header__nav-icon"><?php echo scp_module_icon_svg('logout'); ?></span>
            <?php esc_html_e('Çıkış Yap', 'seviye-storefront'); ?>
        </a>
    </nav>
</header>
<?php if (function_exists('scp_render_mini_cart_drawer')) : ?>
    <?php scp_render_mini_cart_drawer(); ?>
<?php endif; ?>
<?php
/**
 * "Mobilde alt gezinme çubuğu" - yalnızca veli için (üstteki
 * `scp_view_own_children`/`scp_manage_own_profile` kontrolleriyle AYNI
 * roller, AYNI dört hedef: Mağaza, Sepetim, Siparişlerim, Profilim), CSS
 * ile yalnızca dar ekranlarda (`max-width: 640px`, panel.css'in kendi
 * mobil kırılma noktası) görünür - masaüstünde zaten üst menüde var,
 * burada tekrar gösterilmiyor. Şube/Genel Merkez/Muhasebe/Depo gibi
 * personel rolleri için basılmıyor - onlar zaten sol kenar çubuğuna sahip
 * (bkz. inc/sidebar.php), mobilde onun için AYRI bir çözüm (küçük ekranda
 * aç/kapa) zaten var, burada ikinci bir gezinme sistemi eklenmiyor.
 */
$scp_is_parent_zone = current_user_can('scp_view_own_children') || current_user_can('scp_manage_own_profile');
?>
<?php if ($scp_is_parent_zone && function_exists('wc_get_page_permalink')) : ?>
    <nav class="scp-mobile-bottom-nav" aria-label="<?php esc_attr_e('Mobil gezinme', 'seviye-storefront'); ?>">
        <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">
            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- scp_module_icon_svg() returns one of a fixed set of hardcoded inline SVG strings (templates/partials/icon.php), no user input reaches it. ?>
            <?php echo scp_module_icon_svg('home'); ?>
            <span><?php esc_html_e('Ana Sayfa', 'seviye-storefront'); ?></span>
        </a>
        <a href="<?php echo esc_url(wc_get_cart_url()); ?>">
            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- scp_module_icon_svg() returns one of a fixed set of hardcoded inline SVG strings (templates/partials/icon.php), no user input reaches it. ?>
            <?php echo scp_module_icon_svg('cart'); ?>
            <span><?php esc_html_e('Sepet', 'seviye-storefront'); ?></span>
        </a>
        <a href="<?php echo esc_url(home_url('/siparislerim')); ?>">
            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- scp_module_icon_svg() returns one of a fixed set of hardcoded inline SVG strings (templates/partials/icon.php), no user input reaches it. ?>
            <?php echo scp_module_icon_svg('orders'); ?>
            <span><?php esc_html_e('Siparişler', 'seviye-storefront'); ?></span>
        </a>
        <a href="<?php echo esc_url(home_url('/profilim')); ?>">
            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- scp_module_icon_svg() returns one of a fixed set of hardcoded inline SVG strings (templates/partials/icon.php), no user input reaches it. ?>
            <?php echo scp_module_icon_svg('profile'); ?>
            <span><?php esc_html_e('Profil', 'seviye-storefront'); ?></span>
        </a>
    </nav>
<?php endif; ?>
<div class="scp-layout">
    <?php scp_render_sidebar(); ?>
    <main id="scp-main-content" class="scp-site-main">
