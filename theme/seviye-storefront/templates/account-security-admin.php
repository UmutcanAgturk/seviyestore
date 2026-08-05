<?php

/**
 * Hesap Güvenliği - its own page (/admin/hesap-guvenligi,
 * /sube/hesap-guvenligi, see inc/zones.php's scp_menu_pages()) - "her bir
 * menü için ayrı bir sayfa yap" (bölüm 65). Reached at all requires no
 * capability (see scp_menu_pages() - it manages the CURRENT user's own
 * account, not a permission-scoped resource, same as before this split).
 * Just a shell around the SAME shared partial
 * templates/partials/account-security.php also used by
 * templates/parent-dashboard.php's own /profilim page - that partial (and
 * assets/js/account-security.js binding to it) is untouched by this round.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Hesap Güvenliği', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <?php include SCP_THEME_DIR . '/templates/partials/account-security.php'; ?>
</div>
