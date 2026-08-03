<?php

declare(strict_types=1);

namespace Seviye\Destek\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Destek\Domain\SupportMessage;
use Seviye\Destek\Domain\SupportTicket;
use Seviye\Destek\Rbac\SupportCapability;
use Seviye\Destek\Repository\SupportTicketRepositoryInterface;
use Seviye\Students\Contracts\ParentChildrenLookupInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/destek/tickets/* - "Veli destek/talep sistemi", KVKK talebinden
 * (Seviye Security'nin PrivacyRequestsRestController'ı) TAMAMEN ayrı: genel
 * şikayet/soru bildirme ve şube personelinin yanıtlayıp kapatabileceği bir
 * ticket akışı. Erişim iki eksende ayrılır - veli SUBMIT_TICKET ile yalnızca
 * KENDİ ticket'larına, personel MANAGE_TICKETS ile
 * BranchMembershipInterface::branchIdForUser() üzerinden kendi şubesinin
 * (ya da HQ ise tüm şubelerin) ticket'larına erişir - Students'ın
 * canAccessStudent() ile aynı ilke, bkz.
 * plugin/seviye-students/src/Http/StudentsRestController.php.
 */
final class SupportTicketsRestController extends AbstractRestController
{
    public function __construct(
        private readonly SupportTicketRepositoryInterface $tickets,
        private readonly BranchMembershipInterface $branchMemberships,
        private readonly BranchLookupInterface $branchLookup,
        private readonly ParentChildrenLookupInterface $parentChildren
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/destek/tickets', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => $this->requireCapability(SupportCapability::MANAGE_TICKETS->value),
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'store'],
                'permission_callback' => $this->requireCapability(SupportCapability::SUBMIT_TICKET->value),
                'args' => [
                    'subject' => ['required' => true, 'type' => 'string'],
                    'message' => ['required' => true, 'type' => 'string'],
                    'branch_id' => ['required' => false, 'type' => 'integer'],
                ],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/destek/tickets/mine', [
            'methods' => 'GET',
            'callback' => [$this, 'mine'],
            'permission_callback' => $this->requireCapability(SupportCapability::SUBMIT_TICKET->value),
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/destek/tickets/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'show'],
            'permission_callback' => [$this, 'canAccessTicket'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/destek/tickets/(?P<id>\d+)/messages', [
            'methods' => 'POST',
            'callback' => [$this, 'reply'],
            'permission_callback' => [$this, 'canAccessTicket'],
            'args' => [
                'message' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/destek/tickets/(?P<id>\d+)/close', [
            'methods' => 'POST',
            'callback' => [$this, 'close'],
            'permission_callback' => [$this, 'canManageTicket'],
            'args' => [
                'note' => ['required' => false, 'type' => 'string'],
            ],
        ]);
    }

    public function index(): WP_REST_Response
    {
        $branchId = $this->currentUserBranchId();

        return new WP_REST_Response(array_map($this->serialize(...), $this->tickets->allForBranch($branchId)));
    }

    public function mine(): WP_REST_Response
    {
        $tickets = $this->tickets->allForUser(get_current_user_id());

        return new WP_REST_Response(array_map($this->serialize(...), $tickets));
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $subject = trim((string) $request->get_param('subject'));
        $message = trim((string) $request->get_param('message'));

        if ($subject === '' || $message === '') {
            return new WP_REST_Response(['message' => __('Konu ve mesaj gerekli.', 'seviye-destek')], 422);
        }

        [$branchId, $error] = $this->resolveBranchId($request);

        if ($error !== null) {
            return new WP_REST_Response(['message' => $error], 422);
        }

        $ticket = $this->tickets->create($branchId, get_current_user_id(), $subject, $message);

        return new WP_REST_Response($this->serialize($ticket), 201);
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $ticket = $this->tickets->find((int) $request->get_param('id'));

        if ($ticket === null) {
            return new WP_REST_Response(['message' => __('Destek talebi bulunamadı.', 'seviye-destek')], 404);
        }

        return new WP_REST_Response($this->serialize($ticket));
    }

    public function reply(WP_REST_Request $request): WP_REST_Response
    {
        $ticket = $this->tickets->find((int) $request->get_param('id'));

        if ($ticket === null) {
            return new WP_REST_Response(['message' => __('Destek talebi bulunamadı.', 'seviye-destek')], 404);
        }

        $message = trim((string) $request->get_param('message'));

        if ($message === '') {
            return new WP_REST_Response(['message' => __('Mesaj gerekli.', 'seviye-destek')], 422);
        }

        $isStaff = current_user_can(SupportCapability::MANAGE_TICKETS->value);
        $this->tickets->addMessage($ticket->id, get_current_user_id(), $isStaff, $message);

        return new WP_REST_Response($this->serialize($this->tickets->find($ticket->id)));
    }

    public function close(WP_REST_Request $request): WP_REST_Response
    {
        $ticket = $this->tickets->find((int) $request->get_param('id'));

        if ($ticket === null) {
            return new WP_REST_Response(['message' => __('Destek talebi bulunamadı.', 'seviye-destek')], 404);
        }

        $note = trim((string) ($request->get_param('note') ?? ''));
        $this->tickets->close($ticket->id, get_current_user_id(), $note !== '' ? $note : null);

        return new WP_REST_Response($this->serialize($this->tickets->find($ticket->id)));
    }

    public function canAccessTicket(WP_REST_Request $request): bool
    {
        $ticket = $this->tickets->find((int) $request->get_param('id'));

        if ($ticket === null) {
            // Erişim reddi 404'ü gizlemez - show()/reply() zaten "bulunamadı"
            // döner, burada yalnızca gerçek sahiplik/şube kontrolü var.
            return current_user_can(SupportCapability::SUBMIT_TICKET->value)
                || current_user_can(SupportCapability::MANAGE_TICKETS->value);
        }

        $isOwner = $ticket->createdByUserId === get_current_user_id();

        if (current_user_can(SupportCapability::SUBMIT_TICKET->value) && $isOwner) {
            return true;
        }

        return $this->canManageThisTicket($ticket);
    }

    public function canManageTicket(WP_REST_Request $request): bool
    {
        $ticket = $this->tickets->find((int) $request->get_param('id'));

        return $ticket !== null && $this->canManageThisTicket($ticket);
    }

    private function canManageThisTicket(SupportTicket $ticket): bool
    {
        if (!current_user_can(SupportCapability::MANAGE_TICKETS->value)) {
            return false;
        }

        $branchId = $this->currentUserBranchId();

        return $branchId === null || $ticket->branchId === $branchId;
    }

    /**
     * @return array{0: ?int, 1: ?string}
     */
    private function resolveBranchId(WP_REST_Request $request): array
    {
        $raw = $request->get_param('branch_id');

        if ($raw === null || $raw === '') {
            return [null, null];
        }

        $branchId = (int) $raw;
        $childrenBranchIds = array_map(
            static fn ($child): int => $child->branchId,
            $this->parentChildren->childrenOf(get_current_user_id())
        );

        if (!in_array($branchId, $childrenBranchIds, true) || !$this->branchLookup->exists($branchId)) {
            return [null, __('Geçersiz şube.', 'seviye-destek')];
        }

        return [$branchId, null];
    }

    private function currentUserBranchId(): ?int
    {
        return $this->branchMemberships->branchIdForUser(get_current_user_id());
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'branch_id' => $ticket->branchId,
            'branch_name' => $ticket->branchId !== null ? $this->branchLookup->find($ticket->branchId)?->name : null,
            'created_by' => $ticket->createdByUserId,
            'subject' => $ticket->subject,
            'status' => $ticket->status->value,
            'created_at' => $ticket->createdAt,
            'updated_at' => $ticket->updatedAt,
            'messages' => array_map($this->serializeMessage(...), $ticket->messages),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(SupportMessage $message): array
    {
        return [
            'id' => $message->id,
            'author_user_id' => $message->authorUserId,
            'is_staff' => $message->isStaff,
            'message' => $message->message,
            'created_at' => $message->createdAt,
        ];
    }
}
