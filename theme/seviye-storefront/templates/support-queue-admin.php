<?php

/**
 * Destek Talepleri (staff queue) - its own page (/admin/destek-talepleri,
 * /sube/destek-talepleri, see inc/zones.php's scp_menu_pages()) - "her bir
 * menü için ayrı bir sayfa yap" (bölüm 65). Reached at all already implies
 * scp_manage_support_tickets passed - see scp_menu_pages(). This is the
 * STAFF queue only - a Veli's own self-service ticket card
 * (templates/partials/support-tickets.php) is unrelated and unaffected,
 * see that file's own docblock. Markup/ids/data-attributes moved here
 * verbatim from templates/zone.php so assets/js/support-tickets-panel.js
 * keeps working unchanged.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Destek Talepleri', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-support-tickets-queue-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('Destek Talepleri', 'seviye-storefront'); ?></h2>
        </div>

        <p class="scp-status" data-scp-support-queue-status></p>

        <div class="scp-table-wrapper">
            <table class="scp-table" data-scp-support-queue-table hidden>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Konu', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Şube', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Güncellenme', 'seviye-storefront'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-scp-support-queue-body></tbody>
            </table>
        </div>

        <div class="scp-card scp-card--nested" data-scp-support-queue-detail hidden>
            <div class="scp-card__header">
                <h3 data-scp-support-queue-detail-title></h3>
                <div>
                    <button type="button" class="scp-btn scp-btn--small" data-scp-support-queue-close-ticket>
                        <?php esc_html_e('Kapat', 'seviye-storefront'); ?>
                    </button>
                    <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-support-queue-detail-close>
                        <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                    </button>
                </div>
            </div>

            <ul class="scp-list" data-scp-support-queue-messages></ul>

            <form class="scp-form" data-scp-support-queue-reply-form>
                <textarea name="message" rows="3" required maxlength="1000" data-scp-char-counter></textarea>
                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn scp-btn--small">
                        <?php esc_html_e('Yanıtla', 'seviye-storefront'); ?>
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>
