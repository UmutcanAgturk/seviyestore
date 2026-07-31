<?php

declare(strict_types=1);

namespace Seviye\Security\Http;

use InvalidArgumentException;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Security\Auth\TcNumber;
use Seviye\Security\Identity\IdentityGatewayInterface;
use Seviye\Security\Rbac\SecurityCapability;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/security/users/{id}/tc-no - links a T.C. Kimlik No to an
 * EXISTING WordPress user. Genel Merkez/native administrator only (same
 * capability as the "Seviye Kullanıcılar" wp-admin pages).
 *
 * Exists so other modules (e.g. Seviye Students, when it auto-creates a
 * Veli account while adding a student - see
 * StudentsRestController::maybeCreateAndLinkParent()) can complete a user's
 * T.C. No login setup WITHOUT a PHP-level dependency on Security's
 * internals: Security already depends on Students' Contracts
 * (StudentDirectoryInterface, see SecurityModule::boot()), so the reverse
 * (Students depending on a Security Contract) would be a circular
 * composer/module dependency. A REST endpoint has no such constraint - the
 * theme's JS simply calls both plugins' APIs in sequence, the same way it
 * already composes students/branches/pricing today.
 */
final class IdentityRestController extends AbstractRestController
{
    public function __construct(private readonly IdentityGatewayInterface $identities)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/security/users/(?P<id>\d+)/tc-no', [
            'methods' => 'POST',
            'callback' => [$this, 'link'],
            'permission_callback' => $this->requireCapability(SecurityCapability::MANAGE_SECURITY_SETTINGS->value),
            'args' => [
                'tc_no' => ['required' => true, 'type' => 'string'],
            ],
        ]);
    }

    public function link(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');

        if (get_userdata($userId) === false) {
            return new WP_REST_Response(['message' => __('Kullanıcı bulunamadı.', 'seviye-security')], 404);
        }

        try {
            $tcNumber = TcNumber::fromString((string) $request->get_param('tc_no'));
        } catch (InvalidArgumentException $exception) {
            return new WP_REST_Response(['message' => $exception->getMessage()], 422);
        }

        try {
            $this->identities->link($tcNumber, $userId);
        } catch (\Throwable $exception) {
            return new WP_REST_Response(['message' => $exception->getMessage()], 409);
        }

        return new WP_REST_Response(['success' => true]);
    }
}
