<?php

/**
 * "Markalı 404 sayfası" - WordPress' own template hierarchy only reaches
 * this file for a genuinely unmatched URL: inc/access-gate.php's
 * template_redirect (priority 5) already sent a logged-out visitor to
 * templates/login.php, and inc/zones.php's scp_render_zone_template()
 * (priority 10) already handles every /admin, /sube sub-path it recognizes
 * - including a garbage sub-path, which it silently falls through to that
 * zone's own root rather than 404ing (see that function's own docblock).
 * So by the time this file runs, the request is a logged-in user hitting
 * something outside both of those - a stale/mistyped WooCommerce product
 * link, an old bookmark, a dead search-engine result, etc. get_header()
 * is safe to call unconditionally for the same reason index.php's does:
 * is_user_logged_in() is already guaranteed true here.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();

?>
<div class="scp-panel">
    <div class="scp-card scp-not-found">
        <p class="scp-not-found__code" aria-hidden="true">404</p>
        <h1><?php esc_html_e('Bu sayfa bulunamadı', 'seviye-storefront'); ?></h1>
        <p class="scp-not-found__hint">
            <?php esc_html_e(
                'Aradığınız sayfa taşınmış, silinmiş olabilir ya da hiç var olmamış olabilir.',
                'seviye-storefront'
            ); ?>
        </p>
        <a class="scp-btn" href="<?php echo esc_url(scp_current_user_landing_path()); ?>">
            <?php esc_html_e('Panele Dön', 'seviye-storefront'); ?>
        </a>
    </div>
</div>
<?php
get_footer();
