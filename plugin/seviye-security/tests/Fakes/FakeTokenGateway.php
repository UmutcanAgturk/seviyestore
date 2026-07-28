<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Fakes;

use DateTimeImmutable;
use Seviye\Security\Token\PasswordTokenGatewayInterface;
use Seviye\Security\Token\PasswordTokenPurpose;
use Seviye\Security\Token\PasswordTokenRecord;

final class FakeTokenGateway implements PasswordTokenGatewayInterface
{
    /** @var array<string, PasswordTokenRecord> */
    private array $records = [];

    public function store(int $userId, string $tokenHash, PasswordTokenPurpose $purpose, DateTimeImmutable $expiresAt): void
    {
        $this->records[$tokenHash] = new PasswordTokenRecord($userId, $purpose, $expiresAt);
    }

    public function find(string $tokenHash): ?PasswordTokenRecord
    {
        return $this->records[$tokenHash] ?? null;
    }

    public function consume(string $tokenHash): void
    {
        unset($this->records[$tokenHash]);
    }
}
