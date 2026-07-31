<?php

declare(strict_types=1);

namespace Seviye\Core\Logging;

use Seviye\Core\Database\ConnectionInterface;

/**
 * Reads scp_logs for Http\ActivityLogRestController - a separate, minimal
 * adapter rather than extending DatabaseLogger (a PSR-3 *writer*, with no
 * business reading its own table back out). Mirrors
 * Seviye\Commerce\Repository\WpdbOrderLineItemQuery's shape.
 */
final class WpdbLogQuery
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    /**
     * @return list<LogEntry>
     */
    public function search(LogFilter $filter): array
    {
        $table = $this->connection->table('logs');
        $conditions = [];
        $args = [];

        if ($filter->channel !== null) {
            $conditions[] = 'channel = %s';
            $args[] = $filter->channel;
        }

        if ($filter->level !== null) {
            $conditions[] = 'level = %s';
            $args[] = $filter->level;
        }

        if ($filter->userId !== null) {
            $conditions[] = 'user_id = %d';
            $args[] = $filter->userId;
        }

        if ($filter->fromDate !== null) {
            $conditions[] = 'created_at >= %s';
            $args[] = $filter->fromDate . ' 00:00:00';
        }

        if ($filter->toDate !== null) {
            $conditions[] = 'created_at <= %s';
            $args[] = $filter->toDate . ' 23:59:59';
        }

        if ($filter->search !== null) {
            $conditions[] = 'message LIKE %s';
            $args[] = '%' . $this->escapeLikeWildcards($filter->search) . '%';
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $limit = max(1, $filter->limit);
        $sql = "SELECT * FROM {$table}{$where} ORDER BY created_at DESC, id DESC LIMIT {$limit}";

        if ($args !== []) {
            $sql = $this->connection->prepare($sql, $args);
        }

        return array_map($this->hydrate(...), $this->connection->getResults($sql));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): LogEntry
    {
        $userId = $row['user_id'] !== null ? (int) $row['user_id'] : null;
        $context = $row['context'] !== null ? json_decode((string) $row['context'], true) : null;

        return new LogEntry(
            (int) $row['id'],
            (string) $row['channel'],
            (string) $row['level'],
            (string) $row['message'],
            is_array($context) ? $context : null,
            $userId,
            $userId !== null ? $this->userName($userId) : null,
            $row['ip_address'] !== null ? (string) $row['ip_address'] : null,
            (string) $row['created_at']
        );
    }

    /**
     * MySQL LIKE treats `%`/`_` as wildcards and `\` as its escape
     * character - a search term containing any of these must have them
     * escaped before being wrapped in the surrounding `%…%` wildcards,
     * otherwise a literal "%" or "_" typed by the user would silently
     * widen/narrow the match instead of being searched for literally.
     */
    private function escapeLikeWildcards(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function userName(int $userId): ?string
    {
        if (!function_exists('get_userdata')) {
            return null;
        }

        $user = get_userdata($userId);

        return $user !== false ? $user->display_name : null;
    }
}
