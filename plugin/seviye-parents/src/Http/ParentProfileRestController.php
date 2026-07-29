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
            return new WP_REST_Response([
                'phone' => null,
                'notification_preference' => NotificationPreference::EMAIL->value,
                'kvkk_consent_given' => false,
            ]);
        }

        return new WP_REST_Response($this->serialize($profile));
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $preference = NotificationPreference::tryFrom((string) $request->get_param('notification_preference'))
            ?? NotificationPreference::EMAIL;

        $phone = $request->get_param('phone');

        $profile = $this->profiles->upsert(
            get_current_user_id(),
            $phone !== null && $phone !== '' ? (string) $phone : null,
            $preference,
            (bool) $request->get_param('kvkk_consent')
        );

        return new WP_REST_Response($this->serialize($profile));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ParentProfile $profile): array
    {
        return [
            'phone' => $profile->phone,
            'notification_preference' => $profile->notificationPreference->value,
            'kvkk_consent_given' => $profile->hasGivenKvkkConsent(),
        ];
    }
}
