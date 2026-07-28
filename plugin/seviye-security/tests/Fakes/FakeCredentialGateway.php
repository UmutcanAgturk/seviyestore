<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Fakes;

use Seviye\Security\Auth\CredentialGatewayInterface;

final class FakeCredentialGateway implements CredentialGatewayInterface
{
    /** @var array<int, string> */
    private array $passwordsByUserId = [];

    public function withPassword(int $userId, string $password): self
    {
        $this->passwordsByUserId[$userId] = $password;

        return $this;
    }

    public function userExists(int $userId): bool
    {
        return isset($this->passwordsByUserId[$userId]);
    }

    public function verifyPassword(int $userId, string $password): bool
    {
        return ($this->passwordsByUserId[$userId] ?? null) === $password;
    }
}
