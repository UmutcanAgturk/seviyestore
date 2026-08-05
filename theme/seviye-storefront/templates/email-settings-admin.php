<?php

/**
 * E-posta Ayarları (Gmail SMTP) - its own page (/admin/eposta-ayarlari,
 * see inc/zones.php's scp_menu_pages()) - "her bir menü için ayrı bir
 * sayfa yap" (bölüm 65). Reached at all already implies
 * scp_manage_notification_settings AND the admin zone passed - see
 * scp_menu_pages(). Markup/ids/data-attributes moved here verbatim from
 * templates/zone.php so assets/js/email-settings-panel.js keeps working
 * unchanged.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('E-posta Ayarları', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-email-settings-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('E-posta Ayarları (Gmail SMTP)', 'seviye-storefront'); ?></h2>
        </div>

        <p class="scp-form__hint">
            <?php esc_html_e(
                'Veliler sipariş verdiğinde otomatik gönderilen bildirim e-postaları bu Gmail hesabı üzerinden gider.',
                'seviye-storefront'
            ); ?>
            <?php esc_html_e(
                'Uygulama Şifresi, Google hesabınızın normal şifresi değildir - Google hesap ayarlarından oluşturulur.',
                'seviye-storefront'
            ); ?>
        </p>

        <p class="scp-status" data-scp-email-settings-status></p>

        <form class="scp-form" data-scp-email-settings-form>
            <label>
                <span><?php esc_html_e('Gmail Adresi', 'seviye-storefront'); ?></span>
                <input type="email" name="email" required>
            </label>
            <label>
                <span>
                    <?php esc_html_e('Uygulama Şifresi (değiştirmek istemiyorsanız boş bırakın)', 'seviye-storefront'); ?>
                </span>
                <input type="password" name="app_password" autocomplete="new-password">
            </label>
            <div class="scp-form__actions">
                <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
            </div>
        </form>
    </section>
</div>
