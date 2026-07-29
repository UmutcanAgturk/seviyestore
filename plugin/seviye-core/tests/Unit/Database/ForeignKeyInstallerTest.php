<?php

declare(strict_types=1);

namespace Seviye\Core\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Tests\Fakes\FakeConnection;

final class ForeignKeyInstallerTest extends TestCase
{
    public function testAddsTheConstraintWhenItDoesNotYetExist(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];

        ForeignKeyInstaller::ensure(
            $connection,
            'wp_scp_students',
            'wp_scp_students_branch_id_fk',
            'FOREIGN KEY (branch_id) REFERENCES wp_scp_branches (id)'
        );

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('wp_scp_students_branch_id_fk', $connection->queries[0]);
    }

    public function testDoesNothingWhenTheConstraintAlreadyExists(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['CONSTRAINT_NAME' => 'wp_scp_students_branch_id_fk']];

        ForeignKeyInstaller::ensure(
            $connection,
            'wp_scp_students',
            'wp_scp_students_branch_id_fk',
            'FOREIGN KEY (branch_id) REFERENCES wp_scp_branches (id)'
        );

        self::assertCount(0, $connection->queries);
    }
}
