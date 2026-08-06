<?php

declare(strict_types=1);

namespace Seviye\Notifications\Http;

use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Settings\SettingsRepositoryInterface;
use Seviye\Notifications\Channel\NetgsmSmsChannel;
use Seviye\Notifications\Channel\WhatsAppChannel;
use Seviye\Notifications\Rbac\NotificationCapability;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/notifications/sms-settings - read/write the NetGSM SMS gateway
 * credentials (see {@see NetgsmSmsChannel}). seviye/v1/notifications/
 * whatsapp-settings - the same for the WhatsApp Business Cloud API (see
 * {@see WhatsAppChannel}), added to this SAME controller rather than a new
 * one since it's the identical "read/write one third-party gateway's
 * credentials" shape. Genel Merkez only
 * ({@see NotificationCapability::MANAGE_NOTIFICATION_SETTINGS}).
 *
 * The password/access token is a write-only secret: GET never returns it
 * (only the non-secret fields and a `configured` boolean), and PUT leaves
 * the stored secret untouched when the request omits it - the same "don't
 * blank out a secret the caller isn't trying to change" UX every panel form
 * with a password field needs, first applied here since no earlier REST
 * endpoint in this platform has stored a third-party credential.
 */
final class NotificationsSettingsRestController extends AbstractRestController
{
    public function __construct(private readonly SettingsRepositoryInterface $settings)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/notifications/sms-settings', [
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
                    'usercode' => ['required' => true, 'type' => 'string'],
                    'password' => ['required' => false, 'type' => 'string'],
                    'msgheader' => ['required' => true, 'type' => 'string'],
                ],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/notifications/whatsapp-settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'showWhatsApp'],
                'permission_callback' => $this->requireCapability(
                    NotificationCapability::MANAGE_NOTIFICATION_SETTINGS->value
                ),
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateWhatsApp'],
                'permission_callback' => $this->requireCapability(
                    NotificationCapability::MANAGE_NOTIFICATION_SETTINGS->value
                ),
                'args' => [
                    'phone_number_id' => ['required' => true, 'type' => 'string'],
                    'access_token' => ['required' => false, 'type' => 'string'],
                ],
            ],
        ]);
    }

    public function show(): WP_REST_Response
    {
        $usercode = (string) ($this->settings->get(NetgsmSmsChannel::SETTING_USERCODE) ?? '');
        $password = $this->settings->get(NetgsmSmsChannel::SETTING_PASSWORD);
        $msgheader = (string) ($this->settings->get(NetgsmSmsChannel::SETTING_HEADER) ?? '');

        return new WP_REST_Response([
            'usercode' => $usercode,
            'msgheader' => $msgheader,
            'configured' => $usercode !== '' && $password !== null && $password !== '' && $msgheader !== '',
        ]);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $usercode = sanitize_text_field((string) $request->get_param('usercode'));
        $msgheader = sanitize_text_field((string) $request->get_param('msgheader'));
        $password = $request->get_param('password');

        $this->settings->set(NetgsmSmsChannel::SETTING_USERCODE, $usercode);
        $this->settings->set(NetgsmSmsChannel::SETTING_HEADER, $msgheader);

        if ($password !== null && $password !== '') {
            $this->settings->set(NetgsmSmsChannel::SETTING_PASSWORD, (string) $password);
        }

        return $this->show();
    }

    public function showWhatsApp(): WP_REST_Response
    {
        $phoneNumberId = (string) ($this->settings->get(WhatsAppChannel::SETTING_PHONE_NUMBER_ID) ?? '');
        $accessToken = $this->settings->get(WhatsAppChannel::SETTING_ACCESS_TOKEN);

        return new WP_REST_Response([
            'phone_number_id' => $phoneNumberId,
            'configured' => $phoneNumberId !== '' && $accessToken !== null && $accessToken !== '',
        ]);
    }

    public function updateWhatsApp(WP_REST_Request $request): WP_REST_Response
    {
        $phoneNumberId = sanitize_text_field((string) $request->get_param('phone_number_id'));
        $accessToken = $request->get_param('access_token');

        $this->settings->set(WhatsAppChannel::SETTING_PHONE_NUMBER_ID, $phoneNumberId);

        if ($accessToken !== null && $accessToken !== '') {
            $this->settings->set(WhatsAppChannel::SETTING_ACCESS_TOKEN, (string) $accessToken);
        }

        return $this->showWhatsApp();
    }
}
