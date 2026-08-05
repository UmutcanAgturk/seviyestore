<?php

/**
 * KVKK Talepleri (staff review queue) - its own page (/admin/kvkk-talepleri,
 * see inc/zones.php's scp_menu_pages()) - "her bir menü için ayrı bir
 * sayfa yap" (bölüm 65). Reached at all already implies
 * scp_manage_privacy_requests AND the admin zone passed - see
 * scp_menu_pages(). Markup/ids/data-attributes moved here verbatim from
 * templates/zone.php so assets/js/privacy-requests-panel.js (which also
 * binds the self-service card on templates/privacy-requests-admin.php)
 * keeps working unchanged.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('KVKK Talepleri', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-privacy-requests-queue-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('KVKK Talepleri', 'seviye-storefront'); ?></h2>
        </div>

        <p class="scp-status" data-scp-privacy-queue-status></p>

        <div class="scp-table-wrapper">
            <table class="scp-table" data-scp-privacy-queue-table hidden>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Kullanıcı', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('E-posta', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Talep Tarihi', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Not', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('İşlem', 'seviye-storefront'); ?></th>
                    </tr>
                </thead>
                <tbody data-scp-privacy-queue-body></tbody>
            </table>
        </div>
    </section>
</div>
