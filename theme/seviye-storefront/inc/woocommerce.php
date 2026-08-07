<?php

/**
 * WooCommerce presentation glue: every product on this platform requires a
 * student selection (Seviye Commerce's cart validation rejects an add-to-cart
 * without one - see plugin/seviye-commerce/src/Http/WooCommerceCartHooks.php),
 * so this file (a) renders a student picker on the single product page and
 * (b) replaces the archive/shop page's instant "add to cart" link (which has
 * no form to carry a student selection) with a plain link to the product
 * page, where the picker lives.
 *
 * `add_theme_support('woocommerce')` (inc/setup.php) alone was NOT enough -
 * WooCommerce's default templates still expected the theme's OWN content
 * wrapper markup (none existed here, so shop pages rendered edge-to-edge
 * with no `.scp-panel`-style container) and still called
 * `woocommerce_get_sidebar()` (this platform has no sidebar/widget concept
 * at all - no `register_sidebar()` anywhere in the theme - so that call
 * rendered whatever default WordPress widgets happened to be assigned to
 * the site's stale/inactive sidebar slot, as a bare unstyled list). Both are
 * addressed below via WooCommerce's own documented theme-integration hooks,
 * not template overrides. `woocommerce_enqueue_styles` is also filtered
 * empty so WooCommerce's bundled default stylesheet never fights
 * assets/css/woocommerce.css on cascade order - this theme's CSS is the
 * only styling source for WooCommerce markup from here on.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WooCommerce')) {
    return;
}

add_action('admin_init', 'scp_ensure_shop_page_exists');
add_action('admin_init', 'scp_ensure_cart_page_exists');
add_action('admin_init', 'scp_ensure_store_not_coming_soon');
add_action('woocommerce_before_add_to_cart_button', 'scp_render_student_picker');
add_action('woocommerce_before_add_to_cart_button', 'scp_render_size_guide_trigger', 5);
add_filter('woocommerce_loop_add_to_cart_link', 'scp_replace_loop_add_to_cart_link', 10, 2);

// "Boş arama sonucunda akıllı öneriler" - öncelik 20, WooCommerce'in
// KENDİ "sonuç bulunamadı" paragrafından (loop/no-products-found.php,
// öncelik 10) SONRA basılır, onu değiştirmez, yalnızca altına ekler.
add_action('woocommerce_no_products_found', 'scp_render_no_products_suggestions', 20);

// "Sepet/ödeme adım göstergesi" - sepet, ödeme ve "sipariş alındı"
// (thank you) sayfalarının HER ÜÇÜNDE de basılır - scp_render_checkout_steps()
// hangi sayfada olduğuna göre kendi aktif adımını hesaplıyor.
add_action('woocommerce_before_cart', 'scp_render_checkout_steps');
add_action('woocommerce_before_checkout_form', 'scp_render_checkout_steps');
add_action('woocommerce_before_thankyou', 'scp_render_checkout_steps');

// "Mağaza Vitrini" - öncelik 1, scp_render_category_banner()'dan (4) ÖNCE -
// yalnızca mağaza ana sayfasında (is_shop(), kategori arşivlerinde DEĞİL -
// onların zaten kendi banner'ı var) gösterilen hero + öne çıkan ürünler
// bölümü.
add_action('woocommerce_before_shop_loop', 'scp_render_shop_showcase', 1);

// "Yeni eklenen ürünler rafı" - öncelik 2, hero'dan (1) hemen sonra,
// kategori banner'ından (4) önce.
add_action('woocommerce_before_shop_loop', 'scp_render_new_arrivals', 2);

// "Kategori banner'ları" - öncelik 4, scp_render_shop_filters()'ten (5)
// ÖNCE - bir kategori arşivinin görseli/açıklaması varsa filtre çubuğunun
// üstünde geniş bir banner olarak görünür.
add_action('woocommerce_before_shop_loop', 'scp_render_category_banner', 4);

// "Mağaza tarafında arama ve kategori filtreleme" - reuses WooCommerce's own
// native search form (get_product_search_form()) and category taxonomy
// listing (wp_list_categories()) rather than a custom REST/JS filter UI, so
// existing WC query-string handling (?s=..., the product_cat archive URL)
// keeps working with zero extra plumbing. See scp_render_shop_filters().
add_action('woocommerce_before_shop_loop', 'scp_render_shop_filters', 5);

// "Düşük stok rozeti" - priority 15 so it prints AFTER WooCommerce's own
// default thumbnail output on this same hook (priority 10, unhooked
// nowhere in this theme).
add_action('woocommerce_before_shop_loop_item_title', 'scp_render_low_stock_badge', 15);

// "Ürün rozetleri: Yeni" - top-RIGHT corner (öncelik farketmiyor - WC'nin
// kendi "İndirimde" rozeti ile bu tema hiç dokunulmamış "Son N adet!"
// rozeti ikisi de sol üstte; onları saran/yeniden konumlandıran bir
// değişiklik YAPILMADI, çünkü WC'nin bu iki varsayılan hook'unun (thumbnail
// + sale flash) TAM önceliğini bu sandbox'ta canlı bir WooCommerce
// kurulumu olmadan doğrulayamıyoruz - onları bir sarmalayıcıya almaya
// çalışmak, o önceliği yanlış tahmin edersek ürün görselini bile
// sarmalayıp mağaza ızgarasını bozabilirdi. Ayrı bir köşe kullanmak bu
// riski tamamen ortadan kaldırıyor.
add_action('woocommerce_before_shop_loop_item_title', 'scp_render_new_badge', 12);

// "Ürün hızlı önizleme" - priority 15, WC'nin kendi sepete-ekleme
// linkinin (priority 10, scp_replace_loop_add_to_cart_link() ile
// "Öğrenci Seç"e çevrilmiş) HEMEN ardından.
add_action('woocommerce_after_shop_loop_item', 'scp_render_quick_view_trigger', 15);

// "Son görüntülenen ürünler" - işaretçi ürün özetinin İÇİNDE (WC'nin ürün
// verisi orada `global $product` olarak zaten hazır), şerit ise özetten
// SONRA - WC'nin kendi ilgili ürünler bölümünün (priority 20) hemen
// ardından, priority 25.
add_action('woocommerce_single_product_summary', 'scp_render_recently_viewed_marker', 60);
add_action('woocommerce_after_single_product_summary', 'scp_render_recently_viewed_strip', 25);

// "Stok gelince haber ver" - priority 31, WC'nin kendi sepete-ekleme
// alanının (priority 30 - stok yoksa burada "Stokta yok" mesajı basılır)
// HEMEN ardından.
add_action('woocommerce_single_product_summary', 'scp_render_stock_subscription', 31);

add_filter('woocommerce_enqueue_styles', '__return_empty_array');
remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);

add_action('woocommerce_before_main_content', 'scp_open_woocommerce_wrapper', 10);
add_action('woocommerce_after_main_content', 'scp_close_woocommerce_wrapper', 10);

function scp_open_woocommerce_wrapper(): void
{
    echo '<div class="scp-panel scp-shop-panel">';
}

function scp_close_woocommerce_wrapper(): void
{
    echo '</div>';
}

/**
 * WooCommerce's own installer (WC_Install::create_pages(), fired from
 * register_activation_hook()) normally creates the "Mağaza" page and sets
 * the woocommerce_shop_page_id option on first activation - but, same as
 * every migration in this codebase (see docs/ARCHITECTURE.md, "Üçüncü
 * kural"), that hook only fires on the inactive→active transition, never
 * on a plugin zip being replaced/reinstalled. If the shop page was ever
 * deleted, or WooCommerce was reinstalled without its own fresh-activation
 * page-creation step running, wc_get_page_id('shop') is left pointing at
 * nothing and the storefront has no shop page to render at all - no
 * amount of CSS fixes the page not existing. Checked/self-healed on every
 * wp-admin load, mirroring MigrationRunner::run()'s reasoning exactly.
 */
function scp_ensure_shop_page_exists(): void
{
    if (!function_exists('wc_get_page_id') || !function_exists('wc_create_page')) {
        return;
    }

    $shopPageId = wc_get_page_id('shop');

    if ($shopPageId > 0 && get_post_status($shopPageId) === 'publish') {
        return;
    }

    wc_create_page(
        esc_sql(_x('shop', 'Page slug', 'woocommerce')),
        'woocommerce_shop_page_id',
        _x('Mağaza', 'Page title', 'seviye-storefront')
    );
}

/**
 * Same self-heal as {@see scp_ensure_shop_page_exists()}, for the "Sepetim"
 * page - unlike the shop page (a special product-archive page type WC
 * recognizes automatically), the cart page needs actual page content: the
 * `[woocommerce_cart]` shortcode, which WC_Install::create_pages() would
 * normally seed on first activation. The shortcode (rather than the newer
 * Cart block) is used deliberately for maximum compatibility across
 * WooCommerce versions.
 */
function scp_ensure_cart_page_exists(): void
{
    if (!function_exists('wc_get_page_id') || !function_exists('wc_create_page')) {
        return;
    }

    $cartPageId = wc_get_page_id('cart');

    if ($cartPageId > 0 && get_post_status($cartPageId) === 'publish') {
        return;
    }

    wc_create_page(
        esc_sql(_x('sepetim', 'Page slug', 'seviye-storefront')),
        'woocommerce_cart_page_id',
        _x('Sepetim', 'Page title', 'seviye-storefront'),
        '[woocommerce_cart]'
    );
}

/**
 * WooCommerce (since the "coming soon" onboarding checklist landed) ships
 * fresh installs with `woocommerce_coming_soon` set to `yes`, which replaces
 * every storefront page - shop, single product, cart - with a bare "Store is
 * launching soon" placeholder for every visitor, Veli included, regardless
 * of that Veli's own branch/grade-level product visibility. Unlike the shop
 * page above, WooCommerce's own installer does turn this off once someone
 * manually clicks "Launch your store" in wp-admin, but nothing ever prompts
 * for that click on THIS platform (there is no public storefront-launch
 * moment - a school's Veli accounts are provisioned long before anyone
 * would think to check WooCommerce Settings > General for an unrelated
 * onboarding toggle) - so it can silently sit at its "yes" default
 * indefinitely, making every product invisible to every Veli and reading
 * exactly like a broken grade-level/branch visibility filter. Self-healed on
 * every wp-admin load, the same "never blocks on a one-time human step"
 * reasoning as scp_ensure_shop_page_exists().
 */
function scp_ensure_store_not_coming_soon(): void
{
    if (get_option('woocommerce_coming_soon') === 'yes') {
        update_option('woocommerce_coming_soon', 'no');
    }
}

function scp_render_student_picker(): void
{
    if (!current_user_can('scp_view_own_children')) {
        return;
    }
    ?>
    <div class="scp-form scp-student-picker" data-scp-student-picker>
        <label>
            <span><?php esc_html_e('Öğrenci', 'seviye-storefront'); ?></span>
            <select name="scp_student_id" required>
                <option value=""><?php esc_html_e('Yükleniyor…', 'seviye-storefront'); ?></option>
            </select>
        </label>
    </div>
    <?php
}

/**
 * "Mini sepet" - header.php'deki "Sepetim" linki artık doğrudan Sepetim
 * sayfasına gitmek yerine (data-scp-mini-cart-trigger, bkz. header.php ve
 * assets/js/scp-ui-kit.js'in initMiniCart()'ı) bu kayan paneli açıyor.
 * İçerik WooCommerce'in kendi `woocommerce_mini_cart()` şablon
 * fonksiyonuyla (widget/shortcode'un da kullandığı, `cart/mini-cart.php`)
 * render ediliyor - özel bir sepet REST uç noktası KURULMADI, çünkü sepete
 * ekleme bu platformda zaten tam sayfa yeniden yüklemeyle oluyor (tekil
 * ürün sayfasındaki standart WC formu, mağaza listesindeki linkler
 * `scp_replace_loop_add_to_cart_link()` ile "Öğrenci Seç" linkine
 * çevrilmiş durumda, ayrıca ajax değiller) - yani header her sayfa
 * yüklemesinde ZATEN güncel sepeti render ediyor, ayrı bir fragment-refresh
 * mekanizmasına gerek yok. Yalnızca veli/`scp_view_own_children` sahibi
 * kullanıcılar için (Sepetim linkiyle aynı görünürlük kuralı).
 */
function scp_render_mini_cart_drawer(): void
{
    if (!current_user_can('scp_view_own_children')) {
        return;
    }
    ?>
    <div class="scp-mini-cart" data-scp-mini-cart hidden>
        <div class="scp-mini-cart__backdrop" data-scp-mini-cart-close></div>
        <div
            class="scp-mini-cart__panel"
            role="dialog"
            aria-modal="true"
            aria-label="<?php esc_attr_e('Sepetim', 'seviye-storefront'); ?>"
        >
            <div class="scp-mini-cart__header">
                <h2><?php esc_html_e('Sepetim', 'seviye-storefront'); ?></h2>
                <button
                    type="button"
                    class="scp-mini-cart__close"
                    data-scp-mini-cart-close
                    aria-label="<?php esc_attr_e('Kapat', 'seviye-storefront'); ?>"
                >&times;</button>
            </div>
            <div class="scp-mini-cart__content">
                <?php woocommerce_mini_cart(); ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * "Mağaza Vitrini" - yalnızca mağaza ANA sayfasında (`is_shop()`) gösterilen
 * iki bağımsız isteğe bağlı parça: (1) HQ'nun `scp_commerce_shop_showcase`
 * filtre köprüsü üzerinden düzenlediği (bkz. CommerceModule::boot() ve
 * templates/shop-showcase-admin.php) başlık/alt başlık/arka plan görseli
 * hero'su - hepsi boşsa hiçbir şey basılmaz; (2) WooCommerce'in KENDİ
 * "Öne Çıkan" ürün işaretlemesi (`wc_get_featured_product_ids()` - ürün
 * düzenleme ekranındaki standart yıldız simgesi, bu platform tarafından
 * icat edilmiş yeni bir alan DEĞİL) doluysa, WooCommerce'in KENDİ
 * `[featured_products]` shortcode'u kullanılarak basılan bir ürün şeridi -
 * özel bir sorgu/carousel JS'i yazılmadı, shortcode'un çıktısı
 * woocommerce.css'te yatay kaydırılabilir (scroll-snap) bir şerit haline
 * CSS ile dönüştürülüyor.
 *
 * Kategori arşivlerinde (`is_product_taxonomy()`) YOK - onların zaten
 * kendi görsel/açıklaması var (bkz. scp_render_category_banner()), iki
 * banner üst üste kafa karıştırıcı olurdu.
 */
function scp_render_shop_showcase(): void
{
    if (!is_shop()) {
        return;
    }

    $showcase = apply_filters('scp_commerce_shop_showcase', null);
    $heading = is_array($showcase) ? (string) ($showcase['heading'] ?? '') : '';
    $subheading = is_array($showcase) ? (string) ($showcase['subheading'] ?? '') : '';
    $imageUrl = is_array($showcase) ? ($showcase['image_url'] ?? null) : null;

    if ($heading !== '' || $subheading !== '' || $imageUrl) {
        $style = $imageUrl ? sprintf('background-image:url(%s)', esc_url((string) $imageUrl)) : '';

        ?>
        <div class="scp-shop-showcase" style="<?php echo esc_attr($style); ?>">
            <div class="scp-shop-showcase__content">
                <?php if ($heading !== '') : ?>
                    <h2 class="scp-shop-showcase__heading"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($subheading !== '') : ?>
                    <p class="scp-shop-showcase__subheading"><?php echo esc_html($subheading); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    if (!empty(wc_get_featured_product_ids())) {
        ?>
        <div class="scp-shop-showcase__carousel">
            <?php echo do_shortcode('[featured_products limit="8" columns="4"]'); ?>
        </div>
        <?php
    }
}

/**
 * "Yeni eklenen ürünler rafı" - yalnızca mağaza ana sayfasında, öne çıkan
 * ürünler şeridinden hemen sonra (öncelik 2). scp_render_new_badge()'in
 * KENDİ "14 gün" eşiğiyle aynı sezgisel kullanılıyor - en son yayınlanan
 * ürün bile 14 günden eskiyse (mağazaya yakın zamanda hiçbir şey
 * eklenmemiş) bölüm tamamen basılmıyor, boş/alakasız bir "yeni ürünler"
 * başlığı görünmesin diye. WooCommerce'in KENDİ genel `[products]`
 * shortcode'u (`orderby="date"`) kullanılıyor - özel bir sorgu yazılmadı,
 * Mağaza Vitrini'nin `[featured_products]` şeridiyle AYNI carousel CSS
 * kalıbını (.scp-shop-showcase__carousel) paylaşıyor.
 */
function scp_render_new_arrivals(): void
{
    if (!is_shop()) {
        return;
    }

    $newestProducts = wc_get_products([
        'limit' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'status' => 'publish',
        'return' => 'ids',
    ]);

    if (empty($newestProducts)) {
        return;
    }

    $publishedTimestamp = get_post_time('U', true, (int) $newestProducts[0]);

    if ($publishedTimestamp === false || (time() - (int) $publishedTimestamp) / DAY_IN_SECONDS > 14) {
        return;
    }

    ?>
    <div class="scp-shop-showcase__section">
        <h2 class="scp-shop-showcase__section-title"><?php esc_html_e('Yeni Ürünler', 'seviye-storefront'); ?></h2>
        <div class="scp-shop-showcase__carousel">
            <?php echo do_shortcode('[products limit="8" columns="4" orderby="date" order="DESC"]'); ?>
        </div>
    </div>
    <?php
}

/**
 * "Boş arama sonucunda akıllı öneriler" - `woocommerce_no_products_found`
 * hook'una BAĞLI (öncelik 20, WC'nin kendi "sonuç bulunamadı" mesajından
 * sonra), yalnızca gerçek bir arama sorgusu varsa (`?s=...`) basılır -
 * içeriği boş bir kategori arşivinde (arama YOK, gerçekten sıfır ürün
 * var) sessizce hiçbir şey basmıyor, o zaman öneri sunmak anlamsız
 * olurdu. Kategoriler `scp_render_shop_filters()`'in AYNI
 * `.scp-shop-filters__categories` markup kalıbını yeniden kullanıyor;
 * önerilen ürünler için özel bir "en çok satan" sorgusu icat edilmedi -
 * `wc_get_products()`'ın kendi `orderby => 'popularity'` seçeneği
 * (WooCommerce'in `total_sales` meta'sına dayanıyor) kullanıldı.
 */
function scp_render_no_products_suggestions(): void
{
    $searchTerm = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

    if ($searchTerm === '') {
        return;
    }

    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 6]);
    $suggestedProducts = wc_get_products([
        'limit' => 4,
        'orderby' => 'popularity',
        'order' => 'DESC',
        'status' => 'publish',
        'return' => 'objects',
    ]);

    if (empty($categories) && empty($suggestedProducts)) {
        return;
    }

    ?>
    <div class="scp-no-products-suggestions">
        <?php if (!empty($categories) && !is_wp_error($categories)) : ?>
            <nav class="scp-shop-filters__categories" aria-label="<?php esc_attr_e('Kategoriler', 'seviye-storefront'); ?>">
                <span class="scp-shop-filters__categories-label">
                    <?php esc_html_e('Kategorilere göz atın', 'seviye-storefront'); ?>
                </span>
                <ul>
                    <?php foreach ($categories as $category) : ?>
                        <li>
                            <a href="<?php echo esc_url((string) get_term_link($category)); ?>">
                                <?php echo esc_html($category->name); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>

        <?php if (!empty($suggestedProducts)) : ?>
            <h3 class="scp-shop-showcase__section-title">
                <?php esc_html_e('Bunlar ilginizi çekebilir', 'seviye-storefront'); ?>
            </h3>
            <ul class="products columns-4">
                <?php foreach ($suggestedProducts as $suggestedProduct) : ?>
                    <?php if (!$suggestedProduct instanceof WC_Product) : ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <li class="product">
                        <a href="<?php echo esc_url((string) get_permalink($suggestedProduct->get_id())); ?>">
                            <?php echo wp_kses_post($suggestedProduct->get_image('woocommerce_thumbnail')); ?>
                            <h2 class="woocommerce-loop-product__title">
                                <?php echo esc_html($suggestedProduct->get_name()); ?>
                            </h2>
                            <span class="price"><?php echo wp_kses_post($suggestedProduct->get_price_html()); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * "Sepet/ödeme adım göstergesi" - `orders-panel.js`'in
 * `renderFulfillmentTimeline()`'ıyla AYNI `.scp-order-timeline` görsel
 * dilini (nokta + etiket + done/active/upcoming durumları, panel.css'te
 * zaten stillendi) sipariş sonrasından ÖNCEye, ödeme akışına taşıyor.
 * WooCommerce'in `is_checkout()`'u thank-you (order-received) sayfasında
 * da true döner - `is_order_received_page()` bu ikisini ayırıyor, yoksa
 * onay sayfasında "Ödeme" adımı yanlışlıkla aktif görünürdü.
 */
function scp_render_checkout_steps(): void
{
    if (!is_cart() && !is_checkout()) {
        return;
    }

    $currentIndex = is_order_received_page() ? 2 : (is_checkout() ? 1 : 0);

    $steps = [
        __('Sepet', 'seviye-storefront'),
        __('Ödeme', 'seviye-storefront'),
        __('Onay', 'seviye-storefront'),
    ];

    ?>
    <ol class="scp-order-timeline scp-checkout-steps">
        <?php foreach ($steps as $index => $label) : ?>
            <?php $state = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'active' : 'upcoming'); ?>
            <li class="scp-order-timeline__step scp-order-timeline__step--<?php echo esc_attr($state); ?>">
                <span class="scp-order-timeline__dot"></span>
                <span class="scp-order-timeline__label"><?php echo esc_html($label); ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
    <?php
}

/**
 * "Kategori banner'ları" - kategori arşivinin kendi görseli varsa
 * (WooCommerce'in "Ürün kategorileri" ekranındaki "Görsel" alanı -
 * `thumbnail_id` term meta'sı, `content-product_cat.php` şablonunun
 * kategori ızgarasında zaten kullandığı AYNI alan, burada YENİDEN
 * KULLANILIYOR, yeni bir alan icat edilmedi) geniş bir arka plan banner'ı
 * olarak, açıklaması varsa da üzerinde gösteriliyor. Kategori ADI burada
 * TEKRAR basılmıyor - WooCommerce'in kendi `archive-product.php` şablonu
 * (dokunulmadı) zaten `woocommerce_page_title()` ile ayrı bir
 * `.woocommerce-products-header__title` başlığı basıyor; bu fonksiyon
 * yalnızca görsel/açıklamayı ekliyor, o başlık woocommerce.css'te bu
 * banner'la görsel olarak bütünleşecek şekilde ayrıca stillendi.
 */
function scp_render_category_banner(): void
{
    if (!is_product_taxonomy()) {
        return;
    }

    $term = get_queried_object();

    if (!$term instanceof WP_Term) {
        return;
    }

    $thumbnailId = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
    $imageUrl = $thumbnailId > 0 ? wp_get_attachment_image_url($thumbnailId, 'large') : false;
    $description = term_description($term->term_id, 'product_cat');

    if (!$imageUrl && $description === '') {
        return;
    }

    $style = $imageUrl ? sprintf('background-image:url(%s)', esc_url($imageUrl)) : '';

    ?>
    <div class="scp-category-banner" style="<?php echo esc_attr($style); ?>">
        <?php if ($description !== '') : ?>
            <div class="scp-category-banner__description"><?php echo wp_kses_post($description); ?></div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * "Ürün hızlı önizleme" - mağaza ızgarasındaki her karta bir "Hızlı Bakış"
 * düğmesi + o ürünün detaylarını taşıyan gizli bir `<template>` ekliyor.
 * Ayrı bir REST çağrısı/AJAX KURULMADI: bu platformda "ürünleri görüntüle"
 * yetkisi (`scp_view_products`/`scp_manage_products`) yalnızca personelde
 * var, mağazayı gezen veli'de YOK - `ProductsRestController`'ın
 * `canViewProducts()` izin denetimi bu yüzden buradan çağrılamaz. Bunun
 * yerine detaylar (görsel, fiyat, kısa açıklama) sayfa zaten render
 * edilirken sunucu tarafında basılıyor - assets/js/scp-ui-kit.js'in
 * initQuickView()'ı yalnızca bu ZATEN VAR olan `<template>` içeriğini bir
 * modale klonluyor, ekstra bir ağ isteği yok. Sepete ekleme burada
 * YAPILMIYOR - "Ürün Sayfasına Git" linki, öğrenci seçiminin yapıldığı
 * tekil ürün sayfasına yönlendiriyor (bkz. scp_render_student_picker()) -
 * sepet/harcama limiti/fiyat kuralı doğrulamalarını burada yeniden
 * uygulamaktan kaçınmak için kasıtlı bir kapsam sınırı.
 */
function scp_render_quick_view_trigger(): void
{
    global $product;

    if (!$product instanceof WC_Product) {
        return;
    }

    $productId = $product->get_id();
    $imageId = $product->get_image_id();
    $imageUrl = $imageId ? wp_get_attachment_image_url($imageId, 'medium') : wc_placeholder_img_src('medium');
    $shortDescription = $product->get_short_description();

    ?>
    <button
        type="button"
        class="scp-quick-view-trigger"
        data-scp-quick-view-trigger
        data-scp-quick-view-target="scp-quick-view-<?php echo esc_attr((string) $productId); ?>"
    >
        <?php esc_html_e('Hızlı Bakış', 'seviye-storefront'); ?>
    </button>
    <template id="scp-quick-view-<?php echo esc_attr((string) $productId); ?>">
        <div class="scp-quick-view__header">
            <button
                type="button"
                class="scp-quick-view__close"
                data-scp-quick-view-close
                aria-label="<?php esc_attr_e('Kapat', 'seviye-storefront'); ?>"
            >&times;</button>
        </div>
        <div class="scp-quick-view__image">
            <img src="<?php echo esc_url((string) $imageUrl); ?>" alt="">
        </div>
        <div class="scp-quick-view__body">
            <h2><?php echo esc_html($product->get_name()); ?></h2>
            <p class="scp-quick-view__price"><?php echo wp_kses_post($product->get_price_html()); ?></p>
            <?php if ($shortDescription !== '') : ?>
                <div class="scp-quick-view__description">
                    <?php echo wp_kses_post(wpautop($shortDescription)); ?>
                </div>
            <?php endif; ?>
            <a class="scp-btn" href="<?php echo esc_url((string) get_permalink($productId)); ?>">
                <?php esc_html_e('Ürün Sayfasına Git', 'seviye-storefront'); ?>
            </a>
        </div>
    </template>
    <?php
}

/**
 * "Beden Rehberi" - tekil ürün sayfasında, öğrenci seçicisinden (öncelik 10)
 * ÖNCE (öncelik 5) bir "Beden Rehberi" tetikleyici düğmesi + Hızlı
 * Bakış'la AYNI modal markup kalıbını (`.scp-quick-view__*` sınıfları,
 * `data-scp-quick-view-*` öznitelikleri) kullanan gizli bir `<template>`
 * basar - assets/js/scp-ui-kit.js'in initQuickView()'ı zaten bu genel
 * delege edilmiş tıklama dinleyicisiyle çalıştığı için burada YENİ BİR JS
 * GEREKMİYOR. İçerik `scp_commerce_size_guide_rows` filtre köprüsü
 * üzerinden okunuyor (bkz. CommerceModule::boot()) - Depo'nun
 * scp_depo_supplier_id_for_user'ı ve ProductOwnershipBridge'in
 * scp_commerce_product_owner_branch_id'siyle AYNI gevşek filtre köprüsü
 * ilkesi, tema hiçbir DI container'a bağımlı olmadan plugin verisini
 * okuyabiliyor.
 *
 * Tablo mağaza geneli tek bir içerik olduğundan (ürün başına değil), yalnızca
 * o ürünün gerçekten bir beden varyantı varsa gösteriliyor - `pa_beden`
 * global özniteliği taşımayan bir üründe (ör. defter, kalem) beden rehberi
 * anlamsız olurdu (bkz. ProductsRestController'ın kendi "beden/renk"
 * varyant docblock'u).
 */
function scp_render_size_guide_trigger(): void
{
    global $product;

    if (!$product instanceof WC_Product) {
        return;
    }

    if ($product->get_attribute('pa_beden') === '') {
        return;
    }

    $rows = apply_filters('scp_commerce_size_guide_rows', []);

    if (!is_array($rows) || $rows === []) {
        return;
    }

    ?>
    <button
        type="button"
        class="scp-quick-view-trigger scp-size-guide-trigger"
        data-scp-quick-view-trigger
        data-scp-quick-view-target="scp-size-guide"
    >
        <?php esc_html_e('Beden Rehberi', 'seviye-storefront'); ?>
    </button>
    <template id="scp-size-guide">
        <div class="scp-quick-view__header">
            <button
                type="button"
                class="scp-quick-view__close"
                data-scp-quick-view-close
                aria-label="<?php esc_attr_e('Kapat', 'seviye-storefront'); ?>"
            >&times;</button>
        </div>
        <div class="scp-quick-view__body">
            <h2><?php esc_html_e('Beden Rehberi', 'seviye-storefront'); ?></h2>
            <div class="scp-table-wrapper">
                <table class="scp-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Beden', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Yaş', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Boy (cm)', 'seviye-storefront'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><?php echo esc_html((string) ($row['label'] ?? '')); ?></td>
                                <td><?php echo esc_html(scp_format_size_guide_range(
                                    $row['age_min'] ?? null,
                                    $row['age_max'] ?? null
                                )); ?></td>
                                <td><?php echo esc_html(scp_format_size_guide_range(
                                    $row['height_min_cm'] ?? null,
                                    $row['height_max_cm'] ?? null
                                )); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </template>
    <?php
}

/**
 * "4-5 yaş", "4 yaş ve üzeri", "5 yaş ve altı" ya da her ikisi de boşsa "-".
 */
function scp_format_size_guide_range(mixed $min, mixed $max): string
{
    $min = $min === null || $min === '' ? null : (string) $min;
    $max = $max === null || $max === '' ? null : (string) $max;

    if ($min !== null && $max !== null) {
        return $min === $max ? $min : $min . '-' . $max;
    }

    if ($min !== null) {
        /* translators: %s: minimum age or height value */
        return sprintf(__('%s ve üzeri', 'seviye-storefront'), $min);
    }

    if ($max !== null) {
        /* translators: %s: maximum age or height value */
        return sprintf(__('%s ve altı', 'seviye-storefront'), $max);
    }

    return '-';
}

/**
 * Renders above the product loop on the shop page and every product
 * category archive - not on the single product page, where a search/filter
 * bar has no product grid below it to act on.
 *
 * "Mağaza sayfasını daha iyi yap kullanıcı dostu olsun" - a category chip
 * had no way back to the unfiltered shop other than the browser's back
 * button, so a "Tümü" chip is prepended whenever a category is active
 * (`wp_list_categories()`'s own `current_category` only highlights the
 * ACTIVE category, it never adds an "all" option itself).
 */
function scp_render_shop_filters(): void
{
    if (!is_shop() && !is_product_taxonomy()) {
        return;
    }

    $minPrice = isset($_GET['scp_min_price']) ? sanitize_text_field(wp_unslash($_GET['scp_min_price'])) : '';
    $maxPrice = isset($_GET['scp_max_price']) ? sanitize_text_field(wp_unslash($_GET['scp_max_price'])) : '';
    $inStock = isset($_GET['scp_in_stock']) && $_GET['scp_in_stock'] === '1';
    $hasActiveFilter = $minPrice !== '' || $maxPrice !== '' || $inStock;

    ?>
    <div class="scp-shop-filters">
        <?php get_product_search_form(); ?>
        <nav class="scp-shop-filters__categories" aria-label="<?php esc_attr_e('Kategoriler', 'seviye-storefront'); ?>">
            <span class="scp-shop-filters__categories-label">
                <?php esc_html_e('Kategoriler', 'seviye-storefront'); ?>
            </span>
            <ul>
                <?php if (is_product_taxonomy()) : ?>
                    <li>
                        <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>">
                            <?php esc_html_e('Tümü', 'seviye-storefront'); ?>
                        </a>
                    </li>
                <?php endif; ?>
                <?php
                wp_list_categories([
                    'taxonomy' => 'product_cat',
                    'title_li' => '',
                    'hide_empty' => true,
                    'show_count' => true,
                    'current_category' => is_product_taxonomy() ? get_queried_object_id() : 0,
                ]);
                ?>
            </ul>
        </nav>
        <?php if (get_terms(['taxonomy' => 'product_tag', 'hide_empty' => true, 'fields' => 'count']) > 0) : ?>
            <nav
                class="scp-shop-filters__categories"
                aria-label="<?php esc_attr_e('Etiketler', 'seviye-storefront'); ?>"
            >
                <span class="scp-shop-filters__categories-label">
                    <?php esc_html_e('Etiketler', 'seviye-storefront'); ?>
                </span>
                <ul>
                    <?php if (is_tax('product_tag')) : ?>
                        <li>
                            <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>">
                                <?php esc_html_e('Tümü', 'seviye-storefront'); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php
                    wp_list_categories([
                        'taxonomy' => 'product_tag',
                        'title_li' => '',
                        'hide_empty' => true,
                        'show_count' => true,
                        'number' => 20,
                        'current_category' => is_tax('product_tag') ? get_queried_object_id() : 0,
                    ]);
                    ?>
                </ul>
            </nav>
        <?php endif; ?>
        <form class="scp-shop-filters__advanced" method="get">
            <?php if (get_search_query() !== '') : ?>
                <input type="hidden" name="s" value="<?php echo esc_attr(get_search_query()); ?>">
                <input type="hidden" name="post_type" value="product">
            <?php endif; ?>
            <label>
                <span><?php esc_html_e('Min. Fiyat', 'seviye-storefront'); ?></span>
                <input
                    type="number"
                    name="scp_min_price"
                    min="0"
                    step="0.01"
                    value="<?php echo esc_attr($minPrice); ?>"
                >
            </label>
            <label>
                <span><?php esc_html_e('Maks. Fiyat', 'seviye-storefront'); ?></span>
                <input
                    type="number"
                    name="scp_max_price"
                    min="0"
                    step="0.01"
                    value="<?php echo esc_attr($maxPrice); ?>"
                >
            </label>
            <label class="scp-checkbox">
                <input type="checkbox" name="scp_in_stock" value="1" <?php checked($inStock); ?>>
                <span><?php esc_html_e('Yalnızca stokta olanlar', 'seviye-storefront'); ?></span>
            </label>
            <button type="submit" class="scp-btn scp-btn--small">
                <?php esc_html_e('Filtrele', 'seviye-storefront'); ?>
            </button>
            <?php if ($hasActiveFilter) : ?>
                <a
                    class="scp-shop-filters__clear"
                    href="<?php echo esc_url(remove_query_arg(['scp_min_price', 'scp_max_price', 'scp_in_stock'])); ?>"
                >
                    <?php esc_html_e('Temizle', 'seviye-storefront'); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>
    <?php
}

/**
 * "Gelişmiş mağaza filtreleri" - scp_render_shop_filters()'in fiyat
 * aralığı/stok durumu formunu (`scp_min_price`/`scp_max_price`/
 * `scp_in_stock` GET parametreleri) mağazanın ana ürün sorgusuna
 * uyguluyor. WooCommerce'in kendi `_price`/`_stock_status` postmeta
 * alanları kullanılıyor (WC'nin fiyat aralığı widget'ının/katalog
 * filtrelerinin de kullandığı AYNI, dokümante edilmiş alanlar) - yeni bir
 * alan icat edilmedi. Yalnızca ana ürün arşivi sorgusunu etkiler
 * (`is_main_query()` + `is_post_type_archive('product')`/`is_tax('product_cat')`) -
 * başka bir yerdeki (ör. "İlgili Ürünler") ikincil bir sorguyu etkilemez.
 */
add_action('pre_get_posts', 'scp_apply_shop_filters');

function scp_apply_shop_filters(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    if (!$query->is_post_type_archive('product') && !$query->is_tax('product_cat')) {
        return;
    }

    $minPrice = isset($_GET['scp_min_price']) ? sanitize_text_field(wp_unslash($_GET['scp_min_price'])) : '';
    $maxPrice = isset($_GET['scp_max_price']) ? sanitize_text_field(wp_unslash($_GET['scp_max_price'])) : '';
    $inStock = isset($_GET['scp_in_stock']) && $_GET['scp_in_stock'] === '1';

    if ($minPrice === '' && $maxPrice === '' && !$inStock) {
        return;
    }

    $metaQuery = (array) $query->get('meta_query');

    if ($minPrice !== '' || $maxPrice !== '') {
        $metaQuery[] = [
            'key' => '_price',
            'value' => [
                $minPrice !== '' ? (float) $minPrice : 0,
                $maxPrice !== '' ? (float) $maxPrice : 999999999,
            ],
            'type' => 'DECIMAL',
            'compare' => 'BETWEEN',
        ];
    }

    if ($inStock) {
        $metaQuery[] = [
            'key' => '_stock_status',
            'value' => 'instock',
            'compare' => '=',
        ];
    }

    $query->set('meta_query', $metaQuery);
}

/**
 * "Ürün kartlarında 'Son 3 adet!' gibi aciliyet göstergesi" - only when the
 * product manages its own stock AND its remaining quantity is at/under
 * WooCommerce's own low-stock threshold (`wc_get_low_stock_amount()` -
 * the product's own low_stock_amount if set, falling back to the store-wide
 * "Düşük stok eşiği" setting, exactly the same threshold this platform's
 * admin "Depo/Ürünler" low-stock warnings already use - see
 * plugin/seviye-commerce/src/Http/LowStockNotificationHooks.php). Hidden
 * entirely at 0 (out of stock is WooCommerce's own separate "Stokta Yok"
 * badge, not this one).
 */
function scp_render_low_stock_badge(): void
{
    global $product;

    if (!$product instanceof WC_Product || !$product->get_manage_stock()) {
        return;
    }

    $quantity = $product->get_stock_quantity();

    if ($quantity === null || $quantity <= 0) {
        return;
    }

    $threshold = (int) wc_get_low_stock_amount($product);

    if ($threshold <= 0 || $quantity > $threshold) {
        return;
    }

    printf(
        '<span class="scp-low-stock-badge">%s</span>',
        esc_html(
            sprintf(
                /* translators: %d: remaining stock quantity */
                __('Son %d adet!', 'seviye-storefront'),
                $quantity
            )
        )
    );
}

/**
 * "Ürün rozetleri: Yeni" - ürün yayınlanma tarihinden bu yana geçen süre
 * 14 günden azsa gösteriliyor. WooCommerce'in "yeni ürün" kavramı yok -
 * bu, WordPress'in kendi post_date'ini (her ürün zaten bir WP post) 14
 * günlük sabit bir eşikle karşılaştıran basit bir sezgisel.
 */
function scp_render_new_badge(): void
{
    global $product;

    if (!$product instanceof WC_Product) {
        return;
    }

    $publishedTimestamp = get_post_time('U', true, $product->get_id());

    if ($publishedTimestamp === false) {
        return;
    }

    $daysSincePublished = (time() - (int) $publishedTimestamp) / DAY_IN_SECONDS;

    if ($daysSincePublished > 14) {
        return;
    }

    printf(
        '<span class="scp-new-badge">%s</span>',
        esc_html__('Yeni', 'seviye-storefront')
    );
}

/**
 * @param \WC_Product $product
 */
function scp_replace_loop_add_to_cart_link(string $html, $product): string
{
    if (!current_user_can('scp_view_own_children')) {
        return $html;
    }

    return sprintf(
        '<a href="%1$s" class="button">%2$s</a>',
        esc_url(get_permalink($product->get_id())),
        esc_html__('Öğrenci Seç', 'seviye-storefront')
    );
}

/**
 * "Son görüntülenen ürünler" - tamamen istemci tarafı: yeni bir REST
 * endpoint'i veya sunucu tarafı oturum/kullanıcı verisi YOK. Bu gizli
 * işaretçi, o anki ürün sayfasının kendi verisini (id, ad, url, görsel,
 * fiyat) data-* öznitelikleri olarak basıyor; assets/js/scp-ui-kit.js'in
 * initRecentlyViewed()'i bunu `localStorage`'a yazıyor VE aşağıdaki
 * scp_render_recently_viewed_strip()'in boş kabını dolduruyor - ikisi de
 * AYNI JS fonksiyonu, sırasıyla "kaydet" ve "listele" adımları.
 */
function scp_render_recently_viewed_marker(): void
{
    global $product;

    if (!$product instanceof WC_Product) {
        return;
    }

    $imageId = $product->get_image_id();
    $imageUrl = $imageId ? wp_get_attachment_image_url($imageId, 'thumbnail') : wc_placeholder_img_src('thumbnail');

    printf(
        '<div id="scp-recently-viewed-marker" hidden data-id="%1$s" data-name="%2$s"'
            . ' data-url="%3$s" data-image="%4$s" data-price="%5$s"></div>',
        esc_attr((string) $product->get_id()),
        esc_attr($product->get_name()),
        esc_url(get_permalink($product->get_id())),
        esc_url((string) $imageUrl),
        esc_attr(wp_strip_all_tags($product->get_price_html()))
    );
}

/**
 * Boş bir kap - initRecentlyViewed() `localStorage.scpRecentlyViewed`'dan
 * (o anki ürün HARİÇ) en fazla 6 kartı buraya render ediyor. Hiç kayıt
 * yoksa (ilk ziyaret) veya listede o anki üründen başka ürün yoksa kap
 * boş kalır - initResponsiveTables() gibi diğer generic initXxx()
 * fonksiyonlarının izlediği "veri yoksa sessizce hiçbir şey render etme"
 * deseniyle aynı.
 */
function scp_render_recently_viewed_strip(): void
{
    global $product;

    if (!$product instanceof WC_Product) {
        return;
    }

    echo '<div class="scp-recently-viewed" data-scp-recently-viewed></div>';
}

/**
 * "Stok gelince haber ver" - yalnızca STOKTA OLMAYAN bir üründe, yalnızca
 * veli için (personelin kendi test amaçlı görüntülemesinde anlamı yok)
 * boş bir kap basılıyor - gerçek abonelik durumu (aboneyim/değilim) ve
 * abone ol/aboneliği iptal et düğmesi assets/js/scp-ui-kit.js'in
 * initStockSubscription()'ı tarafından
 * seviye/v1/commerce/stock-subscriptions/{id}'ye bir GET ile dolduruluyor
 * - PHP tarafında "şu anda abone mi" bilgisini sorgulayıp basmak yerine
 * (StockSubscriptionRepositoryInterface'i buraya bağımlılık olarak
 * eklemek gerekirdi), tek kaynak REST endpoint'i JS'in zaten çağıracağı
 * için PHP tarafı kasıtlı olarak sade tutuldu.
 */
function scp_render_stock_subscription(): void
{
    global $product;

    if (!$product instanceof WC_Product || $product->is_in_stock()) {
        return;
    }

    if (!current_user_can('scp_view_own_children')) {
        return;
    }

    printf(
        '<div class="scp-stock-subscription" data-scp-stock-subscription data-product-id="%s"></div>',
        esc_attr((string) $product->get_id())
    );
}
