<?php

/**
 * Generic fallback template. WooCommerce (Seviye Commerce, once built)
 * supplies its own archive/product templates via the template hierarchy;
 * this file only needs to handle the case where there is genuinely no
 * WooCommerce/content yet, or a plain Page/Post is being viewed.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
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
