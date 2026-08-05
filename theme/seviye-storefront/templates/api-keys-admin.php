<?php

/**
 * API Anahtarları - its own page (/admin/api-anahtarlari, see
 * inc/zones.php's scp_menu_pages()) - "her bir menü için ayrı bir sayfa
 * yap" (bölüm 65). Reached at all already implies scp_manage_api_keys AND
 * the admin zone passed - see scp_menu_pages(). Markup/ids/data-attributes
 * moved here verbatim from templates/zone.php so assets/js/api-keys-panel.js
 * keeps working unchanged.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('API Anahtarları', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

    <section class="scp-card" id="scp-api-keys-panel">
        <div class="scp-card__header">
            <h2><?php esc_html_e('API Anahtarları', 'seviye-storefront'); ?></h2>
            <button type="button" class="scp-btn" data-scp-new-api-key>
                <?php esc_html_e('Yeni Anahtar', 'seviye-storefront'); ?>
            </button>
        </div>

        <p class="scp-status" data-scp-api-keys-status></p>

        <div class="scp-api-key-reveal" data-scp-api-key-reveal hidden>
            <p>
                <strong>
                    <?php esc_html_e('Bu anahtar yalnızca bir kez gösterilir. Şimdi kopyalayın.', 'seviye-storefront'); ?>
                </strong>
            </p>
            <code data-scp-api-key-value></code>
            <div class="scp-form__actions">
                <button type="button" class="scp-btn scp-btn--ghost" data-scp-dismiss-api-key>
                    <?php esc_html_e('Kapat', 'seviye-storefront'); ?>
                </button>
            </div>
        </div>

        <form class="scp-form" data-scp-api-key-form hidden>
            <div class="scp-form__row">
                <label>
                    <span><?php esc_html_e('Etiket', 'seviye-storefront'); ?></span>
                    <input type="text" name="label" required>
                </label>
                <label>
                    <span><?php esc_html_e('Kullanıcı ID (boş = ben)', 'seviye-storefront'); ?></span>
                    <input type="number" min="1" name="user_id">
                </label>
            </div>
            <div class="scp-form__actions">
                <button type="submit" class="scp-btn"><?php esc_html_e('Oluştur', 'seviye-storefront'); ?></button>
                <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-api-key>
                    <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                </button>
            </div>
        </form>

        <div class="scp-table-wrapper">
            <table class="scp-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Etiket', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Anahtar', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Kullanıcı ID', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Son Kullanım', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-scp-api-keys-body></tbody>
            </table>
        </div>
    </section>
</div>
