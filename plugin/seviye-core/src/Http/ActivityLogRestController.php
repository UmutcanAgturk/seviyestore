<?php

declare(strict_types=1);

namespace Seviye\Core\Http;

use Seviye\Core\Logging\LogEntry;
use Seviye\Core\Logging\LogFilter;
use Seviye\Core\Logging\WpdbLogQuery;
use Seviye\Core\Rbac\Capability;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/core/activity-log - "Genel merkez hesabından tüm yapılan
 * aktiviteleri de gösterecek başka bir menü de ekle": read-only, gated on
 * Capability::VIEW_AUDIT_LOGS (Genel Merkez only per
 * Rbac\RoleDefinitions::defaults() - this capability existed since the
 * platform's very first RBAC pass but had no REST surface or UI until
 * now). Reads Logging\RequestActivityLogger's/every module's existing
 * scp_logs rows through {@see WpdbLogQuery}; this controller adds no
 * writes of its own.
 */
final class ActivityLogRestController extends AbstractRestController
{
    public function __construct(private readonly WpdbLogQuery $logs)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/core/activity-log', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => $this->requireCapability(Capability::VIEW_AUDIT_LOGS->value),
        ]);
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $filter = new LogFilter(
            channel: $this->stringParam($request, 'channel'),
            level: $this->stringParam($request, 'level'),
            userId: $this->intParam($request, 'user_id'),
            fromDate: $this->stringParam($request, 'from'),
            toDate: $this->stringParam($request, 'to'),
            search: $this->stringParam($request, 'search'),
            limit: $this->intParam($request, 'limit') ?? 200
        );

        return new WP_REST_Response(array_map($this->serialize(...), $this->logs->search($filter)));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(LogEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'channel' => $entry->channel,
            'level' => $entry->level,
            'message' => $entry->message,
            'context' => $entry->context,
            'user_id' => $entry->userId,
            'user_name' => $entry->userName,
            'ip_address' => $entry->ipAddress,
            'created_at' => $entry->createdAt,
        ];
    }

    private function intParam(WP_REST_Request $request, string $name): ?int
    {
        $value = $request->get_param($name);

        return $value === null || $value === '' ? null : (int) $value;
    }

    private function stringParam(WP_REST_Request $request, string $name): ?string
    {
        $value = $request->get_param($name);

        return $value === null || $value === '' ? null : (string) $value;
    }
}
