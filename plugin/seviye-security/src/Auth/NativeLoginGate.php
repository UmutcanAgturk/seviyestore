<?php

declare(strict_types=1);

namespace Seviye\Security\Auth;

use Seviye\Security\TwoFactor\TwoFactorService;
use WP_Error;
use WP_User;

/**
 * Closes a gap the platform's own custom login flow (AuthRestController)
 * never covers: WordPress' NATIVE authentication path - wp-login.php's
 * form, xmlrpc.php, and Application Passwords Basic Auth - all resolve a
 * user through the `authenticate` filter chain and `wp_signon()`/
 * `wp_authenticate()`, a path this platform's own login never uses (see
 * AuthRestController::verify()/AccountRestController::changePassword(),
 * both of which call `wp_set_auth_cookie()`/`wp_set_current_user()`
 * DIRECTLY, never `wp_signon()`). Every account's password is a real,
 * fully valid WordPress password hash (`wp_set_password()` - see
 * AuthRestController's password-reset flow and UserListPage/
 * UserAuthorizationAdminPage's admin-set-password flows), so without this
 * gate, a user who enabled two-factor authentication on the platform's own
 * "Hesap Güvenliği" panel gets ZERO benefit from it: their password alone
 * is enough to fully authenticate via wp-login.php, which has no concept
 * of the platform's TOTP step at all.
 *
 * Deliberately narrower than blocking native login outright: WordPress'
 * native `administrator` role is an intentional coexistence path (see
 * SecurityModule::boot()'s `get_role('administrator')` grant, added so a
 * site's real WordPress administrator can reach "Seviye Kullanıcılar" too)
 * and the IP allowlist (inc/ip-restriction.php) is documented as
 * intentionally scoped to page-rendering only, not an authentication
 * boundary - this gate does not relitigate either of those. It closes
 * specifically the case the rest of the codebase never addresses at all:
 * an account that opted into two-factor MUST NOT be able to fully
 * authenticate through a path that cannot ask for the second factor.
 */
final class NativeLoginGate
{
    public function __construct(private readonly TwoFactorService $twoFactorService)
    {
    }

    public function register(): void
    {
        // Priority 30: WordPress' own `wp_authenticate_username_password`/
        // `wp_authenticate_email_password`/`wp_authenticate_application_password`
        // all run at priority 20 and resolve $user to either a WP_User (valid
        // credentials) or a WP_Error (invalid) - this only needs to act once
        // that outcome is already known.
        add_filter('authenticate', [$this, 'rejectTwoFactorAccounts'], 30);
    }

    /**
     * @param WP_User|WP_Error|null $user
     * @return WP_User|WP_Error|null
     */
    public function rejectTwoFactorAccounts($user)
    {
        if (!$user instanceof WP_User) {
            return $user;
        }

        if (!$this->twoFactorService->isEnabledForUser($user->ID)) {
            return $user;
        }

        return new WP_Error(
            'scp_native_login_blocked',
            __(
                'Bu hesapta iki adımlı doğrulama etkin. Lütfen platformun kendi giriş ekranını kullanın.',
                'seviye-security'
            )
        );
    }
}
