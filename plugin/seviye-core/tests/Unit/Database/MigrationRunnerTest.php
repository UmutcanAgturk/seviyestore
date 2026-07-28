<?php

declare(strict_types=1);

namespace Seviye\Core\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Tests\Fakes\FakeConnection;

final class MigrationRunnerTest extends TestCase
{
    public function testRunExecutesOnlyPendingMigrationsInVersionOrder(): void
    {
        $connection = new FakeConnection();
        $runner = new MigrationRunner($connection, new NullLogger());
        $order = [];

        $runner->register($this->fakeMigration('2026_01_02', $order));
        $runner->register($this->fakeMigration('2026_01_01', $order));

        $executed = $runner->run();

        self::assertSame(['2026_01_01', '2026_01_02'], $executed);
        self::assertSame(['2026_01_01', '2026_01_02'], $order);
        self::assertCount(2, $connection->inserted);
    }

    public function testRunSkipsAlreadyAppliedMigrations(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [
            ['version' => '2026_01_01'],
        ];

        $runner = new MigrationRunner($connection, new NullLogger());
        $order = [];

        $runner->register($this->fakeMigration('2026_01_01', $order));

        $executed = $runner->run();

        self::assertSame([], $executed);
        self::assertSame([], $order);
        self::assertCount(0, $connection->inserted);
    }

    /**
     * @param list<string> $order
     */
    private function fakeMigration(string $version, array &$order): MigrationInterface
    {
        return new class ($version, $order) implements MigrationInterface {
            /**
             * @param list<string> $order
             */
            public function __construct(
                private readonly string $version,
                private array &$order
            ) {
            }

            public function version(): string
            {
                return $this->version;
            }

            public function description(): string
            {
                return 'Fake migration ' . $this->version;
            }

            public function up(ConnectionInterface $connection): void
            {
                $this->order[] = $this->version;
            }

            public function down(ConnectionInterface $connection): void
            {
            }
        };
    }
}
