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

/**
 * "Manuel karanlık mod anahtarı" - stamps `data-theme="dark"`/`"light"`
 * on `<html>` from the visitor's stored `localStorage.scpTheme` choice,
 * synchronously, BEFORE any stylesheet paints. Printed directly in
 * header.php/templates/login.php's `<head>` - ahead of `wp_head()`, where
 * theme.css's/auth.css's `<link>` tags land - rather than left to
 * scp-ui-kit.js's own `initThemeToggle()` (a footer script, `in_footer:
 * true`), which would only run after the WHOLE page (styles included) has
 * already painted with the wrong theme for one visible frame. try/catch
 * because `localStorage` throws in some privacy-mode/embedded-iframe
 * contexts - a blocked read there should just fall through to the site's
 * own light default (no OS `prefers-color-scheme` fallback anymore, see
 * theme.css's own docblock on that), not break the page.
 */
function scp_theme_preload_script(): void
{
    ?>
    <script>
    (function () {
        try {
            var stored = localStorage.getItem('scpTheme');

            if (stored === 'dark' || stored === 'light') {
                document.documentElement.setAttribute('data-theme', stored);
            }
        } catch (e) {}
    })();
    </script>
    <?php
}

/**
 * "Kullanıcı baş harf avatarı" - header.php'nin kendi görünen adından
 * sunucu tarafında (JS gerekmeden) hesaplanan bir baş harf + renk. Ürün/
 * öğrenci listeleri gibi JS'in ZATEN render ettiği yerlerde bunun yerine
 * assets/js/scp-ui-kit.js'in AYNI isimdeki (ama ayrı bir uygulaması olan)
 * `scpAvatar()`'ı kullanılıyor - iki dilde TAM AYNI hash sonucunu
 * üretecek şekilde birebir eşleştirmeye ÇALIŞILMADI (PHP'nin UTF-8
 * byte'ları ile JS'in UTF-16 code unit'leri arasında Türkçe karakterli
 * adlarda [İ, ş, ğ, ü, ö, ç] birebir aynı hash'i üretmesi güvenilir
 * şekilde garanti edilemez) - her bağlamın kendi içinde tutarlı (aynı ad
 * her zaman aynı rengi alır) olması yeterli görüldü, iki bağlam arasında
 * BİREBİR aynı renk bir gereksinim değil.
 */
function scp_avatar_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

    if ($parts === [] || $parts === false) {
        return '';
    }

    $initials = mb_substr($parts[0], 0, 1);

    if (count($parts) > 1) {
        $initials .= mb_substr($parts[count($parts) - 1], 0, 1);
    }

    return mb_strtoupper($initials);
}

function scp_avatar_color(string $name): string
{
    $palette = ['#14326b', '#0f9d63', '#b45309', '#0f5c96', '#7e3af2', '#c0271e', '#0b7a4c'];
    $hash = 0;

    foreach (str_split($name) as $char) {
        $hash = ($hash * 31 + ord($char)) % count($palette);
    }

    return $palette[$hash];
}

function scp_render_avatar(string $name): void
{
    printf(
        '<span class="scp-avatar" style="background-color:%s">%s</span>',
        esc_attr(scp_avatar_color($name)),
        esc_html(scp_avatar_initials($name))
    );
}
