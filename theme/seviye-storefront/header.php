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
<header class="scp-site-header">
    <div class="scp-site-header__brand">
        <a class="scp-site-header__brand-link" href="<?php echo esc_url(scp_current_user_landing_path()); ?>">
            <span class="scp-site-header__mark" aria-hidden="true">S</span>
            <?php bloginfo('name'); ?>
        </a>
    </div>
    <nav class="scp-site-header__nav">
        <?php if (function_exists('wc_get_page_permalink') && current_user_can('scp_view_own_children')) : ?>
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">
                <?php esc_html_e('Mağaza', 'seviye-storefront'); ?>
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
<main class="scp-site-main">
