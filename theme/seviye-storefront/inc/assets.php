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
    wp_enqueue_style('scp-panel', SCP_THEME_URL . '/assets/css/panel.css', ['scp-theme'], SCP_THEME_VERSION);

    scp_enqueue_panel_assets();
}

/**
 * REST calls made from an already-authenticated screen carry a WordPress
 * cookie, so - unlike the pre-login seviye/v1/auth/* endpoints in
 * inc/assets.php's auth counterpart - WordPress' own cookie-nonce check
 * (rest_cookie_check_errors(), wired in by core on every request) rejects
 * them without a valid X-WP-Nonce header. Every script enqueued here gets
 * one via scpPanel.nonce.
 */
function scp_enqueue_panel_assets(): void
{
    $zone = (string) get_query_var('scp_zone');

    $localized = [
        'restUrl' => esc_url_raw(rest_url('seviye/v1/')),
        'nonce' => wp_create_nonce('wp_rest'),
    ];

    $text = [
        'loadError' => __('Veriler yüklenirken bir hata oluştu.', 'seviye-storefront'),
        'saveError' => __('Kaydedilirken bir hata oluştu.', 'seviye-storefront'),
        'saved' => __('Kaydedildi.', 'seviye-storefront'),
        'edit' => __('Düzenle', 'seviye-storefront'),
        'remove' => __('Kaldır', 'seviye-storefront'),
        'parentLinked' => __('Veli bağlandı.', 'seviye-storefront'),
        'profileSaved' => __('Profiliniz güncellendi.', 'seviye-storefront'),
        'noChildren' => __('Sisteme bağlı bir öğrenci bulunamadı.', 'seviye-storefront'),
        'scopeGeneral' => __('Genel', 'seviye-storefront'),
        'scopeBranch' => __('Şube', 'seviye-storefront'),
        'scopeStudent' => __('Öğrenci', 'seviye-storefront'),
        'branchIdLabel' => __('Şube ID', 'seviye-storefront'),
        'studentIdLabel' => __('Öğrenci ID', 'seviye-storefront'),
        'confirmDeletePriceRule' => __('Bu fiyat kuralını silmek istediğinize emin misiniz?', 'seviye-storefront'),
        'priceRuleDeleted' => __('Fiyat kuralı silindi.', 'seviye-storefront'),
        'statusActive' => __('Aktif', 'seviye-storefront'),
        'statusInactive' => __('Pasif', 'seviye-storefront'),
        'methodBankTransfer' => __('Banka Havalesi', 'seviye-storefront'),
        'methodCash' => __('Nakit', 'seviye-storefront'),
        'methodOther' => __('Diğer', 'seviye-storefront'),
        'settlementRecorded' => __('Tahsilat kaydedildi.', 'seviye-storefront'),
        'noSettlements' => __('Henüz tahsilat kaydı yok.', 'seviye-storefront'),
        'twoFactorEnabled' => __('İki adımlı doğrulama etkinleştirildi.', 'seviye-storefront'),
        'twoFactorDisabled' => __('İki adımlı doğrulama devre dışı bırakıldı.', 'seviye-storefront'),
        'twoFactorInvalidCode' => __('Kod hatalı. Lütfen tekrar deneyin.', 'seviye-storefront'),
        'twoFactorWrongPassword' => __('Şifre hatalı.', 'seviye-storefront'),
    ];

    if (in_array($zone, ['admin', 'sube'], true) && current_user_can('scp_manage_students')) {
        $handle = 'scp-students-panel';
        wp_enqueue_script($handle, SCP_THEME_URL . '/assets/js/students-panel.js', [], SCP_THEME_VERSION, true);
        wp_localize_script($handle, 'scpPanel', array_merge($localized, [
            'canManageAllBranches' => current_user_can('scp_manage_branches'),
        ]));
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    if ($zone === 'admin' && current_user_can('scp_manage_branches')) {
        $handle = 'scp-branches-panel';
        wp_enqueue_script($handle, SCP_THEME_URL . '/assets/js/branches-panel.js', [], SCP_THEME_VERSION, true);
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    if (in_array($zone, ['admin', 'sube'], true) && current_user_can('scp_manage_pricing')) {
        $handle = 'scp-pricing-panel';
        wp_enqueue_script($handle, SCP_THEME_URL . '/assets/js/pricing-panel.js', [], SCP_THEME_VERSION, true);
        wp_localize_script($handle, 'scpPanel', array_merge($localized, [
            'canManageAllBranches' => current_user_can('scp_manage_branches'),
        ]));
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    $isParentZone = current_user_can('scp_view_own_children') || current_user_can('scp_manage_own_profile');

    if ($zone === '' && $isParentZone) {
        $handle = 'scp-parent-dashboard';
        wp_enqueue_script($handle, SCP_THEME_URL . '/assets/js/parent-dashboard.js', [], SCP_THEME_VERSION, true);
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    $canViewHakedis = current_user_can('scp_view_hakedis') || current_user_can('scp_view_own_hakedis');

    if (in_array($zone, ['admin', 'sube'], true) && $canViewHakedis) {
        $handle = 'scp-hakedis-panel';
        wp_enqueue_script($handle, SCP_THEME_URL . '/assets/js/hakedis-panel.js', [], SCP_THEME_VERSION, true);
        wp_localize_script($handle, 'scpPanel', array_merge($localized, [
            'canViewAllBranches' => current_user_can('scp_view_hakedis'),
            'canRecordSettlement' => current_user_can('scp_record_hakedis_settlement'),
        ]));
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    // Every logged-in user manages their own account's 2FA, in every zone -
    // unlike every other script above, this one is never capability-gated.
    $handle = 'scp-account-security';
    wp_enqueue_script($handle, SCP_THEME_URL . '/assets/js/account-security.js', [], SCP_THEME_VERSION, true);
    wp_localize_script($handle, 'scpPanel', $localized);
    wp_localize_script($handle, 'scpPanelText', $text);

    if ($zone === 'admin' && current_user_can('scp_manage_security_settings')) {
        $handle = 'scp-ip-allowlist-panel';
        wp_enqueue_script($handle, SCP_THEME_URL . '/assets/js/ip-allowlist-panel.js', [], SCP_THEME_VERSION, true);
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    if (function_exists('is_product') && is_product() && current_user_can('scp_view_own_children')) {
        $handle = 'scp-product-student-picker';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/product-student-picker.js',
            [],
            SCP_THEME_VERSION,
            true
        );
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }
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
        'invalidCode' => __('Kod hatalı.', 'seviye-storefront'),
        'twoFactorSessionExpired' => __('Doğrulama süresi doldu, lütfen tekrar giriş yapın.', 'seviye-storefront'),
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
