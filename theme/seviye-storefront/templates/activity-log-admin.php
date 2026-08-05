<?php

/**
 * Aktivite Günlüğü - its own page (/admin/aktivite-gunlugu, see
 * inc/zones.php's scp_menu_pages()) - "her bir menü için ayrı bir sayfa
 * yap" (bölüm 65). Reached at all already implies scp_view_audit_logs AND
 * the admin zone passed - see scp_menu_pages(). Markup/ids/data-attributes
 * moved here verbatim from templates/zone.php so
 * assets/js/activity-log-panel.js keeps working unchanged.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Aktivite Günlüğü', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-activity-log-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('Aktivite Günlüğü', 'seviye-storefront'); ?></h2>
        </div>

        <p class="scp-status" data-scp-activity-log-status></p>

        <form class="scp-form scp-form--inline" data-scp-activity-log-form>
            <label>
                <span><?php esc_html_e('Kanal', 'seviye-storefront'); ?></span>
                <select name="channel">
                    <option value=""><?php esc_html_e('Tümü', 'seviye-storefront'); ?></option>
                    <option value="activity"><?php esc_html_e('Panel İşlemleri', 'seviye-storefront'); ?></option>
                    <option value="security.auth">
                        <?php esc_html_e('Giriş/Kimlik Doğrulama', 'seviye-storefront'); ?>
                    </option>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('Seviye', 'seviye-storefront'); ?></span>
                <select name="level">
                    <option value=""><?php esc_html_e('Tümü', 'seviye-storefront'); ?></option>
                    <option value="info"><?php esc_html_e('Bilgi', 'seviye-storefront'); ?></option>
                    <option value="warning"><?php esc_html_e('Uyarı', 'seviye-storefront'); ?></option>
                    <option value="error"><?php esc_html_e('Hata', 'seviye-storefront'); ?></option>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('Kullanıcı ID', 'seviye-storefront'); ?></span>
                <input type="number" min="1" name="user_id">
            </label>
            <label>
                <span><?php esc_html_e('Başlangıç', 'seviye-storefront'); ?></span>
                <input type="date" name="from">
            </label>
            <label>
                <span><?php esc_html_e('Bitiş', 'seviye-storefront'); ?></span>
                <input type="date" name="to">
            </label>
            <label>
                <span><?php esc_html_e('Ara', 'seviye-storefront'); ?></span>
                <input type="text" name="search">
            </label>

            <div class="scp-form__actions">
                <button type="submit" class="scp-btn"><?php esc_html_e('Getir', 'seviye-storefront'); ?></button>
            </div>
        </form>

        <div class="scp-table-wrapper">
            <table class="scp-table" data-scp-activity-log-table hidden>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Tarih', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Kullanıcı', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Kanal', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Seviye', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('İşlem', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('IP', 'seviye-storefront'); ?></th>
                    </tr>
                </thead>
                <tbody data-scp-activity-log-body></tbody>
            </table>
        </div>
    </section>
</div>
