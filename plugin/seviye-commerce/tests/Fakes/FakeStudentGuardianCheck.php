<?php

declare(strict_types=1);

namespace Seviye\Commerce\Tests\Fakes;

use Seviye\Students\Contracts\StudentGuardianCheckInterface;

final class FakeStudentGuardianCheck implements StudentGuardianCheckInterface
{
    /** @var array<string, bool> */
    public array $links = [];

    public function put(int $parentUserId, int $studentId): void
    {
        $this->links[$parentUserId . ':' . $studentId] = true;
    }

    public function isGuardianOf(int $parentUserId, int $studentId): bool
    {
        return $this->links[$parentUserId . ':' . $studentId] ?? false;
    }
}
