<?php

/**
 * Verilerim (KVKK) - self-service page (/admin/kvkk, /sube/kvkk, see
 * inc/zones.php's scp_menu_pages()) - "her bir menü için ayrı bir sayfa
 * yap" (bölüm 65). Reached at all requires no capability (see
 * scp_menu_pages() - self-service, same as before this split). Just a
 * shell around the SAME shared partial
 * templates/partials/privacy-requests.php also used by
 * templates/parent-dashboard.php's own /profilim page - that partial (and
 * assets/js/privacy-requests-panel.js binding to it) is untouched by this
 * round. The STAFF review queue is a separate page - see
 * templates/privacy-queue-admin.php.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Verilerim (KVKK)', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <?php include SCP_THEME_DIR . '/templates/partials/privacy-requests.php'; ?>
</div>
