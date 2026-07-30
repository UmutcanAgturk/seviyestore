<?php

/**
 * Shared landing view for the /admin and /sube zones. Included directly by
 * inc/zones.php with $scp_zone_label already set in scope.
 *
 * Both zones reuse the same student management panel because the REST API
 * itself already scopes the data (Genel Merkez/Bölge Müdürü see every
 * branch, Şube Müdürü only their own) - see
 * Seviye\Students\Http\StudentsRestController::currentUserBranchId(). Not
 * every branch-scoped role has scp_manage_students (Muhasebe, Depo, Satış
 * Danışmanı, Rehberlik do not), so this checks the capability in PHP before
 * rendering the panel, rather than showing a form that would just 403 on
 * submit for those roles.
 *
 * The branch management section is /admin-only (scp_current_zone() check):
 * scp_manage_branches is granted only to Genel Merkez/Bölge Müdürü, who are
 * the only roles that ever land in the admin zone, but the explicit check
 * keeps this page correct even if that role/zone mapping ever changes.
 *
 * The price rules section, unlike branches, appears in BOTH zones: unlike
 * scp_manage_branches, scp_manage_pricing is also granted to Şube Müdürü
 * (who lands in /sube, not /admin) - see
 * Seviye\Pricing\Http\PricingRestController::canWriteScope(). It requires a
 * product id to look up (no product catalog exists yet - Seviye
 * Commerce/WooCommerce integration is still planned).
 *
 * The cari bakiye (hakediş balance) section also appears in BOTH zones:
 * scp_view_hakedis (HQ) and scp_view_own_hakedis (Şube Müdürü + Muhasebe
 * only, not every branch-scoped role - see
 * Seviye\Finance\Rbac\HakedisCapability) land in /admin and /sube
 * respectively. There is no "list every branch's balance" REST endpoint;
 * the HQ view instead combines the already-public GET /branches with one
 * GET /finance/hakedis/balance/{id} call per branch, client-side - the
 * same "no speculative REST surface" principle used for the price rules
 * lookup. Its "Tahsilat" (settlement) sub-section is browsable by anyone
 * who can view a balance (branch picker in the HQ view, automatic own
 * branch otherwise) but only writable by scp_record_hakedis_settlement
 * holders (Genel Merkez / Muhasebe) - a Bölge Müdürü sees the same
 * settlement history a Muhasebe user does, but never the record form.
 *
 * The "Raporlar" section (both zones) mirrors the cari bakiye section's
 * split exactly: scp_view_reports (HQ) sees a branch filter (blank = every
 * branch) and scp_view_own_reports (Şube Müdürü) is silently locked to
 * their own branch - see Seviye\Reports\Http\ReportsRestController. "Getir"
 * loads the JSON view inline; the CSV/Excel buttons instead navigate the
 * browser straight to GET /reports/sales?format=csv|xlsx (a real file
 * download can't go through fetch()+JS, so the REST nonce rides along as a
 * `_wpnonce` query param instead of the X-WP-Nonce header every other call
 * here uses).
 *
 * The "SMS Ayarları" section is /admin-only AND gated on
 * scp_manage_notification_settings (Genel Merkez only, mirroring the IP
 * Kısıtlaması section's gate) - it configures the NetGSM SMS gateway
 * credentials Seviye Notifications' SMS channel needs (see
 * Seviye\Notifications\Http\NotificationsSettingsRestController). The
 * notification bell itself (unread panel-içi bildirimler) is NOT rendered
 * here - it lives in header.php, since it needs to appear on every
 * authenticated page (including WooCommerce shop/product pages), not just
 * this zone's own panel.
 *
 * The "Hesap Güvenliği" (2FA) section renders for EVERY role in both
 * zones, unconditionally - unlike every other section here, it is not
 * gated by a capability check, because it manages the current user's own
 * account, not a permission-scoped resource (see
 * Seviye\Security\Http\TwoFactorRestController). The "IP Kısıtlaması"
 * section is the opposite extreme: /admin-only AND gated on
 * scp_manage_security_settings, Genel Merkez's single most privileged,
 * newly-introduced capability (see Seviye\Security\Rbac\SecurityCapability).
 *
 * The quicknav at the top of the page is built from the exact same
 * capability (+ zone) checks each section below already gates on ($scp_
 * -prefixed locals, computed once before get_header()) - it never invents a
 * link a section wouldn't actually render, and only appears once there is
 * more than one section to jump between.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Quicknav is built from the exact same capability (+ zone, where relevant)
 * checks each section below already gates on - it never invents a link a
 * section wouldn't actually render. Only shown when there is more than one
 * section to jump between; a single-section page gains nothing from it.
 */
$scp_sections = [];

if (current_user_can('scp_manage_students')) {
    $scp_sections['#scp-students-panel'] = __('Öğrenciler', 'seviye-storefront');
}

if (scp_current_zone() === 'admin' && current_user_can('scp_manage_branches')) {
    $scp_sections['#scp-branches-panel'] = __('Şubeler', 'seviye-storefront');
}

if (current_user_can('scp_manage_pricing')) {
    $scp_sections['#scp-pricing-panel'] = __('Fiyat Kuralları', 'seviye-storefront');
}

if (current_user_can('scp_view_hakedis') || current_user_can('scp_view_own_hakedis')) {
    $scp_sections['#scp-hakedis-panel'] = __('Cari Bakiye', 'seviye-storefront');
}

if (current_user_can('scp_view_reports') || current_user_can('scp_view_own_reports')) {
    $scp_sections['#scp-reports-panel'] = __('Raporlar', 'seviye-storefront');
}

$scp_sections['#scp-account-security-panel'] = __('Hesap Güvenliği', 'seviye-storefront');

if (scp_current_zone() === 'admin' && current_user_can('scp_manage_security_settings')) {
    $scp_sections['#scp-ip-allowlist-panel'] = __('IP Kısıtlaması', 'seviye-storefront');
}

if (scp_current_zone() === 'admin' && current_user_can('scp_manage_notification_settings')) {
    $scp_sections['#scp-sms-settings-panel'] = __('SMS Ayarları', 'seviye-storefront');
}

get_header();
?>
<div class="scp-panel">
    <h1><?php
        echo esc_html(sprintf(
            /* translators: %s: zone label, e.g. "Genel Merkez" or "Şube" */
            __('%s Paneli', 'seviye-storefront'),
            $scp_zone_label
        ));
        ?></h1>
    <p><?php
        echo esc_html(sprintf(
            /* translators: %s: display name of the logged-in user */
            __('Hoş geldiniz, %s.', 'seviye-storefront'),
            wp_get_current_user()->display_name
        ));
        ?></p>

    <?php if (count($scp_sections) > 1) : ?>
        <nav class="scp-quicknav" aria-label="<?php esc_attr_e('Bölüm kısayolları', 'seviye-storefront'); ?>">
            <?php foreach ($scp_sections as $scp_href => $scp_label) : ?>
                <a href="<?php echo esc_attr($scp_href); ?>"><?php echo esc_html($scp_label); ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if (current_user_can('scp_manage_students')) : ?>
        <section class="scp-card" id="scp-students-panel">
            <div class="scp-card__header">
                <h2><?php esc_html_e('Öğrenciler', 'seviye-storefront'); ?></h2>
                <button type="button" class="scp-btn" data-scp-new-student>
                    <?php esc_html_e('Yeni Öğrenci', 'seviye-storefront'); ?>
                </button>
            </div>

            <p class="scp-status" data-scp-students-status></p>

            <div class="scp-table-wrapper">
                <table class="scp-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Ad Soyad', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Şube', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Eğitim Yılı', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Sınıf', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-scp-students-body></tbody>
                </table>
            </div>

            <form class="scp-form" data-scp-student-form hidden>
                <input type="hidden" name="id">

                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Ad', 'seviye-storefront'); ?></span>
                        <input type="text" name="first_name" required>
                    </label>
                    <label>
                        <span><?php esc_html_e('Soyad', 'seviye-storefront'); ?></span>
                        <input type="text" name="last_name" required>
                    </label>
                </div>

                <div class="scp-form__row">
                    <label data-scp-branch-field hidden>
                        <span><?php esc_html_e('Şube', 'seviye-storefront'); ?></span>
                        <select name="branch_id"></select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Eğitim Yılı', 'seviye-storefront'); ?></span>
                        <input type="text" name="education_year" placeholder="2025-2026" required>
                    </label>
                    <label>
                        <span><?php esc_html_e('Sınıf', 'seviye-storefront'); ?></span>
                        <input type="text" name="class_name" required>
                    </label>
                </div>

                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                    <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-student>
                        <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                    </button>
                </div>

                <div data-scp-parents-panel hidden>
                    <h3><?php esc_html_e('Veliler', 'seviye-storefront'); ?></h3>
                    <ul class="scp-list" data-scp-parents-list></ul>

                    <div class="scp-form scp-form--inline">
                        <label>
                            <span><?php esc_html_e('Veli Kullanıcı ID', 'seviye-storefront'); ?></span>
                            <input type="number" min="1" data-scp-parent-user-id>
                        </label>
                        <label>
                            <span><?php esc_html_e('Yakınlık', 'seviye-storefront'); ?></span>
                            <select data-scp-parent-relationship>
                                <option value="anne"><?php esc_html_e('Anne', 'seviye-storefront'); ?></option>
                                <option value="baba"><?php esc_html_e('Baba', 'seviye-storefront'); ?></option>
                                <option value="vasi"><?php esc_html_e('Vasi', 'seviye-storefront'); ?></option>
                            </select>
                        </label>
                        <button type="button" class="scp-btn" data-scp-link-parent>
                            <?php esc_html_e('Bağla', 'seviye-storefront'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </section>
    <?php else : ?>
        <div class="scp-card scp-empty">
            <p><?php esc_html_e('Bu panelin içeriği, ilgili modüller geliştirildikçe burada yer alacak.', 'seviye-storefront'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (scp_current_zone() === 'admin' && current_user_can('scp_manage_branches')) : ?>
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
    <?php endif; ?>

    <?php if (current_user_can('scp_manage_pricing')) : ?>
        <section class="scp-card" id="scp-pricing-panel">
            <div class="scp-card__header">
                <h2><?php esc_html_e('Fiyat Kuralları', 'seviye-storefront'); ?></h2>
            </div>

            <form class="scp-form scp-form--inline" data-scp-price-lookup-form>
                <label>
                    <span><?php esc_html_e('Ürün ID', 'seviye-storefront'); ?></span>
                    <input type="number" min="1" name="product_id" required>
                </label>
                <button type="submit" class="scp-btn"><?php esc_html_e('Fiyatları Getir', 'seviye-storefront'); ?></button>
            </form>

            <p class="scp-status" data-scp-pricing-status></p>

            <div data-scp-price-rules-results hidden>
                <div class="scp-card__header">
                    <span></span>
                    <button type="button" class="scp-btn" data-scp-new-price-rule>
                        <?php esc_html_e('Yeni Kural', 'seviye-storefront'); ?>
                    </button>
                </div>

                <div class="scp-table-wrapper">
                    <table class="scp-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Kapsam', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Hedef', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Fiyat (TRY)', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody data-scp-price-rules-body></tbody>
                    </table>
                </div>

                <form class="scp-form" data-scp-price-rule-form hidden>
                    <input type="hidden" name="id">

                    <div class="scp-form__row">
                        <label data-scp-price-scope-field>
                            <span><?php esc_html_e('Kapsam', 'seviye-storefront'); ?></span>
                            <select name="scope">
                                <option value="general" data-scp-scope-general><?php esc_html_e('Genel', 'seviye-storefront'); ?></option>
                                <option value="branch"><?php esc_html_e('Şube', 'seviye-storefront'); ?></option>
                                <option value="student"><?php esc_html_e('Öğrenci', 'seviye-storefront'); ?></option>
                            </select>
                        </label>
                        <label data-scp-price-target-field hidden>
                            <span data-scp-price-target-label></span>
                            <input type="number" min="1" name="target_id">
                        </label>
                    </div>

                    <div class="scp-form__row">
                        <label>
                            <span><?php esc_html_e('Fiyat (TRY)', 'seviye-storefront'); ?></span>
                            <input type="number" min="0" step="0.01" name="price" required>
                        </label>
                        <label data-scp-price-status-field hidden>
                            <span><?php esc_html_e('Durum', 'seviye-storefront'); ?></span>
                            <select name="status">
                                <option value="active"><?php esc_html_e('Aktif', 'seviye-storefront'); ?></option>
                                <option value="inactive"><?php esc_html_e('Pasif', 'seviye-storefront'); ?></option>
                            </select>
                        </label>
                    </div>

                    <div class="scp-form__actions">
                        <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                        <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-price-rule>
                            <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--danger" data-scp-delete-price-rule hidden>
                            <?php esc_html_e('Sil', 'seviye-storefront'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <?php if (current_user_can('scp_view_hakedis') || current_user_can('scp_view_own_hakedis')) : ?>
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
    <?php endif; ?>

    <?php if (current_user_can('scp_view_reports') || current_user_can('scp_view_own_reports')) : ?>
        <section class="scp-card" id="scp-reports-panel">
            <div class="scp-card__header">
                <h2><?php esc_html_e('Raporlar', 'seviye-storefront'); ?></h2>
            </div>

            <p class="scp-status" data-scp-reports-status></p>

            <form class="scp-form scp-form--inline" data-scp-report-form>
                <label data-scp-report-branch-field hidden>
                    <span><?php esc_html_e('Şube', 'seviye-storefront'); ?></span>
                    <select></select>
                </label>
                <label>
                    <span><?php esc_html_e('Ürün ID', 'seviye-storefront'); ?></span>
                    <input type="number" min="1" name="product_id">
                </label>
                <label>
                    <span><?php esc_html_e('Kategori ID', 'seviye-storefront'); ?></span>
                    <input type="number" min="1" name="category_id">
                </label>
                <label>
                    <span><?php esc_html_e('Başlangıç', 'seviye-storefront'); ?></span>
                    <input type="date" name="from">
                </label>
                <label>
                    <span><?php esc_html_e('Bitiş', 'seviye-storefront'); ?></span>
                    <input type="date" name="to">
                </label>

                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('Getir', 'seviye-storefront'); ?></button>
                    <button type="button" class="scp-btn scp-btn--ghost" data-scp-report-csv>
                        <?php esc_html_e('CSV İndir', 'seviye-storefront'); ?>
                    </button>
                    <button type="button" class="scp-btn scp-btn--ghost" data-scp-report-xlsx>
                        <?php esc_html_e('Excel İndir', 'seviye-storefront'); ?>
                    </button>
                </div>
            </form>

            <div class="scp-table-wrapper">
                <table class="scp-table" data-scp-reports-table hidden>
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Şube', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Ürün', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Sipariş Sayısı', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Toplam Tutar (TRY)', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Toplam KDV (TRY)', 'seviye-storefront'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-scp-reports-body></tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php include SCP_THEME_DIR . '/templates/partials/account-security.php'; ?>

    <?php if (scp_current_zone() === 'admin' && current_user_can('scp_manage_security_settings')) : ?>
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
    <?php endif; ?>

    <?php if (scp_current_zone() === 'admin' && current_user_can('scp_manage_notification_settings')) : ?>
        <section class="scp-card" id="scp-sms-settings-panel">
            <div class="scp-card__header">
                <h2><?php esc_html_e('SMS Ayarları (NetGSM)', 'seviye-storefront'); ?></h2>
            </div>

            <p class="scp-status" data-scp-sms-settings-status></p>

            <form class="scp-form" data-scp-sms-settings-form>
                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Kullanıcı Kodu', 'seviye-storefront'); ?></span>
                        <input type="text" name="usercode" required>
                    </label>
                    <label>
                        <span><?php esc_html_e('Başlık (Msgheader)', 'seviye-storefront'); ?></span>
                        <input type="text" name="msgheader" required>
                    </label>
                </div>
                <label>
                    <span>
                        <?php esc_html_e('Şifre (değiştirmek istemiyorsanız boş bırakın)', 'seviye-storefront'); ?>
                    </span>
                    <input type="password" name="password" autocomplete="new-password">
                </label>
                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                </div>
            </form>
        </section>
    <?php endif; ?>
</div>
<?php
get_footer();
