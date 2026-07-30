<?php

declare(strict_types=1);

namespace Seviye\Notifications\Channel;

/**
 * Thin wp_mail() wrapper - not unit tested, same as
 * {@see \Seviye\Commerce\Http\WooCommerceCartHooks} and every other direct
 * WordPress/WooCommerce function adapter in this codebase (see
 * docs/ARCHITECTURE.md, "Test stratejisi"). Requires no new Composer
 * dependency or external credentials: wp_mail() is WordPress core, routed
 * through whatever mail transport (PHP mail(), an SMTP plugin, etc.) the
 * site is already configured with - the same "avoid a heavy dependency,
 * use what's already real" precedent as the 2FA TOTP/Reports XLSX work.
 */
final class EmailChannel implements ChannelInterface
{
    public function send(string $recipient, string $subject, string $body): bool
    {
        if ($recipient === '' || !function_exists('wp_mail')) {
            return false;
        }

        return (bool) wp_mail($recipient, $subject, $body);
    }
}
