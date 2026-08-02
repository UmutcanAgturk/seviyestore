<?php

/**
 * "Verilerim (KVKK)" - self-service veri ihracı/silme talebi card, included
 * identically from templates/zone.php (/admin, /sube) and
 * templates/parent-dashboard.php (/), same "every role manages its own
 * account this way" reasoning as templates/partials/account-security.php
 * (never capability-gated - see
 * Seviye\Security\Http\PrivacyRequestsRestController::isLoggedIn()).
 * assets/js/privacy-requests-panel.js binds to it wherever it appears via
 * getElementById.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<section class="scp-card" id="scp-privacy-requests-panel">
    <div class="scp-card__header">
        <h2><?php esc_html_e('Verilerim (KVKK)', 'seviye-storefront'); ?></h2>
    </div>

    <p class="scp-status" data-scp-privacy-status></p>

    <h3><?php esc_html_e('Verilerimi İndir', 'seviye-storefront'); ?></h3>
    <p>
        <?php esc_html_e(
            'Platformun hakkınızda tuttuğu hesap ve kimlik bilgilerini (T.C. Kimlik No, e-posta, varsa bağlı öğrenciler) bir JSON dosyası olarak indirin.',
            'seviye-storefront'
        ); ?>
    </p>
    <button type="button" class="scp-btn" data-scp-privacy-export>
        <?php esc_html_e('Verilerimi İndir', 'seviye-storefront'); ?>
    </button>

    <hr>

    <h3><?php esc_html_e('Hesabımın Silinmesini Talep Et', 'seviye-storefront'); ?></h3>
    <p>
        <?php esc_html_e(
            'Talebiniz Genel Merkez tarafından incelendikten sonra hesap kimlik bilgileriniz anonimleştirilir. Bu işlem geri alınamaz.',
            'seviye-storefront'
        ); ?>
    </p>

    <form class="scp-form" data-scp-privacy-deletion-form>
        <label>
            <span><?php esc_html_e('Not (opsiyonel)', 'seviye-storefront'); ?></span>
            <textarea name="note" rows="2"></textarea>
        </label>
        <button type="submit" class="scp-btn scp-btn--danger">
            <?php esc_html_e('Silme Talebi Gönder', 'seviye-storefront'); ?>
        </button>
    </form>

    <h3><?php esc_html_e('Taleplerim', 'seviye-storefront'); ?></h3>
    <div class="scp-table-wrapper">
        <table class="scp-table" data-scp-privacy-history-table hidden>
            <thead>
                <tr>
                    <th><?php esc_html_e('Tür', 'seviye-storefront'); ?></th>
                    <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                    <th><?php esc_html_e('Talep Tarihi', 'seviye-storefront'); ?></th>
                    <th><?php esc_html_e('Sonuç Notu', 'seviye-storefront'); ?></th>
                </tr>
            </thead>
            <tbody data-scp-privacy-history-body></tbody>
        </table>
    </div>
</section>
