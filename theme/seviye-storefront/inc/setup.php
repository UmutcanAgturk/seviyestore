<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', 'scp_theme_setup');

function scp_theme_setup(): void
{
    load_theme_textdomain('seviye-storefront', SCP_THEME_DIR . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('woocommerce');
}
