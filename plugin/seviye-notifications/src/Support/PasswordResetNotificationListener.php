<?php

declare(strict_types=1);

namespace Seviye\Notifications\Support;

use Seviye\Core\Events\Event;
use Seviye\Notifications\Dispatch\NotificationDispatcherInterface;
use Seviye\Notifications\Domain\NotificationChannel;

/**
 * Listens for `security.password_reset_requested`, dispatched by
 * Seviye\Security\Http\AuthRestController::forgotPassword() - this module
 * never depends on Seviye Security's classes or Contracts, only on that
 * documented event name/payload shape (`user_id`, `token`, `purpose`),
 * exactly mirroring Finance's HakedisEventListener's relationship to
 * Commerce (see docs/ARCHITECTURE.md, bölüm 15). This is the delivery this
 * repo's own docs (root README.md, theme/README.md) have flagged as
 * "Planlandı (Seviye Notifications'ın sorumluluğu)" since the password
 * token system was first built.
 *
 * `purpose` is `'first_setup'` or `'reset'` (see
 * Seviye\Security\Support\PasswordTokenService) - only the subject line
 * differs; both land the recipient on the same `?scp_token=...` login-screen
 * flow (theme/inc/assets.php's scp_requested_password_token()).
 */
final class PasswordResetNotificationListener
{
    public function __construct(private readonly NotificationDispatcherInterface $dispatcher)
    {
    }

    public function onPasswordResetRequested(Event $event): void
    {
        $userId = (int) $event->get('user_id');
        $token = (string) $event->get('token');
        $purpose = (string) $event->get('purpose');

        $this->dispatcher->dispatch(
            $userId,
            NotificationChannel::EMAIL,
            $event->name(),
            $this->subjectFor($purpose),
            $this->bodyFor($token)
        );
    }

    /**
     * __()'s $text argument must stay a literal string (WordPress' own i18n
     * tooling parses call sites statically to build .pot files - a variable
     * argument, even behind a function_exists() guard, breaks that), so each
     * branch below repeats its own function_exists('__') guard rather than
     * routing through a shared helper that takes the string as a parameter.
     * This class's own tests run outside a WordPress runtime, where __() is
     * undefined - the guard falls back to the raw (Turkish) string.
     */
    private function subjectFor(string $purpose): string
    {
        if ($purpose === 'first_setup') {
            return function_exists('__')
                ? __('Seviye - İlk Şifre Oluşturma', 'seviye-notifications')
                : 'Seviye - İlk Şifre Oluşturma';
        }

        return function_exists('__')
            ? __('Seviye - Şifre Sıfırlama', 'seviye-notifications')
            : 'Seviye - Şifre Sıfırlama';
    }

    private function bodyFor(string $token): string
    {
        // Two short __() calls concatenated around the link, rather than
        // one long translatable string: __()'s argument must stay a single
        // literal (see subjectFor()'s docblock), and a Turkish sentence long
        // enough to be meaningful would not fit on one line within that
        // constraint.
        $intro = function_exists('__')
            ? __('Şifrenizi oluşturmak/sıfırlamak için bağlantıyı kullanın:', 'seviye-notifications')
            : 'Şifrenizi oluşturmak/sıfırlamak için bağlantıyı kullanın:';

        $outro = function_exists('__')
            ? __('Bu bağlantıyı siz talep etmediyseniz, bu e-postayı yok sayabilirsiniz.', 'seviye-notifications')
            : 'Bu bağlantıyı siz talep etmediyseniz, bu e-postayı yok sayabilirsiniz.';

        return $intro . "\n\n" . $this->resetLink($token) . "\n\n" . $outro;
    }

    private function resetLink(string $token): string
    {
        $base = function_exists('home_url') ? home_url('/') : '/';

        return function_exists('add_query_arg')
            ? add_query_arg('scp_token', $token, $base)
            : $base . '?scp_token=' . rawurlencode($token);
    }
}
