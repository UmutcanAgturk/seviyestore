<?php

declare(strict_types=1);

namespace Seviye\Api\Http;

use Seviye\Api\Domain\ApiKey;
use Seviye\Api\Rbac\ApiCapability;
use Seviye\Api\Repository\ApiKeyRepositoryInterface;
use Seviye\Api\Support\ApiKeyGenerator;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/api-keys - Genel Merkez only
 * ({@see ApiCapability::MANAGE_API_KEYS}). Unlike 2FA/Notifications'
 * self-service "own resource" endpoints, this manages a shared,
 * administrative resource: every key platform-wide, for any user (see
 * Domain\ApiKey's docblock - a key's owner is typically a `Sistem`-role
 * service account provisioned in wp-admin for one specific external
 * integration, not necessarily the Genel Merkez user who issued it).
 *
 * POST returns the plain key exactly once - see Domain\GeneratedApiKey.
 * Every other response (including the create response's own `key` field
 * being the only exception) exposes only `key_prefix`, never the key or its
 * hash.
 */
final class ApiKeysRestController extends AbstractRestController
{
    public function __construct(private readonly ApiKeyRepositoryInterface $apiKeys)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/api-keys', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => $this->requireCapability(ApiCapability::MANAGE_API_KEYS->value),
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create'],
                'permission_callback' => $this->requireCapability(ApiCapability::MANAGE_API_KEYS->value),
                'args' => [
                    'label' => ['required' => true, 'type' => 'string'],
                    'user_id' => ['required' => false, 'type' => 'integer'],
                ],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/api-keys/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'revoke'],
            'permission_callback' => $this->requireCapability(ApiCapability::MANAGE_API_KEYS->value),
        ]);
    }

    public function index(): WP_REST_Response
    {
        return new WP_REST_Response(array_map($this->present(...), $this->apiKeys->all()));
    }

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        $label = sanitize_text_field((string) $request->get_param('label'));

        if ($label === '') {
            return new WP_REST_Response(['message' => __('Bir etiket girilmelidir.', 'seviye-api')], 422);
        }

        $rawUserId = $request->get_param('user_id');
        $userId = $rawUserId !== null ? (int) $rawUserId : get_current_user_id();

        if (!get_userdata($userId)) {
            return new WP_REST_Response(['message' => __('Belirtilen kullanıcı bulunamadı.', 'seviye-api')], 422);
        }

        $generated = ApiKeyGenerator::generate();
        $apiKey = $this->apiKeys->create($userId, $label, $generated->prefix, $generated->hash);

        return new WP_REST_Response(array_merge($this->present($apiKey), ['key' => $generated->plainKey]), 201);
    }

    public function revoke(WP_REST_Request $request): WP_REST_Response
    {
        $this->apiKeys->revoke((int) $request->get_param('id'));

        return new WP_REST_Response(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ApiKey $apiKey): array
    {
        return [
            'id' => $apiKey->id,
            'user_id' => $apiKey->userId,
            'label' => $apiKey->label,
            'key_prefix' => $apiKey->keyPrefix,
            'created_at' => $apiKey->createdAt,
            'last_used_at' => $apiKey->lastUsedAt,
            'revoked_at' => $apiKey->revokedAt,
        ];
    }
}
