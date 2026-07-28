<?php

declare(strict_types=1);

namespace Seviye\Core\Security;

/**
 * Resolves the requesting client's IP address for audit logging and rate
 * limiting purposes.
 *
 * X-Forwarded-For is never trusted by default because it is trivially
 * spoofable by the client; it is only honoured when the deployment
 * explicitly defines SCP_TRUST_PROXY as true (e.g. behind a known,
 * correctly configured reverse proxy/load balancer).
 */
final class ClientIp
{
    public static function resolve(): ?string
    {
        if (defined('SCP_TRUST_PROXY') && SCP_TRUST_PROXY === true && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwardedFor = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $candidate = trim($forwardedFor[0]);
            $validated = filter_var($candidate, FILTER_VALIDATE_IP);

            if ($validated !== false) {
                return $validated;
            }
        }

        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $validated = filter_var($remoteAddr, FILTER_VALIDATE_IP);

        return $validated === false ? null : $validated;
    }
}
