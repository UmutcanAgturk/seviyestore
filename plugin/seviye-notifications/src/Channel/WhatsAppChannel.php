<?php

declare(strict_types=1);

namespace Seviye\Notifications\Channel;

use Seviye\Core\Settings\SettingsRepositoryInterface;

/**
 * Adapter for Meta's WhatsApp Business Cloud API
 * (https://developers.facebook.com/docs/whatsapp/cloud-api/) - a documented
 * REST API (JSON POST + Bearer token), no SDK/Composer dependency needed,
 * the same "avoid a heavy dependency" precedent as EmailChannel/NetgsmSmsChannel.
 * Uses WordPress' own HTTP API (wp_remote_post()), same reasoning as
 * NetgsmSmsChannel.
 *
 * Credentials (phone_number_id/access_token) live in Core's
 * {@see SettingsRepositoryInterface}, the same Settings-backed,
 * "empty = disabled" pattern NetgsmSmsChannel established.
 *
 * IMPORTANT, honestly documented constraint this platform cannot code
 * around: WhatsApp's Cloud API only allows a free-form `type: text` message
 * (what this class sends) either (a) within 24 hours of the recipient's
 * last message TO the business, or (b) using a Meta-pre-approved message
 * TEMPLATE outside that window - a template requires business
 * verification and per-template review in Meta's own dashboard, which is
 * an account-setup step outside this codebase's reach, not something this
 * class can satisfy generically. In practice, an unprompted "your order
 * shipped" notification sent to a parent who hasn't messaged the business
 * WhatsApp number recently will likely be REJECTED by Meta's API (a
 * documented error code, not a PHP exception) - this channel simply
 * reports delivery failure, which the dispatcher records honestly (see
 * NotificationsModule's docblock), rather than pretending an unconfigured/
 * unapproved integration works. A production deployment wanting reliable
 * proactive WhatsApp notifications needs an approved template message
 * type, which is a Meta Business Manager configuration step - not
 * something coded here.
 */
final class WhatsAppChannel implements ChannelInterface
{
    public const SETTING_PHONE_NUMBER_ID = 'notifications.whatsapp.phone_number_id';
    public const SETTING_ACCESS_TOKEN = 'notifications.whatsapp.access_token';

    private const API_VERSION = 'v20.0';

    public function __construct(private readonly SettingsRepositoryInterface $settings)
    {
    }

    public function send(string $recipient, string $subject, string $body): bool
    {
        $credentials = $this->credentials();

        if ($credentials === null || !function_exists('wp_remote_post')) {
            return false;
        }

        $endpoint = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            self::API_VERSION,
            $credentials['phoneNumberId']
        );

        $response = wp_remote_post($endpoint, [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $credentials['accessToken'],
                'Content-Type' => 'application/json',
            ],
            'body' => (string) wp_json_encode([
                'messaging_product' => 'whatsapp',
                'to' => $this->normalizePhone($recipient),
                'type' => 'text',
                'text' => ['body' => $body],
            ]),
        ]);

        if (function_exists('is_wp_error') && is_wp_error($response)) {
            return false;
        }

        $statusCode = function_exists('wp_remote_retrieve_response_code')
            ? (int) wp_remote_retrieve_response_code($response)
            : 0;

        return $statusCode >= 200 && $statusCode < 300;
    }

    /**
     * @return array{phoneNumberId: string, accessToken: string}|null
     */
    private function credentials(): ?array
    {
        $phoneNumberId = $this->settings->get(self::SETTING_PHONE_NUMBER_ID);
        $accessToken = $this->settings->get(self::SETTING_ACCESS_TOKEN);

        if (!$phoneNumberId || !$accessToken) {
            return null;
        }

        return ['phoneNumberId' => $phoneNumberId, 'accessToken' => $accessToken];
    }

    /**
     * WhatsApp Cloud API expects the recipient as digits only, country code
     * included, no leading "+"/"00" (e.g. "905551234567") - normalizes
     * whatever format a scp_parent_profiles.phone value happens to be
     * stored in (the inverse of NetgsmSmsChannel::normalizePhone(), which
     * targets NetGSM's own different expected format).
     */
    public function normalizePhone(string $recipient): string
    {
        $digits = preg_replace('/\D/', '', $recipient) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = '90' . substr($digits, 1);
        }

        if (strlen($digits) === 10 && !str_starts_with($digits, '0')) {
            $digits = '90' . $digits;
        }

        return $digits;
    }
}
