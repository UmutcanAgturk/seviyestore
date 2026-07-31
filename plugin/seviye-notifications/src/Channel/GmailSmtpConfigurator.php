<?php

declare(strict_types=1);

namespace Seviye\Notifications\Channel;

use PHPMailer\PHPMailer\PHPMailer;
use Seviye\Core\Settings\SettingsRepositoryInterface;

/**
 * "Google maili özelinde göndereceğiz" - routes every wp_mail() call
 * (EmailChannel's transport, and WordPress core's own mails) through
 * Gmail's SMTP relay instead of whatever the host's default PHP mail()
 * transport is. Hooks `phpmailer_init`, the WordPress core action that
 * fires right before PHPMailer sends, with the live PHPMailer instance
 * passed by reference - the standard, plugin-free way to configure SMTP
 * for wp_mail() (see https://developer.wordpress.org/reference/hooks/phpmailer_init/).
 *
 * Credentials (a Gmail address + an "Uygulama Şifresi"/App Password -
 * Google requires this instead of the normal account password for SMTP
 * auth, especially with 2FA enabled) live in Core's
 * {@see SettingsRepositoryInterface}, configured through
 * seviye/v1/notifications/email-settings (Genel Merkez only) - the same
 * Settings-backed, "empty = disabled" pattern NetgsmSmsChannel established
 * for the SMS gateway. Missing credentials are not an error to crash on:
 * configure() simply leaves PHPMailer on its default transport, exactly
 * like NetgsmSmsChannel's "no-op when unconfigured" SMS behaviour.
 */
final class GmailSmtpConfigurator
{
    public const SETTING_EMAIL = 'notifications.gmail.email';
    public const SETTING_APP_PASSWORD = 'notifications.gmail.app_password';

    private const SMTP_HOST = 'smtp.gmail.com';
    private const SMTP_PORT = 587;

    public function __construct(private readonly SettingsRepositoryInterface $settings)
    {
    }

    public function register(): void
    {
        add_action('phpmailer_init', [$this, 'configure']);
    }

    public function configure(PHPMailer $phpmailer): void
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = self::SMTP_HOST;
        $phpmailer->Port = self::SMTP_PORT;
        $phpmailer->SMTPAuth = true;
        $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $phpmailer->Username = $credentials['email'];
        $phpmailer->Password = $credentials['app_password'];
        $phpmailer->setFrom($credentials['email'], $this->fromName());
    }

    /**
     * @return array{email: string, app_password: string}|null
     */
    private function credentials(): ?array
    {
        $email = $this->settings->get(self::SETTING_EMAIL);
        $appPassword = $this->settings->get(self::SETTING_APP_PASSWORD);

        if (!is_string($email) || $email === '' || !is_string($appPassword) || $appPassword === '') {
            return null;
        }

        return ['email' => $email, 'app_password' => $appPassword];
    }

    private function fromName(): string
    {
        return function_exists('get_bloginfo') ? (string) get_bloginfo('name') : 'Seviye';
    }
}
