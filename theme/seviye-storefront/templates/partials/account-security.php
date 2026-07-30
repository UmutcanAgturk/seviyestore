<?php

/**
 * "Hesap Güvenliği" (2FA) card - included identically from templates/zone.php
 * (/admin, /sube) and templates/parent-dashboard.php (/), since every role
 * manages its own account's 2FA the same way (see
 * Seviye\Security\Http\TwoFactorRestController - "is logged in", not a
 * capability). A single shared partial instead of duplicating this markup
 * in both templates - assets/js/account-security.js binds to it wherever
 * it appears via getElementById, so one script serves both.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<section class="scp-card" id="scp-account-security-panel">
    <div class="scp-card__header">
        <h2><?php esc_html_e('Hesap Güvenliği', 'seviye-storefront'); ?></h2>
    </div>

    <p class="scp-status" data-scp-2fa-status></p>

    <div data-scp-2fa-disabled hidden>
        <p><?php esc_html_e('İki adımlı doğrulama şu anda kapalı.', 'seviye-storefront'); ?></p>
        <button type="button" class="scp-btn" data-scp-2fa-start>
            <?php esc_html_e('Etkinleştir', 'seviye-storefront'); ?>
        </button>
    </div>

    <div data-scp-2fa-setup hidden>
        <p>
            <?php esc_html_e('Kimlik doğrulama uygulamanıza (Google Authenticator, Authy vb.) aşağıdaki anahtarı elle girin veya bağlantıyı açın:', 'seviye-storefront'); ?>
        </p>
        <p><code data-scp-2fa-secret></code></p>
        <p><a data-scp-2fa-uri href="#" rel="noopener"><?php esc_html_e('Kimlik doğrulama uygulamasında aç', 'seviye-storefront'); ?></a></p>

        <form class="scp-form scp-form--inline" data-scp-2fa-confirm-form>
            <label>
                <span><?php esc_html_e('Doğrulama Kodu', 'seviye-storefront'); ?></span>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
            </label>
            <button type="submit" class="scp-btn"><?php esc_html_e('Onayla', 'seviye-storefront'); ?></button>
        </form>
    </div>

    <div data-scp-2fa-enabled hidden>
        <p><?php esc_html_e('İki adımlı doğrulama aktif.', 'seviye-storefront'); ?></p>

        <form class="scp-form scp-form--inline" data-scp-2fa-disable-form>
            <label>
                <span><?php esc_html_e('Şifreniz', 'seviye-storefront'); ?></span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit" class="scp-btn scp-btn--danger">
                <?php esc_html_e('Devre Dışı Bırak', 'seviye-storefront'); ?>
            </button>
        </form>
    </div>
</section>
