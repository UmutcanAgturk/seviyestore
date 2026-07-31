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

    public function testRunReExecutesAlreadyAppliedMigrationsButDoesNotRecordAgain(): void
    {
        // up() is dbDelta() everywhere in this codebase, itself idempotent -
        // re-running an already-applied migration on every call is what
        // self-heals a table lost or never actually created despite being
        // recorded as applied (dbDelta never throws on failure). Only the
        // scp_migrations bookkeeping (the audit-log insert, and the
        // "newly applied" return value) is skipped for a version already on
        // record.
        $connection = new FakeConnection();
        $connection->resultsToReturn = [
            ['version' => '2026_01_01'],
        ];

        $runner = new MigrationRunner($connection, new NullLogger());
        $order = [];

        $runner->register($this->fakeMigration('2026_01_01', $order));

        $executed = $runner->run();

        self::assertSame([], $executed);
        self::assertSame(['2026_01_01'], $order);
        self::assertCount(0, $connection->inserted);
    }

    public function testRunExecutesBothMigrationsWhenTwoDifferentModulesReuseTheSameVersionString(): void
    {
        // version() strings are only unique WITHIN one module - every
        // module numbers its own migrations independently, so two
        // completely unrelated migrations from different modules can
        // legitimately share the exact same date-based label (see
        // MigrationRunner's class docblock, "Dördüncü kural"). Before the
        // registry was keyed on version()+class name, the second register()
        // call here would have silently evicted the first from the array
        // and its up() would never run.
        $connection = new FakeConnection();
        $runner = new MigrationRunner($connection, new NullLogger());
        $order = [];

        $runner->register($this->fakeMigration('2026_01_01', $order));
        $runner->register($this->fakeMigrationOfAnotherClass('2026_01_01', $order));

        $runner->run();

        self::assertCount(2, $order);
        self::assertContains('2026_01_01', $order);
        self::assertContains('2026_01_01-other', $order);
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

    /**
     * A second, distinct anonymous class (PHP identifies anonymous classes
     * by their declaration's source location, so this needs its own literal
     * rather than reusing {@see fakeMigration()}) standing in for a
     * different module's migration class that happens to reuse the same
     * version() string.
     *
     * @param list<string> $order
     */
    private function fakeMigrationOfAnotherClass(string $version, array &$order): MigrationInterface
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
                return 'Fake migration (other module) ' . $this->version;
            }

            public function up(ConnectionInterface $connection): void
            {
                $this->order[] = $this->version . '-other';
            }

            public function down(ConnectionInterface $connection): void
            {
            }
        };
    }
}
