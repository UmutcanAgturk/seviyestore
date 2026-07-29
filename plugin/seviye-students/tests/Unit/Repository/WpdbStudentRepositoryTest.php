<?php

declare(strict_types=1);

namespace Seviye\Students\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Students\Domain\EducationYear;
use Seviye\Students\Domain\StudentStatus;
use Seviye\Students\Repository\WpdbStudentRepository;
use Seviye\Students\Tests\Fakes\FakeConnection;

final class WpdbStudentRepositoryTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function studentRow(): array
    {
        return [
            'id' => '1',
            'branch_id' => '7',
            'first_name' => 'Ahmet',
            'last_name' => 'Yılmaz',
            'education_year' => '2025-2026',
            'class_name' => '5-A',
            'status' => 'active',
        ];
    }

    public function testCreateInsertsAndReadsBackTheStudentByLastInsertId(): void
    {
        $connection = new FakeConnection();
        $connection->nextInsertId = 1;
        $connection->resultsToReturn = [$this->studentRow()];
        $repository = new WpdbStudentRepository($connection);

        $student = $repository->create(7, 'Ahmet', 'Yılmaz', EducationYear::fromString('2025-2026'), '5-A');

        self::assertSame(1, $student->id);
        self::assertSame(7, $student->branchId);
        self::assertSame('Ahmet Yılmaz', $student->fullName());
        self::assertCount(1, $connection->inserted);
        self::assertSame(7, $connection->inserted[0][1]['branch_id']);
    }

    public function testFindReturnsNullWhenNoRowMatches(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbStudentRepository($connection);

        self::assertNull($repository->find(999));
    }

    public function testFindByBranchHydratesEveryReturnedRow(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->studentRow(), [
            'id' => '2',
            'branch_id' => '7',
            'first_name' => 'Zeynep',
            'last_name' => 'Demir',
            'education_year' => '2025-2026',
            'class_name' => '5-A',
            'status' => 'active',
        ]];
        $repository = new WpdbStudentRepository($connection);

        $students = $repository->findByBranch(7);

        self::assertCount(2, $students);
        self::assertSame('Ahmet', $students[0]->firstName);
        self::assertSame('Zeynep', $students[1]->firstName);
    }

    public function testUpdatePersistsAndReadsBackTheStudent(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [array_merge($this->studentRow(), ['class_name' => '6-A'])];
        $repository = new WpdbStudentRepository($connection);

        $student = $repository->update(
            1,
            7,
            'Ahmet',
            'Yılmaz',
            EducationYear::fromString('2025-2026'),
            '6-A',
            StudentStatus::ACTIVE
        );

        self::assertSame('6-A', $student->className);
        self::assertCount(1, $connection->queries);
    }
}
