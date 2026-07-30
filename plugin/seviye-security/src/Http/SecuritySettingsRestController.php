<?php

declare(strict_types=1);

namespace Seviye\Security\Http;

use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Settings\SettingsRepositoryInterface;
use Seviye\Security\Rbac\SecurityCapability;
use Seviye\Security\Routing\IpAllowlist;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/security/ip-allowlist - read/write the /admin zone's IP
 * allowlist (see {@see IpAllowlist}). Genel Merkez only
 * ({@see SecurityCapability::MANAGE_SECURITY_SETTINGS}).
 */
final class SecuritySettingsRestController extends AbstractRestController
{
    public function __construct(private readonly SettingsRepositoryInterface $settings)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/security/ip-allowlist', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => $this->requireCapability(SecurityCapability::MANAGE_SECURITY_SETTINGS->value),
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => $this->requireCapability(SecurityCapability::MANAGE_SECURITY_SETTINGS->value),
                'args' => [
                    'entries' => ['required' => true, 'type' => 'array'],
                ],
            ],
        ]);
    }

    public function show(): WP_REST_Response
    {
        return new WP_REST_Response([
            'entries' => IpAllowlist::parseEntries($this->settings->get(IpAllowlist::SETTING_KEY)),
        ]);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $entries = (array) $request->get_param('entries');
        $sanitized = array_map(static fn (mixed $entry): string => sanitize_text_field((string) $entry), $entries);

        $this->settings->set(IpAllowlist::SETTING_KEY, implode("\n", $sanitized));

        return new WP_REST_Response(['entries' => IpAllowlist::parseEntries(implode("\n", $sanitized))]);
    }
}
