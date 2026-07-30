<?php

declare(strict_types=1);

namespace Seviye\Api\Http;

use Seviye\Api\Auth\ApiKeyAuthenticator;
use Seviye\Core\Security\ClientIp;
use WP_Error;

/**
 * Thin WordPress adapter - not unit tested, same as every other direct
 * WP-hook adapter in this codebase (see docs/ARCHITECTURE.md, "Test
 * stratejisi"). All real logic lives in {@see ApiKeyAuthenticator}.
 *
 * `rest_authentication_errors` is WordPress core's own extension point for
 * REST authentication methods (the same filter `rest_cookie_check_errors()`
 * uses for cookie+nonce auth) - the first use of it in this platform.
 * Follows WP core's own idiom exactly: `if (!empty($result)) return
 * $result;` at the top, so this never overrides an authentication outcome
 * (success or failure) a different method already produced, REGARDLESS of
 * which filter callback happens to run first - the two guards make the
 * ordering irrelevant. When no `Authorization: Bearer <key>` header is
 * present at all, `$result` is returned untouched, so a plain browser
 * request with no such header falls through to cookie+nonce auth exactly
 * as it always has - this endpoint never breaks the existing panels.
 */
final class ApiKeyAuthHook
{
    public function __construct(private readonly ApiKeyAuthenticator $authenticator)
    {
    }

    public function register(): void
    {
        add_filter('rest_authentication_errors', [$this, 'authenticate']);
    }

    public function authenticate(mixed $result): mixed
    {
        if (!empty($result)) {
            return $result;
        }

        $rawKey = $this->bearerToken();

        if ($rawKey === null) {
            return $result;
        }

        $outcome = $this->authenticator->authenticate($rawKey, ClientIp::resolve() ?? '');

        if ($outcome->isThrottled()) {
            return new WP_Error(
                'scp_api_key_throttled',
                __('Too many invalid API key attempts. Try again later.', 'seviye-api'),
                ['status' => 429]
            );
        }

        if (!$outcome->isSuccessful()) {
            return new WP_Error(
                'scp_api_key_invalid',
                __('Invalid API key.', 'seviye-api'),
                ['status' => 401]
            );
        }

        wp_set_current_user((int) $outcome->userId());

        return true;
    }

    private function bearerToken(): ?string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- not a form submission; this authenticates the request itself (verified against scp_api_keys by ApiKeyAuthenticator, not trusted here).
        $header = isset($_SERVER['HTTP_AUTHORIZATION']) ? (string) $_SERVER['HTTP_AUTHORIZATION'] : '';

        if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            // Some hosts (mod_php/CGI/FastCGI) strip the Authorization
            // header from $_SERVER but expose it under this alternate key -
            // the same fallback WordPress core's own Application Passwords
            // feature uses.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
            $header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return trim(substr($header, 7));
    }
}
