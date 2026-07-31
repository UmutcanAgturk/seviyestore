<?php

/**
 * Generic fallback template. A Veli landing on the site's root (no static
 * front page configured, so '/' falls through to this same file) sees
 * their own dashboard (children + profile) instead of an empty blog index.
 *
 * The `!is_page()` guard is required: WooCommerce's cart/checkout/my-account
 * pages (and any other real WP Page - the "Sepetim" page created by
 * scp_ensure_cart_page_exists(), for instance) are genuine Pages with no
 * dedicated template of their own (no page.php in this theme), so WordPress'
 * template hierarchy falls through to this SAME file for them too. Without
 * this guard, every one of those pages was silently replaced by the veli
 * dashboard for any user with scp_view_own_children - the cart page
 * "existed" (wc_get_page_id('cart') resolved fine, the link's URL was
 * correct) but always rendered the dashboard instead of its own content.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();

if (!is_page() && (current_user_can('scp_view_own_children') || current_user_can('scp_manage_own_profile'))) {
    include SCP_THEME_DIR . '/templates/parent-dashboard.php';
    get_footer();

    return;
}

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
