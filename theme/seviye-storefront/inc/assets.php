<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'scp_enqueue_assets');

function scp_enqueue_assets(): void
{
    if (!is_user_logged_in()) {
        scp_enqueue_auth_assets();

        return;
    }

    wp_enqueue_style('scp-theme', SCP_THEME_URL . '/assets/css/theme.css', [], SCP_THEME_VERSION);
}

function scp_enqueue_auth_assets(): void
{
    wp_enqueue_style('scp-auth', SCP_THEME_URL . '/assets/css/auth.css', [], SCP_THEME_VERSION);
    wp_enqueue_script('scp-auth', SCP_THEME_URL . '/assets/js/auth.js', [], SCP_THEME_VERSION, true);

    wp_localize_script('scp-auth', 'scpAuth', [
        'restUrl' => esc_url_raw(rest_url('seviye/v1/auth/')),
        'token' => scp_requested_password_token(),
    ]);

    wp_localize_script('scp-auth', 'scpAuthText', [
        'invalidCredentials' => __('T.C. Kimlik No veya şifre hatalı.', 'seviye-storefront'),
        'throttled' => __('Çok fazla deneme yaptınız. Lütfen birkaç dakika sonra tekrar deneyin.', 'seviye-storefront'),
        'networkError' => __('Bağlantı hatası. Lütfen tekrar deneyin.', 'seviye-storefront'),
        'genericError' => __('Bir hata oluştu. Lütfen tekrar deneyin.', 'seviye-storefront'),
        'weakPassword' => __('Şifre en az 8 karakter olmalıdır.', 'seviye-storefront'),
        'invalidToken' => __('Bağlantının süresi dolmuş veya geçersiz. Lütfen tekrar talep edin.', 'seviye-storefront'),
        'passwordMismatch' => __('Şifreler eşleşmiyor.', 'seviye-storefront'),
        'resetLinkSent' => __('T.C. Kimlik No sistemde kayıtlıysa, bağlantı gönderildi.', 'seviye-storefront'),
        'passwordSet' => __('Şifreniz oluşturuldu. Giriş ekranına yönlendiriliyorsunuz...', 'seviye-storefront'),
    ]);
}

function scp_requested_password_token(): string
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route param, not a state-changing form submission; the token itself is verified (and single-use consumed) by PasswordTokenService, not trusted here.
    if (!isset($_GET['scp_token'])) {
        return '';
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
    return sanitize_text_field(wp_unslash($_GET['scp_token']));
}
