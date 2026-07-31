<?php

declare(strict_types=1);

namespace Seviye\Students\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Seviye\Students\Domain\ParentRelationship;
use Seviye\Students\Repository\WpdbStudentParentRepository;
use Seviye\Students\Tests\Fakes\FakeConnection;

final class WpdbStudentParentRepositoryTest extends TestCase
{
    public function testLinkInsertsWhenNoExistingLinkExists(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbStudentParentRepository($connection);

        $repository->link(1, 42, ParentRelationship::MOTHER);

        self::assertCount(1, $connection->inserted);
        self::assertSame(1, $connection->inserted[0][1]['student_id']);
        self::assertSame(42, $connection->inserted[0][1]['parent_user_id']);
        self::assertSame('anne', $connection->inserted[0][1]['relationship_type']);
        self::assertCount(0, $connection->queries);
    }

    public function testLinkThrowsWithTheRealDbErrorWhenInsertFails(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $connection->insertShouldSucceed = false;
        $repository = new WpdbStudentParentRepository($connection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Öğrenci #1 - veli #42 bağlantısı kaydedilemedi:');

        $repository->link(1, 42, ParentRelationship::MOTHER);
    }

    public function testLinkUpdatesTheRelationshipWhenAlreadyLinked(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['id' => '9']];
        $repository = new WpdbStudentParentRepository($connection);

        $repository->link(1, 42, ParentRelationship::GUARDIAN);

        self::assertCount(0, $connection->inserted);
        self::assertCount(1, $connection->queries);
    }

    public function testUnlinkIssuesADeleteQuery(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbStudentParentRepository($connection);

        $repository->unlink(1, 42);

        self::assertCount(1, $connection->queries);
    }

    public function testParentUserIdsForStudentMapsRowsToIntegers(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['parent_user_id' => '42'], ['parent_user_id' => '43']];
        $repository = new WpdbStudentParentRepository($connection);

        self::assertSame([42, 43], $repository->parentUserIdsForStudent(1));
    }

    public function testStudentIdsForParentMapsRowsToIntegers(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['student_id' => '1'], ['student_id' => '2']];
        $repository = new WpdbStudentParentRepository($connection);

        self::assertSame([1, 2], $repository->studentIdsForParent(42));
    }
}
