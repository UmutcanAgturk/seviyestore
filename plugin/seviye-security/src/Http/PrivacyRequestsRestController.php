<?php

declare(strict_types=1);

namespace Seviye\Security\Http;

use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Parents\Contracts\ParentContactLookupInterface;
use Seviye\Security\Identity\IdentityGatewayInterface;
use Seviye\Security\Privacy\PrivacyExportBuilder;
use Seviye\Security\Privacy\PrivacyRequest;
use Seviye\Security\Privacy\PrivacyRequestGatewayInterface;
use Seviye\Security\Privacy\PrivacyRequestStatus;
use Seviye\Security\Privacy\PrivacyRequestType;
use Seviye\Security\Rbac\PrivacyCapability;
use Seviye\Security\TwoFactor\TwoFactorGatewayInterface;
use Seviye\Students\Contracts\ParentChildrenLookupInterface;
use Seviye\Students\Contracts\StudentSummary;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/privacy/* - KVKK ("Kişisel Verilerin Korunması Kanunu") veri
 * ihracı/silme talepleri.
 *
 * EXPORT completes instantly and self-service (every logged-in user, no
 * capability) - "right to access" should never require someone else's
 * approval to exercise. DELETION only ever queues a PENDING request for
 * Genel Merkez review (MANAGE_PRIVACY_REQUESTS): unlike an export, it has
 * an irreversible side effect (the TC No login mapping is removed, so the
 * account can no longer sign in - see anonymize()), so a compromised
 * session should not be able to trigger it unilaterally.
 *
 * The export deliberately covers only what THIS plugin and its existing
 * dependencies (Students, Parents) can reach: WP account fields, the
 * scp_user_identities TC No + 2FA status, phone (if a veli), and linked
 * children's basic info. Order/hakediş history is NOT included - that is
 * Commerce's data, which this platform must retain regardless of a privacy
 * request (financial-record retention law), and pulling it in would need a
 * new Security -> Commerce dependency this feature's scope does not
 * justify. Anonymization is scoped the same way: it clears the WP account
 * (display name/e-posta) and the scp_user_identities/2FA rows, but never
 * touches WooCommerce customer/billing meta or the öğrenci (child) records
 * themselves - those are the school's own operational/enrollment records,
 * a parent's self-service deletion request has no legal basis to erase
 * them.
 */
final class PrivacyRequestsRestController extends AbstractRestController
{
    public function __construct(
        private readonly PrivacyRequestGatewayInterface $requests,
        private readonly IdentityGatewayInterface $identities,
        private readonly TwoFactorGatewayInterface $twoFactor,
        private readonly ParentContactLookupInterface $parentContacts,
        private readonly ParentChildrenLookupInterface $parentChildren,
        private readonly PrivacyExportBuilder $exportBuilder
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/privacy/requests/export', [
            'methods' => 'POST',
            'callback' => [$this, 'export'],
            'permission_callback' => [$this, 'isLoggedIn'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/privacy/requests/deletion', [
            'methods' => 'POST',
            'callback' => [$this, 'requestDeletion'],
            'permission_callback' => [$this, 'isLoggedIn'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/privacy/requests/mine', [
            'methods' => 'GET',
            'callback' => [$this, 'mine'],
            'permission_callback' => [$this, 'isLoggedIn'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/privacy/requests', [
            'methods' => 'GET',
            'callback' => [$this, 'queue'],
            'permission_callback' => $this->requireCapability(PrivacyCapability::MANAGE_PRIVACY_REQUESTS->value),
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/privacy/requests/(?P<id>\d+)/approve', [
            'methods' => 'POST',
            'callback' => [$this, 'approve'],
            'permission_callback' => $this->requireCapability(PrivacyCapability::MANAGE_PRIVACY_REQUESTS->value),
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/privacy/requests/(?P<id>\d+)/reject', [
            'methods' => 'POST',
            'callback' => [$this, 'reject'],
            'permission_callback' => $this->requireCapability(PrivacyCapability::MANAGE_PRIVACY_REQUESTS->value),
        ]);
    }

    public function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    public function export(): WP_REST_Response
    {
        $userId = get_current_user_id();
        $export = $this->gatherExport($userId);

        $this->requests->create($userId, PrivacyRequestType::EXPORT, PrivacyRequestStatus::COMPLETED, null);

        nocache_headers();
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="veri-ihraci.json"');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() output, not HTML.
        echo wp_json_encode($export, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function gatherExport(int $userId): array
    {
        $user = get_userdata($userId);

        $account = [
            'user_id' => $userId,
            'username' => $user !== false ? $user->user_login : '',
            'email' => $user !== false ? $user->user_email : '',
            'display_name' => $user !== false ? $user->display_name : '',
            'registered_at' => $user !== false ? $user->user_registered : null,
            'roles' => $user !== false ? array_values($user->roles) : [],
        ];

        $tcNumber = $this->identities->findTcNumberByUserId($userId);
        $identity = null;

        if ($tcNumber !== null) {
            $twoFactor = $this->twoFactor->find($userId);
            $identity = [
                'tc_no' => $tcNumber->value(),
                'two_factor_enabled' => $twoFactor !== null && $twoFactor->confirmed,
            ];
        }

        $phone = $this->parentContacts->phoneFor($userId);
        $parentProfile = $phone !== null ? ['phone' => $phone] : null;

        $children = array_map(
            static fn (StudentSummary $student): array => [
                'id' => $student->id,
                'branch_id' => $student->branchId,
                'first_name' => $student->firstName,
                'last_name' => $student->lastName,
            ],
            $this->parentChildren->childrenOf($userId)
        );

        $generatedAt = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');

        return $this->exportBuilder->build($generatedAt, $account, $identity, $parentProfile, $children);
    }

    public function requestDeletion(WP_REST_Request $request): WP_REST_Response
    {
        $note = trim((string) ($request->get_param('note') ?? ''));
        $id = $this->requests->create(
            get_current_user_id(),
            PrivacyRequestType::DELETION,
            PrivacyRequestStatus::PENDING,
            $note !== '' ? $note : null
        );

        return new WP_REST_Response($this->serialize($this->requests->find($id)), 201);
    }

    public function mine(): WP_REST_Response
    {
        return new WP_REST_Response(array_map(
            $this->serialize(...),
            $this->requests->forUser(get_current_user_id())
        ));
    }

    public function queue(): WP_REST_Response
    {
        return new WP_REST_Response(array_map($this->serializeForQueue(...), $this->requests->pending()));
    }

    public function approve(WP_REST_Request $request): WP_REST_Response
    {
        $privacyRequest = $this->requests->find((int) $request->get_param('id'));

        if ($privacyRequest === null || $privacyRequest->status !== PrivacyRequestStatus::PENDING) {
            $message = __('Talep bulunamadı veya zaten sonuçlandırılmış.', 'seviye-security');

            return new WP_REST_Response(['message' => $message], 404);
        }

        $resolutionNote = trim((string) ($request->get_param('resolution_note') ?? ''));
        $this->anonymize($privacyRequest->userId);
        $this->requests->resolve(
            $privacyRequest->id,
            PrivacyRequestStatus::COMPLETED,
            $resolutionNote !== '' ? $resolutionNote : null,
            get_current_user_id()
        );

        return new WP_REST_Response($this->serializeForQueue($this->requests->find($privacyRequest->id)));
    }

    public function reject(WP_REST_Request $request): WP_REST_Response
    {
        $privacyRequest = $this->requests->find((int) $request->get_param('id'));

        if ($privacyRequest === null || $privacyRequest->status !== PrivacyRequestStatus::PENDING) {
            $message = __('Talep bulunamadı veya zaten sonuçlandırılmış.', 'seviye-security');

            return new WP_REST_Response(['message' => $message], 404);
        }

        $resolutionNote = trim((string) ($request->get_param('resolution_note') ?? ''));
        $this->requests->resolve(
            $privacyRequest->id,
            PrivacyRequestStatus::REJECTED,
            $resolutionNote !== '' ? $resolutionNote : null,
            get_current_user_id()
        );

        return new WP_REST_Response($this->serializeForQueue($this->requests->find($privacyRequest->id)));
    }

    /**
     * See class docblock for exactly what this does and does not touch.
     */
    private function anonymize(int $userId): void
    {
        $this->identities->unlink($userId);
        $this->twoFactor->delete($userId);

        if (function_exists('wp_update_user')) {
            wp_update_user([
                'ID' => $userId,
                'display_name' => __('Silinmiş Kullanıcı', 'seviye-security'),
                'first_name' => '',
                'last_name' => '',
                'user_email' => 'silinmis-' . $userId . '@example.invalid',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(?PrivacyRequest $privacyRequest): array
    {
        if ($privacyRequest === null) {
            return [];
        }

        return [
            'id' => $privacyRequest->id,
            'type' => $privacyRequest->type->value,
            'status' => $privacyRequest->status->value,
            'note' => $privacyRequest->note,
            'resolution_note' => $privacyRequest->resolutionNote,
            'requested_at' => $privacyRequest->requestedAt,
            'resolved_at' => $privacyRequest->resolvedAt,
        ];
    }

    /**
     * Same shape as serialize(), plus which user this request is about -
     * irrelevant on "mine" (always the caller) but essential on the admin
     * queue.
     *
     * @return array<string, mixed>
     */
    private function serializeForQueue(?PrivacyRequest $privacyRequest): array
    {
        if ($privacyRequest === null) {
            return [];
        }

        $user = get_userdata($privacyRequest->userId);

        return array_merge($this->serialize($privacyRequest), [
            'user_id' => $privacyRequest->userId,
            'user_display_name' => $user !== false ? $user->display_name : null,
            'user_email' => $user !== false ? $user->user_email : null,
        ]);
    }
}
