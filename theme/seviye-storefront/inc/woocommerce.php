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
add_action('woocommerce_before_add_to_cart_button', 'scp_render_student_picker');
add_filter('woocommerce_loop_add_to_cart_link', 'scp_replace_loop_add_to_cart_link', 10, 2);

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
