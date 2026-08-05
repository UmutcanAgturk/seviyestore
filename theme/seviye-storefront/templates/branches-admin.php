<?php

/**
 * Şubeler - its own page (/admin/subeler, see inc/zones.php's
 * scp_menu_pages()) - "her bir menü için ayrı bir sayfa yap" (bölüm 65).
 * Reached at all already implies scp_manage_branches AND the admin zone
 * passed - see scp_menu_pages(). Markup/ids/data-attributes moved here
 * verbatim from templates/zone.php so assets/js/branches-panel.js keeps
 * working unchanged.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Şubeler', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-branches-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('Şubeler', 'seviye-storefront'); ?></h2>
            <button type="button" class="scp-btn" data-scp-new-branch>
                <?php esc_html_e('Yeni Şube', 'seviye-storefront'); ?>
            </button>
        </div>

        <p class="scp-status" data-scp-branches-status></p>

        <div class="scp-table-wrapper">
            <table class="scp-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Ad', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('IBAN', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Komisyon (%)', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Telefon', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-scp-branches-body></tbody>
            </table>
        </div>

        <form class="scp-form" data-scp-branch-form hidden>
            <input type="hidden" name="id">

            <div class="scp-form__row">
                <label>
                    <span><?php esc_html_e('Ad', 'seviye-storefront'); ?></span>
                    <input type="text" name="name" required>
                </label>
                <label>
                    <span><?php esc_html_e('IBAN', 'seviye-storefront'); ?></span>
                    <input type="text" name="iban" placeholder="TR...">
                </label>
            </div>

            <div class="scp-form__row">
                <label>
                    <span><?php esc_html_e('Komisyon (%)', 'seviye-storefront'); ?></span>
                    <input type="number" name="commission_rate" min="0" max="100" step="0.01" required>
                </label>
                <label>
                    <span><?php esc_html_e('Telefon', 'seviye-storefront'); ?></span>
                    <input type="text" name="phone">
                </label>
                <label data-scp-branch-status-field hidden>
                    <span><?php esc_html_e('Durum', 'seviye-storefront'); ?></span>
                    <select name="status">
                        <option value="active"><?php esc_html_e('Aktif', 'seviye-storefront'); ?></option>
                        <option value="inactive"><?php esc_html_e('Pasif', 'seviye-storefront'); ?></option>
                    </select>
                </label>
            </div>

            <label>
                <span><?php esc_html_e('Adres', 'seviye-storefront'); ?></span>
                <input type="text" name="address">
            </label>

            <div class="scp-form__actions">
                <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-branch>
                    <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                </button>
            </div>
        </form>
    </section>
</div>
