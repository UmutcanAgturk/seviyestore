<?php

declare(strict_types=1);

namespace Seviye\Core\Http;

use Seviye\Core\Rbac\Capability;
use Seviye\Core\Settings\SettingsRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/core/branding - the platform's own logo, stored as a single
 * attachment id (an existing WordPress media item - uploaded through core's
 * own /wp/v2/media REST endpoint by the theme's "Görünüm" panel, never
 * re-implemented here). Genel Merkez only
 * ({@see Capability::MANAGE_CORE_SETTINGS}), same reasoning as
 * SecuritySettingsRestController's IP allowlist: branding is a platform-wide
 * setting, not something any single branch should be able to change for
 * everyone else.
 */
final class BrandingRestController extends AbstractRestController
{
    /**
     * Public (not private, unlike most of this controller's internals) so
     * the theme can read the same setting directly for header.php/login.php
     * - mirrors Seviye\Security\Routing\IpAllowlist::SETTING_KEY, which
     * inc/ip-restriction.php already imports the same way.
     */
    public const LOGO_ATTACHMENT_ID_KEY = 'branding_logo_attachment_id';

    public function __construct(private readonly SettingsRepositoryInterface $settings)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/core/branding', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => $this->requireCapability(Capability::MANAGE_CORE_SETTINGS->value),
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => $this->requireCapability(Capability::MANAGE_CORE_SETTINGS->value),
                'args' => [
                    'logo_attachment_id' => ['required' => true, 'type' => 'integer'],
                ],
            ],
        ]);
    }

    public function show(): WP_REST_Response
    {
        return new WP_REST_Response($this->serialize($this->currentLogoAttachmentId()));
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $attachmentId = (int) $request->get_param('logo_attachment_id');

        // 0 explicitly clears the logo (removes the setting value) - any
        // other value must be a real, existing image attachment, not an
        // arbitrary post id.
        if ($attachmentId !== 0 && !wp_attachment_is_image($attachmentId)) {
            return new WP_REST_Response(['message' => __('Geçersiz görsel.', 'seviye-core')], 422);
        }

        $this->settings->set(self::LOGO_ATTACHMENT_ID_KEY, (string) $attachmentId);

        return new WP_REST_Response($this->serialize($attachmentId));
    }

    /**
     * @return array{logo_attachment_id: ?int, logo_url: ?string}
     */
    private function serialize(?int $attachmentId): array
    {
        return [
            'logo_attachment_id' => $attachmentId ?: null,
            'logo_url' => $attachmentId ? (wp_get_attachment_image_url($attachmentId, 'medium') ?: null) : null,
        ];
    }

    private function currentLogoAttachmentId(): ?int
    {
        $raw = $this->settings->get(self::LOGO_ATTACHMENT_ID_KEY);

        return $raw !== null && $raw !== '' && (int) $raw > 0 ? (int) $raw : null;
    }
}
