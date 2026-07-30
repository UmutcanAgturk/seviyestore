<?php

declare(strict_types=1);

namespace Seviye\Security\TwoFactor;

/**
 * Orchestrates setup/confirm/disable/verify against the gateway + Totp +
 * Encryptor - the one place that knows how those three fit together, kept
 * free of any WordPress call (userId/accountLabel arrive as primitives from
 * the REST layer, the same boundary AuthService already draws).
 */
final class TwoFactorService
{
    private const ISSUER = 'Seviye Commerce Platform';

    public function __construct(
        private readonly TwoFactorGatewayInterface $gateway,
        private readonly Encryptor $encryptor
    ) {
    }

    public function isEnabledForUser(int $userId): bool
    {
        return $this->gateway->find($userId)?->confirmed ?? false;
    }

    public function beginSetup(int $userId, string $accountLabel): TwoFactorSetup
    {
        $secret = Base32::randomSecret();
        $this->gateway->store($userId, $this->encryptor->encrypt($secret));

        return new TwoFactorSetup($secret, $this->otpauthUri($secret, $accountLabel));
    }

    public function confirmSetup(int $userId, string $code): bool
    {
        $secret = $this->decryptedSecretFor($userId);

        if ($secret === null || !Totp::verify($secret, $code)) {
            return false;
        }

        $this->gateway->confirm($userId);

        return true;
    }

    public function disable(int $userId): void
    {
        $this->gateway->delete($userId);
    }

    public function verifyCode(int $userId, string $code): bool
    {
        $record = $this->gateway->find($userId);

        if ($record === null || !$record->confirmed) {
            return false;
        }

        $secret = $this->encryptor->decrypt($record->encryptedSecret);

        return $secret !== null && Totp::verify($secret, $code);
    }

    private function decryptedSecretFor(int $userId): ?string
    {
        $record = $this->gateway->find($userId);

        return $record !== null ? $this->encryptor->decrypt($record->encryptedSecret) : null;
    }

    private function otpauthUri(string $secret, string $accountLabel): string
    {
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode(self::ISSUER . ':' . $accountLabel),
            $secret,
            rawurlencode(self::ISSUER)
        );
    }
}
