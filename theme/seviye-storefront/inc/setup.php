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

    // "Ürün galerisi yakınlaştırma" - WooCommerce'in KENDİ, bundled
    // PhotoSwipe tabanlı zoom/lightbox'ı bu üç theme-support bildirimi
    // OLMADAN hiç enqueue edilmiyor (bkz. wc_current_theme_supports_gallery_zoom()/
    // ..._lightbox()/..._slider() - WooCommerce'in tema entegrasyonunda
    // opt-in olarak tasarlanmış). Kendi zoom/lightbox'ımızı YAZMADIK -
    // WC'nin zaten test edilmiş, kendi kendine yeten (CDN'e çıkmayan)
    // kütüphanesini açtık.
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}
