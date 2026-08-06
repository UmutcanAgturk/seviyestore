<?php

/**
 * WhatsApp Ayarları (WhatsApp Business Cloud API) - its own page
 * (/admin/whatsapp-ayarlari, see inc/zones.php's scp_menu_pages()) - "her
 * bir menü için ayrı bir sayfa yap" (bölüm 65), mirrors
 * templates/sms-settings-admin.php's exact structure for the same
 * "third-party notification gateway credentials" shape. Reached at all
 * already implies scp_manage_notification_settings AND the admin zone
 * passed - see scp_menu_pages(). See
 * plugin/seviye-notifications/src/Channel/WhatsAppChannel.php's own
 * docblock for the honest "24-hour window / approved template" constraint
 * this settings page can't configure around - only credentials live here.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('WhatsApp Ayarları', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-whatsapp-settings-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('WhatsApp Ayarları (Business Cloud API)', 'seviye-storefront'); ?></h2>
        </div>

        <p class="scp-form__hint">
            <?php esc_html_e(
                // phpcs:ignore Generic.Files.LineLength.TooLong -- must stay one literal for i18n extraction
                'Meta\'nın WhatsApp Business Cloud API bilgileri. Serbest metin bildirimleri yalnızca alıcı işletmeyle son 24 saat içinde yazışmışsa iletilir; bunun dışında Meta tarafından önceden onaylanmış bir şablon mesajı gerekir - bu, Meta İşletme Hesabı\'nda ayrıca yapılandırılması gereken bir adımdır.',
                'seviye-storefront'
            ); ?>
        </p>

        <p class="scp-status" data-scp-whatsapp-settings-status></p>

        <form class="scp-form" data-scp-whatsapp-settings-form>
            <label>
                <span><?php esc_html_e('Telefon Numarası Kimliği (Phone Number ID)', 'seviye-storefront'); ?></span>
                <input type="text" name="phone_number_id" required>
            </label>
            <label>
                <span>
                    <?php esc_html_e(
                        'Erişim Anahtarı (değiştirmek istemiyorsanız boş bırakın)',
                        'seviye-storefront'
                    ); ?>
                </span>
                <input type="password" name="access_token" autocomplete="new-password">
            </label>
            <div class="scp-form__actions">
                <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
            </div>
        </form>
    </section>
</div>
