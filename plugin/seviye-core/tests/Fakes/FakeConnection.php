<?php

declare(strict_types=1);

namespace Seviye\Core\Tests\Fakes;

use Seviye\Core\Database\ConnectionInterface;

final class FakeConnection implements ConnectionInterface
{
    /** @var list<array{0: string, 1: array<string, mixed>}> */
    public array $inserted = [];

    /** @var list<array<string, mixed>> */
    public array $resultsToReturn = [];

    /** @var list<string> */
    public array $dbDeltaCalls = [];

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
        $this->dbDeltaCalls[] = $sql;

        return [];
    }

    public function insert(string $table, array $data): bool
    {
        $this->inserted[] = [$table, $data];

        return true;
    }

    public function query(string $sql): bool
    {
        return true;
    }

    public function getResults(string $sql): array
    {
        return $this->resultsToReturn;
    }

    public function prepare(string $sql, array $args): string
    {
        // Test double only: real escaping is $wpdb->prepare()'s job (WpdbConnection).
        return vsprintf($sql, $args);
    }
}
