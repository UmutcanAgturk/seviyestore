<?php

/**
 * Shared landing view for the /admin and /sube zones. Included directly by
 * inc/zones.php with $scp_zone_label already set in scope.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<div class="scp-panel">
    <h1><?php
        echo esc_html(sprintf(
            /* translators: %s: zone label, e.g. "Genel Merkez" or "Şube" */
            __('%s Paneli', 'seviye-storefront'),
            $scp_zone_label
        ));
        ?></h1>
    <p><?php
        echo esc_html(sprintf(
            /* translators: %s: display name of the logged-in user */
            __('Hoş geldiniz, %s.', 'seviye-storefront'),
            wp_get_current_user()->display_name
        ));
        ?></p>
    <p><?php
        esc_html_e(
            'Bu panelin içeriği, ilgili modüller (Şube, Öğrenci, Sipariş, Finans...) geliştirildikçe burada yer alacak.',
            'seviye-storefront'
        );
        ?></p>
</div>
<?php
get_footer();
