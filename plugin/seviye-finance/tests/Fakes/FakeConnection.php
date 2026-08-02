<?php

declare(strict_types=1);

namespace Seviye\Finance\Tests\Fakes;

use Seviye\Core\Database\ConnectionInterface;

final class FakeConnection implements ConnectionInterface
{
    /** @var list<array{0: string, 1: array<string, mixed>}> */
    public array $inserted = [];

    /** @var list<string> */
    public array $queries = [];

    /** @var list<array<string, mixed>> */
    public array $resultsToReturn = [];

    /**
     * Optional per-call override queue: each getResults() call shifts one
     * entry off this queue (in order) before falling back to the shared
     * resultsToReturn fixture - lets a test give two sequential
     * getResults() calls within the same method (e.g. WpdbHakedisTotals::sum()
     * called twice) two DIFFERENT result sets, which resultsToReturn alone
     * cannot express since it is one fixture shared by every call.
     *
     * @var list<list<array<string, mixed>>>
     */
    public array $resultsQueue = [];

    public int $nextInsertId = 1;

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

        return true;
    }

    public function lastInsertId(): int
    {
        return $this->nextInsertId;
    }

    public function query(string $sql): bool
    {
        $this->queries[] = $sql;

        return true;
    }

    public function getResults(string $sql): array
    {
        if ($this->resultsQueue !== []) {
            return array_shift($this->resultsQueue);
        }

        return $this->resultsToReturn;
    }

    public function prepare(string $sql, array $args): string
    {
        // Test double only: real escaping is $wpdb->prepare()'s job (WpdbConnection, Core).
        return vsprintf($sql, array_map(static fn (mixed $arg): string => (string) $arg, $args));
    }
}
