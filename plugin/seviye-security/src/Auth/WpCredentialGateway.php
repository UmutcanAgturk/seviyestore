<?php

declare(strict_types=1);

namespace Seviye\Security\Auth;

final class WpCredentialGateway implements CredentialGatewayInterface
{
    public function userExists(int $userId): bool
    {
        return get_userdata($userId) !== false;
    }

    public function verifyPassword(int $userId, string $password): bool
    {
        $user = get_userdata($userId);

        if ($user === false) {
            return false;
        }

        return wp_check_password($password, $user->user_pass, $userId);
    }
}
