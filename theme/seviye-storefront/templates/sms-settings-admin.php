<?php

/**
 * SMS Ayarları (NetGSM) - its own page (/admin/sms-ayarlari, see
 * inc/zones.php's scp_menu_pages()) - "her bir menü için ayrı bir sayfa
 * yap" (bölüm 65). Reached at all already implies
 * scp_manage_notification_settings AND the admin zone passed - see
 * scp_menu_pages(). Markup/ids/data-attributes moved here verbatim from
 * templates/zone.php so assets/js/notifications-settings-panel.js keeps
 * working unchanged.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('SMS Ayarları', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-sms-settings-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('SMS Ayarları (NetGSM)', 'seviye-storefront'); ?></h2>
        </div>

        <p class="scp-status" data-scp-sms-settings-status></p>

        <form class="scp-form" data-scp-sms-settings-form>
            <div class="scp-form__row">
                <label>
                    <span><?php esc_html_e('Kullanıcı Kodu', 'seviye-storefront'); ?></span>
                    <input type="text" name="usercode" required>
                </label>
                <label>
                    <span><?php esc_html_e('Başlık (Msgheader)', 'seviye-storefront'); ?></span>
                    <input type="text" name="msgheader" required>
                </label>
            </div>
            <label>
                <span>
                    <?php esc_html_e('Şifre (değiştirmek istemiyorsanız boş bırakın)', 'seviye-storefront'); ?>
                </span>
                <input type="password" name="password" autocomplete="new-password">
            </label>
            <div class="scp-form__actions">
                <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
            </div>
        </form>
    </section>
</div>
