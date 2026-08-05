<?php

/**
 * Toplu Duyuru - its own page (/admin/duyuru, /sube/duyuru, see
 * inc/zones.php's scp_menu_pages()) - "her bir menü için ayrı bir sayfa
 * yap" (bölüm 65). Reached at all already implies scp_send_broadcast or
 * scp_send_own_branch_broadcast passed - see scp_menu_pages(). Markup/ids/
 * data-attributes moved here verbatim from templates/zone.php so
 * assets/js/broadcast-panel.js keeps working unchanged.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Toplu Duyuru', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-broadcast-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('Toplu Duyuru', 'seviye-storefront'); ?></h2>
        </div>

        <p class="scp-status" data-scp-broadcast-status></p>

        <form class="scp-form" data-scp-broadcast-form>
            <label data-scp-broadcast-branch-field hidden>
                <span><?php esc_html_e('Alıcı Şube', 'seviye-storefront'); ?></span>
                <select name="branch_id"></select>
            </label>
            <label>
                <span><?php esc_html_e('Başlık', 'seviye-storefront'); ?></span>
                <input type="text" name="subject" required>
            </label>
            <label>
                <span><?php esc_html_e('Mesaj', 'seviye-storefront'); ?></span>
                <textarea name="body" rows="5" required></textarea>
            </label>
            <fieldset class="scp-form__row">
                <label class="scp-checkbox">
                    <input type="checkbox" name="channel_email" checked>
                    <span><?php esc_html_e('E-posta', 'seviye-storefront'); ?></span>
                </label>
                <label class="scp-checkbox">
                    <input type="checkbox" name="channel_panel" checked>
                    <span><?php esc_html_e('Panel Bildirimi', 'seviye-storefront'); ?></span>
                </label>
                <label class="scp-checkbox">
                    <input type="checkbox" name="channel_sms">
                    <span><?php esc_html_e('SMS', 'seviye-storefront'); ?></span>
                </label>
            </fieldset>
            <label>
                <span><?php esc_html_e('Zamanla (opsiyonel - boş bırakılırsa hemen gönderilir)', 'seviye-storefront'); ?></span>
                <input type="datetime-local" name="scheduled_at">
            </label>
            <div class="scp-form__actions">
                <button type="submit" class="scp-btn"><?php esc_html_e('Gönder', 'seviye-storefront'); ?></button>
            </div>
        </form>

        <div class="scp-card scp-card--nested">
            <div class="scp-card__header">
                <h3><?php esc_html_e('Zamanlanmış Duyurular', 'seviye-storefront'); ?></h3>
            </div>
            <p class="scp-status" data-scp-broadcast-scheduled-status></p>
            <div class="scp-table-wrapper">
                <table class="scp-table" data-scp-broadcast-scheduled-table hidden>
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Başlık', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Şube', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Zamanlanma', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-scp-broadcast-scheduled-body></tbody>
                </table>
            </div>
        </div>
    </section>
</div>
