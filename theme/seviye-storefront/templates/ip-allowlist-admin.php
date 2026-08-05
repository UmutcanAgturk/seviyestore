<?php

/**
 * IP Kısıtlaması - its own page (/admin/ip-kisitlamasi, see
 * inc/zones.php's scp_menu_pages()) - "her bir menü için ayrı bir sayfa
 * yap" (bölüm 65). Reached at all already implies
 * scp_manage_security_settings AND the admin zone passed - see
 * scp_menu_pages(). Markup/ids/data-attributes moved here verbatim from
 * templates/zone.php so assets/js/ip-allowlist-panel.js keeps working
 * unchanged.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('IP Kısıtlaması', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-ip-allowlist-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('IP Kısıtlaması', 'seviye-storefront'); ?></h2>
        </div>

        <p class="scp-status" data-scp-ip-allowlist-status></p>

        <p>
            <?php esc_html_e('Genel Merkez paneline (/admin) yalnızca aşağıdaki IP adreslerinden/aralıklarından erişilebilir. Boş bırakılırsa kısıtlama uygulanmaz.', 'seviye-storefront'); ?>
        </p>

        <form class="scp-form" data-scp-ip-allowlist-form>
            <label>
                <span><?php esc_html_e('IP Adresleri (her satıra bir tane, CIDR desteklenir)', 'seviye-storefront'); ?></span>
                <textarea name="entries" rows="6" placeholder="203.0.113.5&#10;198.51.100.0/24"></textarea>
            </label>
            <div class="scp-form__actions">
                <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
            </div>
        </form>
    </section>
</div>
