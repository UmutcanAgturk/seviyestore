<?php

declare(strict_types=1);

namespace Seviye\Students\Tests\Fakes;

use Seviye\Core\Database\ConnectionInterface;

final class FakeConnection implements ConnectionInterface
{
    /** @var list<array{0: string, 1: array<string, mixed>}> */
    public array $inserted = [];

    /** @var list<string> */
    public array $queries = [];

    /** @var list<array<string, mixed>> */
    public array $resultsToReturn = [];

    public int $nextInsertId = 1;

    public bool $insertShouldSucceed = true;

    public bool $queryShouldSucceed = true;

    public function table(string $suffix): string
    {
        return 'test_scp_' . $suffix;
    }

    public function charsetCollate(): string
    {
        return '';
    }

    public function dbDelta(string $sql): array
    {
        return [];
    }

    public function insert(string $table, array $data): bool
    {
        $this->inserted[] = [$table, $data];

        return $this->insertShouldSucceed;
    }

    public function lastInsertId(): int
    {
        return $this->nextInsertId;
    }

    public function query(string $sql): bool
    {
        $this->queries[] = $sql;

        return $this->queryShouldSucceed;
    }

    public function getResults(string $sql): array
    {
        return $this->resultsToReturn;
    }

    public function prepare(string $sql, array $args): string
    {
        // Test double only: real escaping is $wpdb->prepare()'s job (WpdbConnection, Core).
        return vsprintf($sql, array_map(static fn (mixed $arg): string => (string) $arg, $args));
    }
}
