<?php

declare(strict_types=1);

namespace Seviye\Security\Auth;

/**
 * "Kurum tarafından oluşturulan şifreyle ilk giriş yapılacak, giriş
 * yapıldıktan hemen sonra ilk şifresini oluştursun" - tracks which users
 * are currently logging in with a password someone ELSE chose for them
 * (an admin-set/reset password, or an auto-generated veli password) rather
 * than one they picked themselves. See
 * {@see \Seviye\Security\Database\Migrations\CreateMustChangePasswordFlagsTable}.
 */
interface MustChangePasswordGatewayInterface
{
    public function flag(int $userId): void;

    public function clear(int $userId): void;

    public function isFlagged(int $userId): bool;
}
