<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Fakes;

use Seviye\Parents\Contracts\ParentContactLookupInterface;

final class FakeParentContactLookup implements ParentContactLookupInterface
{
    /** @var array<int, string> */
    public array $phones = [];

    public function phoneFor(int $userId): ?string
    {
        return $this->phones[$userId] ?? null;
    }
}
