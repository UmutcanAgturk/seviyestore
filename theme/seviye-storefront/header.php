<?php

/**
 * Header for authenticated views only: inc/access-gate.php renders
 * templates/login.php (its own standalone document) for every logged-out
 * request, so by the time this file runs, is_user_logged_in() is true.
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
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="scp-skip-link" href="#scp-main-content">
    <?php esc_html_e('İçeriğe geç', 'seviye-storefront'); ?>
</a>
<header class="scp-site-header">
    <?php $scp_header_logo_url = scp_logo_url('thumbnail'); ?>
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
    <nav class="scp-site-header__nav">
        <button
            type="button"
            class="scp-command-trigger"
            data-scp-command-trigger
            aria-label="<?php esc_attr_e('Bul (Cmd+K)', 'seviye-storefront'); ?>"
        >
            <?php esc_html_e('Bul', 'seviye-storefront'); ?>
            <kbd aria-hidden="true">⌘K</kbd>
        </button>
        <?php if (function_exists('wc_get_page_permalink') && current_user_can('scp_view_own_children')) : ?>
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">
                <?php esc_html_e('Mağaza', 'seviye-storefront'); ?>
            </a>
            <?php $scp_cart_count = function_exists('WC') && WC()->cart ? WC()->cart->get_cart_contents_count() : 0; ?>
            <a href="<?php echo esc_url(wc_get_cart_url()); ?>">
                <?php esc_html_e('Sepetim', 'seviye-storefront'); ?>
                <?php if ($scp_cart_count > 0) : ?>
                    <span class="scp-cart-count"><?php echo esc_html((string) $scp_cart_count); ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (current_user_can('scp_view_own_children') || current_user_can('scp_manage_own_profile')) : ?>
            <a href="<?php echo esc_url(home_url('/siparislerim')); ?>">
                <?php esc_html_e('Siparişlerim', 'seviye-storefront'); ?>
            </a>
            <a href="<?php echo esc_url(home_url('/profilim')); ?>">
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
                <?php esc_html_e('Bildirimler', 'seviye-storefront'); ?>
                <span class="scp-notif-bell__badge" data-scp-notif-badge hidden></span>
            </button>
            <div class="scp-notif-bell__panel" data-scp-notif-panel hidden>
                <p class="scp-status" data-scp-notif-status></p>
                <ul class="scp-list" data-scp-notif-list></ul>
            </div>
        </div>
        <span class="scp-site-header__user"><?php echo esc_html(wp_get_current_user()->display_name); ?></span>
        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">
            <?php esc_html_e('Çıkış Yap', 'seviye-storefront'); ?>
        </a>
    </nav>
</header>
<main id="scp-main-content" class="scp-site-main">
