<?php

/**
 * Standalone login screen, deliberately outside the normal WordPress
 * template hierarchy: inc/access-gate.php includes this file directly and
 * exits for every logged-out front-end request, so no product, page or
 * post is ever reachable without a session - see docs/ROADMAP.md.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$scp_initial_view = scp_requested_password_token() !== '' ? 'set-password' : 'login';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html(get_bloginfo('name')); ?> &mdash; <?php esc_html_e('Giriş Yap', 'seviye-storefront'); ?></title>
    <?php scp_theme_preload_script(); ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class('scp-auth-body'); ?>>
<?php wp_body_open(); ?>
<main class="scp-auth-screen">
    <div class="scp-auth-card" id="scp-auth">
        <?php $scp_login_logo_url = scp_logo_url('medium'); ?>
        <?php if ($scp_login_logo_url !== null) : ?>
            <img class="scp-auth-logo" src="<?php echo esc_url($scp_login_logo_url); ?>" alt="">
        <?php else : ?>
            <div class="scp-auth-mark" aria-hidden="true">
                <?php echo esc_html(mb_substr(get_bloginfo('name'), 0, 1)); ?>
            </div>
        <?php endif; ?>
        <h1 class="scp-auth-title"><?php echo esc_html(get_bloginfo('name')); ?></h1>

        <p class="scp-auth-status" role="status" aria-live="polite" data-scp-status></p>

        <form
            class="scp-auth-form"
            data-scp-view="login"
            <?php echo 'login' === $scp_initial_view ? '' : 'hidden'; ?>
        >
            <label class="scp-auth-field">
                <span><?php esc_html_e('T.C. Kimlik No', 'seviye-storefront'); ?></span>
                <input type="text" name="tc_no" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" autocomplete="username" required>
            </label>

            <label class="scp-auth-field">
                <span><?php esc_html_e('Şifre', 'seviye-storefront'); ?></span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>

            <label class="scp-auth-checkbox">
                <input type="checkbox" name="remember">
                <span><?php esc_html_e('Beni hatırla', 'seviye-storefront'); ?></span>
            </label>

            <button type="submit" class="scp-auth-submit"><?php esc_html_e('Giriş Yap', 'seviye-storefront'); ?></button>

            <div class="scp-auth-links">
                <a href="#" data-scp-switch="forgot-password"><?php esc_html_e('Şifremi Unuttum', 'seviye-storefront'); ?></a>
            </div>
        </form>

        <form class="scp-auth-form" data-scp-view="2fa" hidden>
            <p class="scp-auth-hint"><?php esc_html_e('Kimlik doğrulama uygulamanızdaki 6 haneli kodu girin.', 'seviye-storefront'); ?></p>

            <input type="hidden" name="pending_token">

            <label class="scp-auth-field">
                <span><?php esc_html_e('Doğrulama Kodu', 'seviye-storefront'); ?></span>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
            </label>

            <button type="submit" class="scp-auth-submit"><?php esc_html_e('Doğrula', 'seviye-storefront'); ?></button>

            <div class="scp-auth-links">
                <a href="#" data-scp-switch="login"><?php esc_html_e('Girişe dön', 'seviye-storefront'); ?></a>
            </div>
        </form>

        <form class="scp-auth-form" data-scp-view="forgot-password" hidden>
            <p class="scp-auth-hint"><?php esc_html_e('T.C. Kimlik No\'nuzu girin, şifre sıfırlama bağlantısını gönderelim.', 'seviye-storefront'); ?></p>

            <label class="scp-auth-field">
                <span><?php esc_html_e('T.C. Kimlik No', 'seviye-storefront'); ?></span>
                <input type="text" name="tc_no" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" required>
            </label>

            <button type="submit" class="scp-auth-submit"><?php esc_html_e('Sıfırlama Bağlantısı Gönder', 'seviye-storefront'); ?></button>

            <div class="scp-auth-links">
                <a href="#" data-scp-switch="login"><?php esc_html_e('Girişe dön', 'seviye-storefront'); ?></a>
            </div>
        </form>

        <form
            class="scp-auth-form"
            data-scp-view="set-password"
            <?php echo 'set-password' === $scp_initial_view ? '' : 'hidden'; ?>
        >
            <p class="scp-auth-hint"><?php esc_html_e('Lütfen yeni şifrenizi belirleyin.', 'seviye-storefront'); ?></p>

            <label class="scp-auth-field">
                <span><?php esc_html_e('Yeni Şifre', 'seviye-storefront'); ?></span>
                <input type="password" name="password" autocomplete="new-password" minlength="8" required>
            </label>

            <div class="scp-password-strength" data-scp-password-strength hidden>
                <div class="scp-password-strength__track">
                    <div class="scp-password-strength__fill" data-scp-password-strength-fill></div>
                </div>
                <span class="scp-password-strength__label" data-scp-password-strength-label></span>
            </div>

            <label class="scp-auth-field">
                <span><?php esc_html_e('Yeni Şifre (Tekrar)', 'seviye-storefront'); ?></span>
                <input type="password" name="password_confirm" autocomplete="new-password" minlength="8" required>
            </label>

            <button type="submit" class="scp-auth-submit"><?php esc_html_e('Şifreyi Kaydet', 'seviye-storefront'); ?></button>
        </form>

        <?php
        /**
         * "Kurum tarafından oluşturulan şifreyle ilk giriş yapılacak, giriş
         * yapıldıktan hemen sonra ilk şifresini oluştursun" - shown instead
         * of redirecting away, right after a successful login/2fa response
         * carries `must_change_password: true` (see auth.js). Deliberately
         * no "Girişe dön"/vazgeç link and no cancel path - the account is
         * already authenticated at this point (the session cookie is set),
         * this is a mandatory gate before scpAuth's redirect_url is
         * followed, not a separate auth flow. Posts to the SAME
         * seviye/v1/auth/set-password endpoint the token-based "Şifremi
         * Unuttum" flow uses, with the `password_change_token` finishLogin()
         * included in its response (see auth.js) - no separate session-
         * gated endpoint (see AuthRestController's own docblock on why).
         */
        ?>
        <form class="scp-auth-form" data-scp-view="require-password-change" hidden>
            <p class="scp-auth-hint">
                <?php esc_html_e(
                    'Bu hesaba kurum tarafından oluşturulan bir şifreyle giriş yaptınız. Devam etmeden önce kendi şifrenizi belirleyin.',
                    'seviye-storefront'
                ); ?>
            </p>

            <label class="scp-auth-field">
                <span><?php esc_html_e('Yeni Şifre', 'seviye-storefront'); ?></span>
                <input type="password" name="password" autocomplete="new-password" minlength="8" required>
            </label>

            <div class="scp-password-strength" data-scp-password-strength hidden>
                <div class="scp-password-strength__track">
                    <div class="scp-password-strength__fill" data-scp-password-strength-fill></div>
                </div>
                <span class="scp-password-strength__label" data-scp-password-strength-label></span>
            </div>

            <label class="scp-auth-field">
                <span><?php esc_html_e('Yeni Şifre (Tekrar)', 'seviye-storefront'); ?></span>
                <input type="password" name="password_confirm" autocomplete="new-password" minlength="8" required>
            </label>

            <button type="submit" class="scp-auth-submit"><?php esc_html_e('Şifreyi Kaydet ve Devam Et', 'seviye-storefront'); ?></button>
        </form>
    </div>
</main>
<?php wp_footer(); ?>
</body>
</html>
