<?php

/**
 * Görünüm - its own page (/admin/gorunum, see inc/zones.php's
 * scp_menu_pages()) - "her bir menü için ayrı bir sayfa yap" (bölüm 65).
 * Reached at all already implies scp_manage_core_settings AND the admin
 * zone passed - see scp_menu_pages(). Markup/ids/data-attributes moved
 * here verbatim from templates/zone.php so assets/js/branding-panel.js
 * keeps working unchanged.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Görünüm', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-branding-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('Görünüm', 'seviye-storefront'); ?></h2>
        </div>

        <p class="scp-form__hint">
            <?php esc_html_e(
                'Yüklenen logo, giriş ekranı ve her sayfanın üst kısmındaki marka alanında gösterilir (PNG önerilir).',
                'seviye-storefront'
            ); ?>
        </p>

        <p class="scp-status" data-scp-branding-status></p>

        <div class="scp-form">
            <img class="scp-branding-preview" data-scp-branding-preview hidden alt="">
            <label>
                <span><?php esc_html_e('Logo (PNG)', 'seviye-storefront'); ?></span>
                <input type="file" accept="image/png,image/*" data-scp-branding-input>
            </label>
            <div class="scp-form__actions">
                <button type="button" class="scp-btn scp-btn--ghost" data-scp-remove-branding>
                    <?php esc_html_e('Logoyu Kaldır', 'seviye-storefront'); ?>
                </button>
            </div>
        </div>
    </section>
</div>
