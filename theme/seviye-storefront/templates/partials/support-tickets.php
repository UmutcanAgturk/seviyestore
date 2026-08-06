<?php

/**
 * "Destek Taleplerim" - veli self-service card: yeni bir "şikayet/soru"
 * ticket'ı aç, kendi ticket'larını gör, mesaj thread'ine yanıt yaz. KVKK
 * talebinden (templates/partials/privacy-requests.php) tamamen ayrı bir
 * akış - bkz. plugin/seviye-destek/src/Http/SupportTicketsRestController.php.
 * Yalnızca scp_submit_support_ticket (Veli) sahibi kullanıcılar için
 * templates/parent-dashboard.php'den include edilir - privacy-requests.php'nin
 * aksine (o her role için gösterilir), bu yüzden burada ayrıca bir
 * current_user_can() kontrolü yoktur, include eden şablon zaten kontrol eder.
 * assets/js/support-tickets-panel.js'in initSelfService() fonksiyonu bağlanır.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<section class="scp-card" id="scp-support-tickets-panel">
    <div class="scp-card__header">
        <h2><?php esc_html_e('Destek Taleplerim', 'seviye-storefront'); ?></h2>
    </div>

    <p class="scp-status" data-scp-support-status></p>

    <h3><?php esc_html_e('Yeni Talep', 'seviye-storefront'); ?></h3>
    <form class="scp-form" data-scp-support-new-form>
        <label>
            <span><?php esc_html_e('Konu', 'seviye-storefront'); ?></span>
            <input type="text" name="subject" required>
        </label>
        <label>
            <span><?php esc_html_e('Şube', 'seviye-storefront'); ?></span>
            <select name="branch_id" data-scp-support-branch-select>
                <option value=""><?php esc_html_e('Genel Merkez', 'seviye-storefront'); ?></option>
            </select>
        </label>
        <label>
            <span><?php esc_html_e('Mesaj', 'seviye-storefront'); ?></span>
            <textarea name="message" rows="4" required maxlength="1000" data-scp-char-counter></textarea>
        </label>
        <div class="scp-form__actions">
            <button type="submit" class="scp-btn"><?php esc_html_e('Gönder', 'seviye-storefront'); ?></button>
        </div>
    </form>

    <h3><?php esc_html_e('Taleplerim', 'seviye-storefront'); ?></h3>
    <div class="scp-table-wrapper">
        <table class="scp-table" data-scp-support-list-table hidden>
            <thead>
                <tr>
                    <th><?php esc_html_e('Konu', 'seviye-storefront'); ?></th>
                    <th><?php esc_html_e('Şube', 'seviye-storefront'); ?></th>
                    <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                    <th><?php esc_html_e('Güncellenme', 'seviye-storefront'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-scp-support-list-body></tbody>
        </table>
    </div>

    <div class="scp-card scp-card--nested" data-scp-support-detail hidden>
        <div class="scp-card__header">
            <h3 data-scp-support-detail-title></h3>
            <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-support-detail-close>
                <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
            </button>
        </div>

        <ul class="scp-list" data-scp-support-messages></ul>

        <form class="scp-form" data-scp-support-reply-form>
            <textarea name="message" rows="3" required maxlength="1000" data-scp-char-counter></textarea>
            <div class="scp-form__actions">
                <button type="submit" class="scp-btn scp-btn--small">
                    <?php esc_html_e('Yanıtla', 'seviye-storefront'); ?>
                </button>
            </div>
        </form>
    </div>
</section>
