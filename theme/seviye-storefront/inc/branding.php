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
 * Null when no logo has been uploaded yet - callers fall back to the
 * letter-badge mark (see header.php/templates/login.php) themselves.
 */
function scp_logo_url(string $size = 'medium'): ?string
{
    if (!class_exists(\Seviye\Core\Plugin::class)) {
        return null;
    }

    $settings = \Seviye\Core\Plugin::instance()->container()->get(
        \Seviye\Core\Settings\SettingsRepositoryInterface::class
    );

    $attachmentId = (int) $settings->get(\Seviye\Core\Http\BrandingRestController::LOGO_ATTACHMENT_ID_KEY);

    if ($attachmentId <= 0) {
        return null;
    }

    $url = wp_get_attachment_image_url($attachmentId, $size);

    return $url !== false ? $url : null;
}
