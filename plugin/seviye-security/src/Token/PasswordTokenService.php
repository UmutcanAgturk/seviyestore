<?php

declare(strict_types=1);

namespace Seviye\Security\Token;

/**
 * Issues and redeems single-use password tokens for "Şifremi Unuttum" and
 * "İlk Şifre Oluştur". The raw token is a cryptographically random value
 * returned only once, at issue time, for the caller to deliver (by e-mail or
 * SMS - the Seviye Notifications module's responsibility, not this one's).
 */
final class PasswordTokenService
{
    private const TOKEN_BYTES = 32;
    private const TTL_SECONDS = 3600;

    public function __construct(
        private readonly PasswordTokenGatewayInterface $gateway,
        private readonly ClockInterface $clock
    ) {
    }

    public function issue(int $userId, PasswordTokenPurpose $purpose): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $expiresAt = $this->clock->now()->modify('+' . self::TTL_SECONDS . ' seconds');

        $this->gateway->store($userId, $this->hash($token), $purpose, $expiresAt);

        return $token;
    }

    /**
     * Consumes the token unconditionally (single-use, even if expired) and
     * returns the record only when it was still valid at the time of
     * redemption.
     */
    public function redeem(string $token): ?PasswordTokenRecord
    {
        $tokenHash = $this->hash($token);
        $record = $this->gateway->find($tokenHash);

        if ($record === null) {
            return null;
        }

        $this->gateway->consume($tokenHash);

        if ($record->expiresAt < $this->clock->now()) {
            return null;
        }

        return $record;
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
