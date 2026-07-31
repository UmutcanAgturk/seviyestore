<?php

declare(strict_types=1);

namespace Seviye\Notifications\Http;

use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Settings\SettingsRepositoryInterface;
use Seviye\Notifications\Channel\GmailSmtpConfigurator;
use Seviye\Notifications\Rbac\NotificationCapability;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/notifications/email-settings - read/write the Gmail SMTP
 * credentials (see {@see GmailSmtpConfigurator}). Genel Merkez only
 * ({@see NotificationCapability::MANAGE_NOTIFICATION_SETTINGS}), mirrors
 * NotificationsSettingsRestController's SMS counterpart exactly, including
 * the "app_password is a write-only secret" rule: GET never returns it
 * (only the Gmail address and a `configured` boolean), and PUT leaves the
 * stored app password untouched when the request omits it.
 */
final class EmailSettingsRestController extends AbstractRestController
{
    public function __construct(private readonly SettingsRepositoryInterface $settings)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/notifications/email-settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => $this->requireCapability(
                    NotificationCapability::MANAGE_NOTIFICATION_SETTINGS->value
                ),
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => $this->requireCapability(
                    NotificationCapability::MANAGE_NOTIFICATION_SETTINGS->value
                ),
                'args' => [
                    'email' => ['required' => true, 'type' => 'string'],
                    'app_password' => ['required' => false, 'type' => 'string'],
                ],
            ],
        ]);
    }

    public function show(): WP_REST_Response
    {
        $email = (string) ($this->settings->get(GmailSmtpConfigurator::SETTING_EMAIL) ?? '');
        $appPassword = $this->settings->get(GmailSmtpConfigurator::SETTING_APP_PASSWORD);

        return new WP_REST_Response([
            'email' => $email,
            'configured' => $email !== '' && $appPassword !== null && $appPassword !== '',
        ]);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $email = sanitize_email((string) $request->get_param('email'));

        if ($email === '' || !is_email($email)) {
            return new WP_REST_Response(
                ['message' => __('Geçerli bir e-posta adresi girin.', 'seviye-notifications')],
                422
            );
        }

        $this->settings->set(GmailSmtpConfigurator::SETTING_EMAIL, $email);

        $appPassword = $request->get_param('app_password');

        if ($appPassword !== null && $appPassword !== '') {
            $this->settings->set(GmailSmtpConfigurator::SETTING_APP_PASSWORD, (string) $appPassword);
        }

        return $this->show();
    }
}
