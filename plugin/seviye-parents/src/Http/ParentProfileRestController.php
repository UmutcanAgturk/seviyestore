<?php

declare(strict_types=1);

namespace Seviye\Parents\Http;

use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Parents\Domain\NotificationPreference;
use Seviye\Parents\Domain\ParentProfile;
use Seviye\Parents\Rbac\ParentCapability;
use Seviye\Parents\Repository\ParentProfileRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/parents/me. Always the *current* user's own profile - there is
 * no "view another parent's profile" endpoint, so no ownership check beyond
 * the capability itself is needed.
 *
 * `username` (wp_users.user_login) is returned but NEVER writable - "Kullanıcı
 * adı kısmı veliler tarafından değiştirilmesin" - it is the veli's login
 * identifier and changing it here would have no matching UI anywhere else
 * on the platform to keep in sync. `email` (wp_users.user_email) IS
 * writable: unlike `phone`/`notification_preference` (Seviye-specific,
 * stored in scp_parent_profiles), it is a native WP user field updated via
 * wp_update_user() directly - there is no Seviye-owned "email" column to
 * keep in sync with it.
 */
final class ParentProfileRestController extends AbstractRestController
{
    public function __construct(private readonly ParentProfileRepositoryInterface $profiles)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/parents/me', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => $this->requireCapability(ParentCapability::MANAGE_OWN_PROFILE->value),
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => $this->requireCapability(ParentCapability::MANAGE_OWN_PROFILE->value),
                'args' => [
                    'email' => ['required' => false, 'type' => 'string'],
                    'phone' => ['required' => false, 'type' => 'string'],
                    'notification_preference' => ['required' => false, 'type' => 'string'],
                    'kvkk_consent' => ['required' => false, 'type' => 'boolean', 'default' => false],
                ],
            ],
        ]);
    }

    public function show(): WP_REST_Response
    {
        $profile = $this->profiles->findByUserId(get_current_user_id());

        if ($profile === null) {
            return new WP_REST_Response(array_merge($this->accountFields(), [
                'phone' => null,
                'notification_preference' => NotificationPreference::EMAIL->value,
                'kvkk_consent_given' => false,
            ]));
        }

        return new WP_REST_Response($this->serialize($profile));
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $emailError = $this->maybeUpdateEmail($request);

        if ($emailError !== null) {
            return new WP_REST_Response(['message' => $emailError], 422);
        }

        $preference = NotificationPreference::tryFrom((string) $request->get_param('notification_preference'))
            ?? NotificationPreference::EMAIL;

        $rawPhone = trim((string) $request->get_param('phone'));
        $phone = null;

        if ($rawPhone !== '') {
            $phone = $this->normalizeTurkishMobile($rawPhone);

            if ($phone === null) {
                $message = __(
                    'Geçersiz telefon numarası. Türkiye cep telefonu formatında girin (05XX XXX XX XX).',
                    'seviye-parents'
                );

                return new WP_REST_Response(['message' => $message], 422);
            }
        }

        $profile = $this->profiles->upsert(
            get_current_user_id(),
            $phone,
            $preference,
            (bool) $request->get_param('kvkk_consent')
        );

        return new WP_REST_Response($this->serialize($profile));
    }

    /**
     * @return string|null an error message, or null on success/no-op
     */
    private function maybeUpdateEmail(WP_REST_Request $request): ?string
    {
        $email = $request->get_param('email');

        if ($email === null || $email === '') {
            return null;
        }

        $email = sanitize_email((string) $email);

        if (!is_email($email)) {
            return __('Geçerli bir e-posta adresi girin.', 'seviye-parents');
        }

        $userId = get_current_user_id();
        $existing = email_exists($email);

        if ($existing !== false && (int) $existing !== $userId) {
            return __('Bu e-posta zaten başka bir kullanıcıya ait.', 'seviye-parents');
        }

        $updated = wp_update_user(['ID' => $userId, 'user_email' => $email]);

        return is_wp_error($updated) ? $updated->get_error_message() : null;
    }

    /**
     * Strips separators/prefixes (0, +90, 90, 0090) and requires exactly a
     * 10-digit Turkish mobile number starting with 5 (05XX XXX XX XX) -
     * "telefon numarası bölümünde türkiye özelinde olacak". Normalized
     * storage (10 digits, no leading 0) matches what
     * Seviye\Notifications\Channel\NetgsmSmsChannel::normalizePhone()
     * already expects on read.
     */
    private function normalizeTurkishMobile(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (str_starts_with($digits, '0090')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '90') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^5\d{9}$/', $digits) === 1 ? $digits : null;
    }

    /**
     * @return array<string, string>
     */
    private function accountFields(): array
    {
        $user = wp_get_current_user();

        return [
            'username' => $user->user_login,
            'email' => $user->user_email,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ParentProfile $profile): array
    {
        return array_merge($this->accountFields(), [
            'phone' => $profile->phone,
            'notification_preference' => $profile->notificationPreference->value,
            'kvkk_consent_given' => $profile->hasGivenKvkkConsent(),
        ]);
    }
}
