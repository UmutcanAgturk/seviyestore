<?php

declare(strict_types=1);

namespace Seviye\Students\Repository;

use Seviye\Students\Domain\ParentRelationship;

interface StudentParentRepositoryInterface
{
    public function link(int $studentId, int $parentUserId, ParentRelationship $relationship): void;

    public function unlink(int $studentId, int $parentUserId): void;

    /**
     * @return list<int>
     */
    public function parentUserIdsForStudent(int $studentId): array;

    /**
     * @return list<int>
     */
    public function studentIdsForParent(int $parentUserId): array;
}
