<?php

declare(strict_types=1);

namespace Seviye\Core\Logging;

use Psr\Log\AbstractLogger;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Security\ClientIp;
use Stringable;

/**
 * PSR-3 logger that persists every entry into scp_logs, the platform's
 * central audit trail (KVKK/security audit requirement).
 */
final class DatabaseLogger extends AbstractLogger
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->connection->insert($this->connection->table('logs'), [
            'channel' => (string) ($context['channel'] ?? 'core'),
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context === [] ? null : wp_json_encode($context),
            'user_id' => function_exists('get_current_user_id') ? (get_current_user_id() ?: null) : null,
            'ip_address' => ClientIp::resolve(),
            'created_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        ]);
    }
}
