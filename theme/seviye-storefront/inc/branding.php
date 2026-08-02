<?php

/**
 * The platform's own uploaded logo (Seviye Core, scp_settings key
 * branding_logo_attachment_id - configured via the /admin "Görünüm" panel,
 * Genel Merkez only). Same container-access pattern as
 * inc/ip-restriction.php's IpAllowlist lookup.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 0 when no logo has been uploaded yet. Split out of scp_logo_url() so
 * inc/pwa.php can reuse the same lookup (it needs the attachment id itself,
 * to read real pixel dimensions via wp_get_attachment_image_src() - a
 * manifest icon's declared "sizes" must match the actual image).
 */
function scp_logo_attachment_id(): int
{
    if (!class_exists(\Seviye\Core\Plugin::class)) {
        return 0;
    }

    $settings = \Seviye\Core\Plugin::instance()->container()->get(
        \Seviye\Core\Settings\SettingsRepositoryInterface::class
    );

    return (int) $settings->get(\Seviye\Core\Http\BrandingRestController::LOGO_ATTACHMENT_ID_KEY);
}

/**
 * Null when no logo has been uploaded yet - callers fall back to the
 * letter-badge mark (see header.php/templates/login.php) themselves.
 */
function scp_logo_url(string $size = 'medium'): ?string
{
    $attachmentId = scp_logo_attachment_id();

    if ($attachmentId <= 0) {
        return null;
    }

    $url = wp_get_attachment_image_url($attachmentId, $size);

    return $url !== false ? $url : null;
}
