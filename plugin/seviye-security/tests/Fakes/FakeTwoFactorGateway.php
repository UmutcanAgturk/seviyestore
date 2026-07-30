<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Fakes;

use Seviye\Security\TwoFactor\TwoFactorGatewayInterface;
use Seviye\Security\TwoFactor\TwoFactorSecret;

final class FakeTwoFactorGateway implements TwoFactorGatewayInterface
{
    /** @var array<int, array{secret: string, confirmed: bool}> */
    private array $records = [];

    public function find(int $userId): ?TwoFactorSecret
    {
        if (!isset($this->records[$userId])) {
            return null;
        }

        return new TwoFactorSecret($userId, $this->records[$userId]['secret'], $this->records[$userId]['confirmed']);
    }

    public function store(int $userId, string $encryptedSecret): void
    {
        $this->records[$userId] = ['secret' => $encryptedSecret, 'confirmed' => false];
    }

    public function confirm(int $userId): void
    {
        if (isset($this->records[$userId])) {
            $this->records[$userId]['confirmed'] = true;
        }
    }

    public function delete(int $userId): void
    {
        unset($this->records[$userId]);
    }
}
