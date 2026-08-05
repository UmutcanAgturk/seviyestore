<?php

/**
 * Cari Bakiye - its own page (/admin/cari-bakiye, /sube/cari-bakiye, see
 * inc/zones.php's scp_menu_pages()) - "her bir menü için ayrı bir sayfa
 * yap" (bölüm 65). Reached at all already implies scp_view_hakedis or
 * scp_view_own_hakedis passed - see scp_menu_pages(). Markup/ids/
 * data-attributes moved here verbatim from templates/zone.php so
 * assets/js/hakedis-panel.js keeps working unchanged.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Cari Bakiye', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-hakedis-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('Cari Bakiye', 'seviye-storefront'); ?></h2>
        </div>

        <p class="scp-status" data-scp-hakedis-status></p>

        <div data-scp-hakedis-own hidden>
            <div class="scp-hakedis-stats">
                <div class="scp-hakedis-stat">
                    <span class="scp-hakedis-stat__label"><?php esc_html_e('Alacak', 'seviye-storefront'); ?></span>
                    <span class="scp-hakedis-stat__value" data-scp-hakedis-own-accrued></span>
                </div>
                <div class="scp-hakedis-stat">
                    <span class="scp-hakedis-stat__label"><?php esc_html_e('Ödenen', 'seviye-storefront'); ?></span>
                    <span class="scp-hakedis-stat__value" data-scp-hakedis-own-settled></span>
                </div>
                <div class="scp-hakedis-stat">
                    <span class="scp-hakedis-stat__label"><?php esc_html_e('Bakiye', 'seviye-storefront'); ?></span>
                    <span class="scp-hakedis-stat__value scp-hakedis-stat__value--primary" data-scp-hakedis-own-balance></span>
                </div>
            </div>
        </div>

        <div class="scp-table-wrapper">
            <table class="scp-table" data-scp-hakedis-all hidden>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Şube', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Alacak (TRY)', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Ödenen (TRY)', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Bakiye (TRY)', 'seviye-storefront'); ?></th>
                    </tr>
                </thead>
                <tbody data-scp-hakedis-all-body></tbody>
            </table>
        </div>

        <div data-scp-hakedis-settlements-panel>
            <h3><?php esc_html_e('Tahsilat', 'seviye-storefront'); ?></h3>

            <label data-scp-settlement-branch-field hidden>
                <span><?php esc_html_e('Şube', 'seviye-storefront'); ?></span>
                <select data-scp-settlement-branch-select></select>
            </label>

            <p class="scp-status" data-scp-settlements-status></p>

            <ul class="scp-list" data-scp-settlements-list></ul>

            <form class="scp-form" data-scp-settlement-form hidden>
                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Tutar (TRY)', 'seviye-storefront'); ?></span>
                        <input type="number" min="0.01" step="0.01" name="amount" required>
                    </label>
                    <label>
                        <span><?php esc_html_e('Yöntem', 'seviye-storefront'); ?></span>
                        <select name="method">
                            <option value="bank_transfer"><?php esc_html_e('Banka Havalesi', 'seviye-storefront'); ?></option>
                            <option value="cash"><?php esc_html_e('Nakit', 'seviye-storefront'); ?></option>
                            <option value="other"><?php esc_html_e('Diğer', 'seviye-storefront'); ?></option>
                        </select>
                    </label>
                </div>
                <label>
                    <span><?php esc_html_e('Not', 'seviye-storefront'); ?></span>
                    <input type="text" name="note">
                </label>
                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn">
                        <?php esc_html_e('Tahsilatı Kaydet', 'seviye-storefront'); ?>
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>
