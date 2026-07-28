<?php

declare(strict_types=1);

namespace Seviye\Security\Identity;

use Seviye\Security\Auth\TcNumber;

/**
 * Maps a {@see TcNumber} to a WordPress user ID. Kept as its own table
 * (scp_user_identities) rather than user meta so lookups on login are a
 * single indexed equality query instead of an unindexed wp_usermeta scan.
 */
interface IdentityGatewayInterface
{
    public function findUserIdByTcNumber(TcNumber $tcNumber): ?int;

    public function tcNumberExists(TcNumber $tcNumber): bool;

    public function link(TcNumber $tcNumber, int $userId): void;
}
