<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Fakes;

use Seviye\Security\Auth\TcNumber;
use Seviye\Security\Identity\IdentityGatewayInterface;

final class FakeIdentityGateway implements IdentityGatewayInterface
{
    /** @var array<string, int> */
    private array $links = [];

    public function findUserIdByTcNumber(TcNumber $tcNumber): ?int
    {
        return $this->links[$tcNumber->value()] ?? null;
    }

    public function tcNumberExists(TcNumber $tcNumber): bool
    {
        return isset($this->links[$tcNumber->value()]);
    }

    public function link(TcNumber $tcNumber, int $userId): void
    {
        $this->links[$tcNumber->value()] = $userId;
    }
}
