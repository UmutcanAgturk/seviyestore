<?php

declare(strict_types=1);

namespace Seviye\Notifications\Channel;

use Seviye\Core\Settings\SettingsRepositoryInterface;

/**
 * Adapter for NetGSM's publicly documented "tekli/toplu SMS gönderim" REST
 * API (https://www.netgsm.com.tr/dokuman/), a plain HTTP POST returning a
 * short plain-text status code - no SDK/Composer dependency needed, the
 * same "avoid a heavy dependency" precedent as EmailChannel/TOTP/Reports'
 * XLSX writer. Uses WordPress' own HTTP API (wp_remote_post()) rather than
 * curl directly, so it works unmodified behind whatever proxy/timeout
 * configuration the host WordPress install already has.
 *
 * Credentials (usercode/password/msgheader) live in Core's
 * {@see SettingsRepositoryInterface}, configured through
 * seviye/v1/notifications/sms-settings (Genel Merkez only) - the same
 * Settings-backed, "empty = disabled" pattern Security's IP allowlist
 * established. Missing credentials are not an error state to crash on: this
 * channel simply reports delivery failure, which the dispatcher records
 * honestly (see NotificationsModule's docblock) rather than pretending an
 * unconfigured integration works.
 */
final class NetgsmSmsChannel implements ChannelInterface
{
    public const SETTING_USERCODE = 'notifications.netgsm.usercode';
    public const SETTING_PASSWORD = 'notifications.netgsm.password';
    public const SETTING_HEADER = 'notifications.netgsm.msgheader';

    private const ENDPOINT = 'https://api.netgsm.com.tr/sms/send/get';

    public function __construct(private readonly SettingsRepositoryInterface $settings)
    {
    }

    public function send(string $recipient, string $subject, string $body): bool
    {
        $credentials = $this->credentials();

        if ($credentials === null || !function_exists('wp_remote_post')) {
            return false;
        }

        $response = wp_remote_post(self::ENDPOINT, [
            'timeout' => 10,
            'body' => [
                'usercode' => $credentials['usercode'],
                'password' => $credentials['password'],
                'msgheader' => $credentials['header'],
                'gsmno' => $this->normalizePhone($recipient),
                'message' => $body,
            ],
        ]);

        if (function_exists('is_wp_error') && is_wp_error($response)) {
            return false;
        }

        $responseBody = function_exists('wp_remote_retrieve_body') ? wp_remote_retrieve_body($response) : '';

        return $this->isSuccessCode((string) $responseBody);
    }

    /**
     * @return array{usercode: string, password: string, header: string}|null
     */
    private function credentials(): ?array
    {
        $usercode = $this->settings->get(self::SETTING_USERCODE);
        $password = $this->settings->get(self::SETTING_PASSWORD);
        $header = $this->settings->get(self::SETTING_HEADER);

        if (!$usercode || !$password || !$header) {
            return null;
        }

        return ['usercode' => $usercode, 'password' => $password, 'header' => $header];
    }

    /**
     * NetGSM expects an 11-digit MSISDN starting with 0 (0XXXXXXXXXXX has no
     * separators, no +90/0090 prefix) - normalizes whatever format a
     * scp_parent_profiles.phone value happens to be stored in.
     */
    public function normalizePhone(string $recipient): string
    {
        $digits = preg_replace('/\D/', '', $recipient) ?? '';

        if (str_starts_with($digits, '90') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        }

        if (strlen($digits) === 10 && !str_starts_with($digits, '0')) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    /**
     * NetGSM's plain-text response begins with "00" (queued for immediate
     * sending) or "01" (queued for scheduled sending), followed by a job ID
     * - any other leading two-digit code is a documented error code.
     */
    public function isSuccessCode(string $response): bool
    {
        $code = substr(trim($response), 0, 2);

        return $code === '00' || $code === '01';
    }
}
