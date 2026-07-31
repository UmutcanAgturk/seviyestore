<?php

declare(strict_types=1);

namespace Seviye\Core\Logging;

use Psr\Log\LoggerInterface;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Hooks `rest_request_after_callbacks` - a WordPress core filter that runs
 * once for every REST request, right after its route handler returned -
 * to record every state-changing (POST/PUT/PATCH/DELETE) call made under
 * this platform's own seviye/v1 namespace into scp_logs via
 * {@see DatabaseLogger}. This is the one place that captures "every
 * meaningful action taken on the platform" (Http\ActivityLogRestController's
 * "Aktivite Günlüğü") without requiring each of the other modules' REST
 * controllers to remember to call a logger themselves - genuinely
 * comprehensive today and automatically covers any route a module adds
 * later, since it hooks the dispatch pipeline itself rather than any one
 * controller.
 *
 * GET requests are never logged (reads aren't "activity"). `/auth/*` is
 * excluded entirely: those routes carry a raw password in the request body
 * and Seviye Security's AuthService already logs login outcomes itself
 * with better semantics (see SecurityModule) - logging them here too would
 * both duplicate and risk a redaction miss on a genuinely sensitive field.
 * Every other route's params are persisted as context with password/secret
 * -shaped fields redacted.
 */
final class RequestActivityLogger
{
    private const LOGGED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const EXCLUDED_ROUTE_PREFIX = '/seviye/v1/auth/';

    private const REDACTED_PARAMS = [
        'password',
        'user_pass',
        'new_password',
        'current_password',
        'code',
        'secret',
        'token',
        'api_key',
    ];

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function register(): void
    {
        add_filter('rest_request_after_callbacks', [$this, 'log'], 10, 3);
    }

    /**
     * @param WP_REST_Response|WP_Error|mixed $response
     * @return WP_REST_Response|WP_Error|mixed
     */
    public function log($response, mixed $handler, WP_REST_Request $request): mixed
    {
        $route = $request->get_route();

        if (
            !in_array($request->get_method(), self::LOGGED_METHODS, true)
            || !str_starts_with($route, '/seviye/v1/')
            || str_starts_with($route, self::EXCLUDED_ROUTE_PREFIX)
        ) {
            return $response;
        }

        $status = match (true) {
            $response instanceof WP_REST_Response => $response->get_status(),
            $response instanceof WP_Error => 500,
            default => 200,
        };

        $this->logger->info($request->get_method() . ' ' . $route, [
            'channel' => 'activity',
            'route' => $route,
            'method' => $request->get_method(),
            'status' => $status,
            'params' => $this->redactedParams($request),
        ]);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function redactedParams(WP_REST_Request $request): array
    {
        $params = $request->get_params();

        foreach (self::REDACTED_PARAMS as $field) {
            if (array_key_exists($field, $params)) {
                $params[$field] = '[redacted]';
            }
        }

        return $params;
    }
}
