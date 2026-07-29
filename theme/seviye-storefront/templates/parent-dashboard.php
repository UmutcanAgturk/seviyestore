<?php

/**
 * Veli home view: "Öğrencilerim" (own children, read-only here - Students
 * remains the single owner of that data) and "Profilim" (Seviye Parents).
 * Included directly by index.php when the current user holds either of
 * the capabilities checked below.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php
        echo esc_html(sprintf(
            /* translators: %s: display name of the logged-in user */
            __('Hoş geldiniz, %s', 'seviye-storefront'),
            wp_get_current_user()->display_name
        ));
        ?></h1>

    <?php if (current_user_can('scp_view_own_children')) : ?>
        <section class="scp-card" id="scp-parent-children">
            <div class="scp-card__header">
                <h2><?php esc_html_e('Öğrencilerim', 'seviye-storefront'); ?></h2>
            </div>
            <p class="scp-status" data-scp-children-status></p>
            <ul class="scp-list" data-scp-children-list></ul>
        </section>
    <?php endif; ?>

    <?php if (current_user_can('scp_manage_own_profile')) : ?>
        <section class="scp-card" id="scp-parent-profile">
            <div class="scp-card__header">
                <h2><?php esc_html_e('Profilim', 'seviye-storefront'); ?></h2>
            </div>
            <p class="scp-status" data-scp-profile-status></p>

            <form class="scp-form" data-scp-profile-form>
                <label>
                    <span><?php esc_html_e('Telefon', 'seviye-storefront'); ?></span>
                    <input type="tel" name="phone" autocomplete="tel">
                </label>
                <label>
                    <span><?php esc_html_e('Bildirim Tercihi', 'seviye-storefront'); ?></span>
                    <select name="notification_preference">
                        <option value="email"><?php esc_html_e('E-posta', 'seviye-storefront'); ?></option>
                        <option value="sms"><?php esc_html_e('SMS', 'seviye-storefront'); ?></option>
                        <option value="both"><?php esc_html_e('İkisi de', 'seviye-storefront'); ?></option>
                    </select>
                </label>
                <label class="scp-checkbox">
                    <input type="checkbox" name="kvkk_consent">
                    <span><?php esc_html_e('KVKK Aydınlatma Metni\'ni okudum, onaylıyorum.', 'seviye-storefront'); ?></span>
                </label>
                <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
            </form>
        </section>
    <?php endif; ?>
</div>
