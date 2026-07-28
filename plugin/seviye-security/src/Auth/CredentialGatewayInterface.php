<?php

declare(strict_types=1);

namespace Seviye\Security\Auth;

/**
 * Port around WordPress' own password hashing/verification, so AuthService
 * never touches $wpdb or wp_check_password() directly.
 */
interface CredentialGatewayInterface
{
    public function userExists(int $userId): bool;

    public function verifyPassword(int $userId, string $password): bool;
}
