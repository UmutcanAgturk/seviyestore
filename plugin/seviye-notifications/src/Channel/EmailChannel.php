<?php

declare(strict_types=1);

namespace Seviye\Notifications\Channel;

use Seviye\Core\Http\BrandingRestController;
use Seviye\Core\Plugin;
use Seviye\Core\Settings\SettingsRepositoryInterface;

/**
 * Thin wp_mail() wrapper - not unit tested, same as
 * {@see \Seviye\Commerce\Http\WooCommerceCartHooks} and every other direct
 * WordPress/WooCommerce function adapter in this codebase (see
 * docs/ARCHITECTURE.md, "Test stratejisi"). Requires no new Composer
 * dependency or external credentials: wp_mail() is WordPress core, routed
 * through whatever mail transport (PHP mail(), an SMTP plugin, etc.) the
 * site is already configured with - the same "avoid a heavy dependency,
 * use what's already real" precedent as the 2FA TOTP/Reports XLSX work.
 *
 * "E-posta bildirimleri hâlâ düz metin" - every caller (order-placed,
 * password reset, broadcast, weekly summary, support tickets, ...) still
 * builds a PLAIN TEXT `$body` and passes it here unchanged; wp_mail()
 * defaults to text/plain unless told otherwise. Rather than touching every
 * one of those listeners, the HTML wrapping happens ONCE, centrally, here -
 * every notification automatically gets the same branded template with no
 * per-listener changes. The `$body` contract itself (plain text) is
 * unchanged: {@see \Seviye\Notifications\Dispatch\NotificationDispatcher}
 * still records the ORIGINAL plain-text body for the in-app panel/history
 * (it calls `record()` before `send()`, using its own copy of `$body`) -
 * only what actually goes out over SMTP is transformed.
 */
final class EmailChannel implements ChannelInterface
{
    private const ACCENT_COLOR = '#0f9d63';

    public function send(string $recipient, string $subject, string $body): bool
    {
        if ($recipient === '' || !function_exists('wp_mail')) {
            return false;
        }

        return (bool) wp_mail($recipient, $subject, $this->renderHtml($body), [
            'Content-Type: text/html; charset=UTF-8',
        ]);
    }

    /**
     * Built via string concatenation (not an echo'd `?>`/`<?php` template
     * block) specifically so every dynamic value is escaped at the exact
     * point it's inserted, with no separate "trust me, bodyToHtml() already
     * escaped it" annotation needed for the one genuinely pre-escaped piece
     * ($headerMark/$bodyHtml).
     */
    private function renderHtml(string $body): string
    {
        $siteName = function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '';
        $logoUrl = $this->logoUrl();
        $font = 'font-family:Arial,Helvetica,sans-serif;';

        $headerMark = $logoUrl !== null
            ? sprintf(
                '<img src="%1$s" alt="%2$s" height="32" style="height:32px;max-width:200px;">',
                esc_url($logoUrl),
                esc_attr($siteName)
            )
            : sprintf(
                '<span style="color:#ffffff;font-size:18px;font-weight:700;%1$s">%2$s</span>',
                $font,
                esc_html($siteName)
            );

        return '<!doctype html><html><body style="margin:0;padding:0;background:#f4f5f7;' . $font . '">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
            . 'style="background:#f4f5f7;padding:32px 0;"><tr><td align="center">'
            . '<table role="presentation" width="480" cellpadding="0" cellspacing="0" '
            . 'style="width:480px;max-width:92%;background:#ffffff;border-radius:12px;overflow:hidden;">'
            . '<tr><td style="background:' . esc_attr(self::ACCENT_COLOR) . ';padding:20px 32px;text-align:center;">'
            . $headerMark
            . '</td></tr>'
            . '<tr><td style="padding:32px;color:#1f2937;font-size:15px;line-height:1.6;' . $font . '">'
            . $this->bodyToHtml($body)
            . '</td></tr>'
            . '<tr><td style="padding:16px 32px;background:#f9fafb;color:#9ca3af;font-size:12px;'
            . 'text-align:center;' . $font . '">'
            . esc_html($siteName)
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /**
     * A paragraph (blank-line-separated) that is nothing but a bare URL -
     * every token-link body in this codebase (password reset, first-login
     * gate, ...) builds its link this exact way - renders as a styled
     * button instead of plain text; everything else becomes a normal
     * paragraph, with single newlines inside it kept as `<br>`.
     */
    private function bodyToHtml(string $body): string
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($body)) ?: [];
        $html = '';

        foreach ($paragraphs as $paragraph) {
            $trimmed = trim($paragraph);

            if ($trimmed === '') {
                continue;
            }

            if (filter_var($trimmed, FILTER_VALIDATE_URL) !== false) {
                $buttonStyle = 'background:%2$s;color:#ffffff;text-decoration:none;padding:12px 28px;'
                    . 'border-radius:999px;display:inline-block;font-weight:600;'
                    . 'font-family:Arial,Helvetica,sans-serif;';

                $buttonMarkup = '<p style="text-align:center;margin:24px 0;">'
                    . '<a href="%1$s" style="' . $buttonStyle . '">%3$s</a></p>';

                $html .= sprintf(
                    $buttonMarkup,
                    esc_url($trimmed),
                    esc_attr(self::ACCENT_COLOR),
                    esc_html__('Devam Et', 'seviye-notifications')
                );

                continue;
            }

            $html .= '<p style="margin:0 0 16px;">' . nl2br(esc_html($trimmed)) . '</p>';
        }

        return $html;
    }

    /**
     * Same `branding_logo_attachment_id` setting the theme's own
     * scp_logo_url() reads (inc/branding.php) - this plugin already
     * depends on seviye/core, so resolving Core's own container directly
     * is safe and avoids depending on the theme (a plugin should never
     * reach into theme code). Null (no logo uploaded, or Core/WP not
     * bootstrapped - e.g. this class' own tests) falls back to the site
     * name as plain text.
     */
    private function logoUrl(): ?string
    {
        if (!class_exists(Plugin::class) || !function_exists('wp_get_attachment_image_url')) {
            return null;
        }

        $settings = Plugin::instance()->container()->get(SettingsRepositoryInterface::class);
        $attachmentId = (int) $settings->get(BrandingRestController::LOGO_ATTACHMENT_ID_KEY);

        if ($attachmentId <= 0) {
            return null;
        }

        $url = wp_get_attachment_image_url($attachmentId, 'medium');

        return $url !== false ? $url : null;
    }
}
