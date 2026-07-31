<?php

/**
 * Generic fallback template. A Veli landing on the site's root (no static
 * front page configured, so '/' falls through to this same file) is
 * redirected STRAIGHT to the WooCommerce shop - the site root is not a
 * dashboard anymore, "Öğrencilerim"/"Profilim"/"Hesap Güvenliği" moved to
 * their own /profilim page (see inc/zones.php, templates/parent-dashboard.php).
 *
 * The redirect must happen before get_header() (any output at all would
 * make wp_safe_redirect()'s header() call fail) - unlike the WooCommerce
 * page-detection reasoning below, which still applies verbatim for every
 * OTHER real WP Page reaching this same fallback file.
 *
 * The `!is_page()` guard is required: WooCommerce's cart/checkout/my-account
 * pages (and any other real WP Page - the "Sepetim" page created by
 * scp_ensure_cart_page_exists(), for instance) are genuine Pages with no
 * dedicated template of their own (no page.php in this theme), so WordPress'
 * template hierarchy falls through to this SAME file for them too. Without
 * this guard, every one of those pages was silently replaced by this
 * redirect for any user with scp_view_own_children - the cart page
 * "existed" (wc_get_page_id('cart') resolved fine, the link's URL was
 * correct) but always bounced back to the shop instead of showing its own
 * content.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (
    !is_page()
    && function_exists('wc_get_page_permalink')
    && (current_user_can('scp_view_own_children') || current_user_can('scp_manage_own_profile'))
) {
    wp_safe_redirect(wc_get_page_permalink('shop'));
    exit;
}

get_header();

?>
<div class="scp-panel">
    <?php if (have_posts()) : ?>
        <?php
        while (have_posts()) :
            the_post();
            ?>
            <article <?php post_class(); ?>>
                <h1><?php the_title(); ?></h1>
                <div class="scp-panel__content"><?php the_content(); ?></div>
            </article>
            <?php
        endwhile;
        ?>
    <?php else : ?>
        <h1><?php
            echo esc_html(sprintf(
                /* translators: %s: display name of the logged-in user */
                __('Hoş geldiniz, %s', 'seviye-storefront'),
                wp_get_current_user()->display_name
            ));
            ?></h1>
        <p><?php
            esc_html_e(
                'Mağaza içeriği, Seviye Commerce modülü kurulduğunda burada yer alacak.',
                'seviye-storefront'
            );
            ?></p>
    <?php endif; ?>
</div>
<?php
get_footer();
