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
 * The "API Anahtarları" section is /admin-only AND gated on
 * scp_manage_api_keys (Genel Merkez only) - it issues/lists/revokes the API
 * keys external ERP/muhasebe/mobil entegrasyonları use to authenticate
 * against seviye/v1 without a browser session (see
 * Seviye\Api\Http\ApiKeysRestController). A newly created key's plain value
 * is shown exactly once, client-side, and never requested again from the
 * server.
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
 * more than one section to jump between. Every entry is an in-page anchor
 * (`#scp-x-panel`) EXCEPT "Siparişler", whose value is a real URL
 * (scp_admin_orders_path(), `/admin/siparisler` or `/sube/siparisler`) -
 * Sipariş Yönetimi is its own standalone page, not a section on this one
 * (see inc/zones.php, templates/orders-admin.php), so esc_url() (not
 * esc_attr()) is used on every $scp_href below to render both kinds
 * correctly.
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

if (current_user_can('scp_view_reports') || current_user_can('scp_view_own_reports')) {
    $scp_sections['#scp-overview-panel'] = __('Genel Bakış', 'seviye-storefront');
}

if (current_user_can('scp_manage_students')) {
    $scp_sections['#scp-students-panel'] = __('Öğrenciler', 'seviye-storefront');
}

if (scp_current_zone() === 'admin' && current_user_can('scp_manage_branches')) {
    $scp_sections['#scp-branches-panel'] = __('Şubeler', 'seviye-storefront');
}

if (current_user_can('scp_manage_products') || current_user_can('scp_view_products')) {
    $scp_sections['#scp-products-panel'] = __('Ürünler', 'seviye-storefront');
}

if (current_user_can('scp_view_orders') || current_user_can('scp_view_own_branch_orders')) {
    $scp_sections[scp_admin_orders_path()] = __('Siparişler', 'seviye-storefront');
}

if (current_user_can('scp_manage_pricing')) {
    $scp_sections['#scp-pricing-panel'] = __('Fiyat Kuralları', 'seviye-storefront');
}

if (current_user_can('scp_manage_coupons')) {
    $scp_sections['#scp-coupons-panel'] = __('Kampanya Kodları', 'seviye-storefront');
}

if (current_user_can('scp_manage_purchase_orders')) {
    $scp_sections['#scp-depo-panel'] = __('Depo', 'seviye-storefront');
}

if (current_user_can('scp_view_hakedis') || current_user_can('scp_view_own_hakedis')) {
    $scp_sections['#scp-hakedis-panel'] = __('Cari Bakiye', 'seviye-storefront');
}

if (current_user_can('scp_view_reports') || current_user_can('scp_view_own_reports')) {
    $scp_sections['#scp-reports-panel'] = __('Raporlar', 'seviye-storefront');
}

if (current_user_can('scp_send_broadcast') || current_user_can('scp_send_own_branch_broadcast')) {
    $scp_sections['#scp-broadcast-panel'] = __('Toplu Duyuru', 'seviye-storefront');
}

$scp_sections['#scp-account-security-panel'] = __('Hesap Güvenliği', 'seviye-storefront');

if (scp_current_zone() === 'admin' && current_user_can('scp_manage_security_settings')) {
    $scp_sections['#scp-ip-allowlist-panel'] = __('IP Kısıtlaması', 'seviye-storefront');
}

if (scp_current_zone() === 'admin' && current_user_can('scp_manage_notification_settings')) {
    $scp_sections['#scp-sms-settings-panel'] = __('SMS Ayarları', 'seviye-storefront');
    $scp_sections['#scp-email-settings-panel'] = __('E-posta Ayarları', 'seviye-storefront');
}

if (scp_current_zone() === 'admin' && current_user_can('scp_manage_api_keys')) {
    $scp_sections['#scp-api-keys-panel'] = __('API Anahtarları', 'seviye-storefront');
}

if (scp_current_zone() === 'admin' && current_user_can('scp_manage_core_settings')) {
    $scp_sections['#scp-branding-panel'] = __('Görünüm', 'seviye-storefront');
}

if (scp_current_zone() === 'admin' && current_user_can('scp_view_audit_logs')) {
    $scp_sections['#scp-activity-log-panel'] = __('Aktivite Günlüğü', 'seviye-storefront');
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
                <a href="<?php echo esc_url($scp_href); ?>"><?php echo esc_html($scp_label); ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if (current_user_can('scp_view_reports') || current_user_can('scp_view_own_reports')) : ?>
        <section class="scp-card" id="scp-overview-panel">
            <div class="scp-card__header">
                <h2><?php esc_html_e('Genel Bakış', 'seviye-storefront'); ?></h2>
            </div>

            <p class="scp-status" data-scp-overview-status></p>

            <div class="scp-stat-grid" data-scp-overview-stats hidden>
                <div class="scp-stat-tile">
                    <span class="scp-stat-tile__label"><?php esc_html_e('Bugün', 'seviye-storefront'); ?></span>
                    <span class="scp-stat-tile__value" data-scp-overview-today-total></span>
                    <span class="scp-stat-tile__meta" data-scp-overview-today-count></span>
                </div>
                <div class="scp-stat-tile">
                    <span class="scp-stat-tile__label"><?php esc_html_e('Son 7 Gün', 'seviye-storefront'); ?></span>
                    <span class="scp-stat-tile__value" data-scp-overview-week-total></span>
                    <span class="scp-stat-tile__meta" data-scp-overview-week-count></span>
                </div>
                <div class="scp-stat-tile">
                    <span class="scp-stat-tile__label"><?php esc_html_e('Son 30 Gün', 'seviye-storefront'); ?></span>
                    <span class="scp-stat-tile__value" data-scp-overview-month-total></span>
                    <span class="scp-stat-tile__meta" data-scp-overview-month-count></span>
                </div>
            </div>

            <h3><?php esc_html_e('En Çok Satan Ürünler (Son 30 Gün)', 'seviye-storefront'); ?></h3>
            <div class="scp-table-wrapper">
                <table class="scp-table" data-scp-overview-products-table hidden>
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Ürün', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Adet', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Ciro (TRY)', 'seviye-storefront'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-scp-overview-products-body></tbody>
                </table>
            </div>

            <div data-scp-overview-branch-section hidden>
                <h3><?php esc_html_e('Şube Bazlı Kırılım (Son 30 Gün)', 'seviye-storefront'); ?></h3>
                <div class="scp-table-wrapper">
                    <table class="scp-table" data-scp-overview-branches-table hidden>
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Şube', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Sipariş', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Ciro (TRY)', 'seviye-storefront'); ?></th>
                            </tr>
                        </thead>
                        <tbody data-scp-overview-branches-body></tbody>
                    </table>
                </div>
            </div>
        </section>
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
                            <th><?php esc_html_e('T.C. Kimlik No', 'seviye-storefront'); ?></th>
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
                    <label>
                        <span><?php esc_html_e('Öğrenci T.C. Kimlik No (isteğe bağlı)', 'seviye-storefront'); ?></span>
                        <input type="text" name="tc_no" inputmode="numeric" maxlength="11" pattern="[0-9]{11}">
                    </label>
                </div>

                <div class="scp-form__row">
                    <label data-scp-branch-field hidden>
                        <span><?php esc_html_e('Şube', 'seviye-storefront'); ?></span>
                        <select name="branch_id"></select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Eğitim Yılı', 'seviye-storefront'); ?></span>
                        <select name="education_year" required>
                            <?php
                            // "YYYY-YYYY" biçimini elle yazdırmak yerine bir
                            // menüden seçtiriyoruz - Seviye\Students\Domain\
                            // EducationYear::isValid() zaten yalnızca bu
                            // biçimi kabul ediyor, serbest metin girişi
                            // kullanıcıyı sessizce 422'ye götürüyordu.
                            $scp_current_start_year = (int) current_time('Y');
                            for ($scp_offset = -1; $scp_offset <= 3; $scp_offset++) :
                                $scp_start_year = $scp_current_start_year + $scp_offset;
                                $scp_year_value = $scp_start_year . '-' . ($scp_start_year + 1);
                                ?>
                                <option value="<?php echo esc_attr($scp_year_value); ?>">
                                    <?php echo esc_html($scp_year_value); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Sınıf', 'seviye-storefront'); ?></span>
                        <input type="text" name="class_name" required>
                    </label>
                </div>

                <div data-scp-parent-quick-add>
                    <h3><?php esc_html_e('Veli Bilgileri (isteğe bağlı)', 'seviye-storefront'); ?></h3>
                    <p class="scp-form__hint">
                        <?php esc_html_e(
                            'Doldurursanız, öğrenciyle birlikte bir veli hesabı oluşturulup otomatik olarak bağlanır. Şifre otomatik oluşturulur ve kayıttan sonra bir kez gösterilir. E-posta zaten kayıtlı bir veliyle eşleşirse yeni hesap açılmaz, öğrenci mevcut veliye bağlanır (bu durumda T.C. Kimlik No/şifre değişmez).',
                            'seviye-storefront'
                        ); ?>
                    </p>
                    <div class="scp-form__row">
                        <label>
                            <span><?php esc_html_e('Veli Adı', 'seviye-storefront'); ?></span>
                            <input type="text" name="parent_first_name">
                        </label>
                        <label>
                            <span><?php esc_html_e('Veli Soyadı', 'seviye-storefront'); ?></span>
                            <input type="text" name="parent_last_name">
                        </label>
                    </div>
                    <div class="scp-form__row">
                        <label>
                            <span><?php esc_html_e('Veli E-posta', 'seviye-storefront'); ?></span>
                            <input type="email" name="parent_email">
                        </label>
                        <label>
                            <span><?php esc_html_e('Yakınlık', 'seviye-storefront'); ?></span>
                            <select name="parent_relationship">
                                <option value="anne"><?php esc_html_e('Anne', 'seviye-storefront'); ?></option>
                                <option value="baba"><?php esc_html_e('Baba', 'seviye-storefront'); ?></option>
                                <option value="vasi"><?php esc_html_e('Vasi', 'seviye-storefront'); ?></option>
                            </select>
                        </label>
                    </div>
                    <div class="scp-form__row">
                        <label>
                            <span><?php esc_html_e('Veli T.C. Kimlik No', 'seviye-storefront'); ?></span>
                            <input type="text" name="parent_tc_no" inputmode="numeric" maxlength="11" pattern="[0-9]{11}">
                        </label>
                    </div>
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

                <div data-scp-spending-limit-panel hidden>
                    <h3><?php esc_html_e('Harcama Limiti', 'seviye-storefront'); ?></h3>
                    <p class="scp-form__hint" data-scp-spending-limit-status></p>
                    <div class="scp-form scp-form--inline">
                        <label>
                            <span><?php esc_html_e('Dönem', 'seviye-storefront'); ?></span>
                            <select data-scp-spending-limit-period>
                                <option value="monthly"><?php esc_html_e('Aylık', 'seviye-storefront'); ?></option>
                                <option value="term"><?php esc_html_e('Dönemlik', 'seviye-storefront'); ?></option>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e('Limit Tutarı (TRY)', 'seviye-storefront'); ?></span>
                            <input type="number" min="0" step="0.01" data-scp-spending-limit-amount>
                        </label>
                        <button type="button" class="scp-btn" data-scp-save-spending-limit>
                            <?php esc_html_e('Kaydet', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--ghost" data-scp-remove-spending-limit>
                            <?php esc_html_e('Limiti Kaldır', 'seviye-storefront'); ?>
                        </button>
                    </div>
                </div>
            </form>

            <div class="scp-reveal-card" data-scp-registration-summary hidden>
                <h3><?php esc_html_e('Kayıt Özeti', 'seviye-storefront'); ?></h3>
                <p class="scp-form__hint">
                    <?php esc_html_e(
                        'Bu bilgiler yalnızca bir kez gösterilir. Şifreyi kapatmadan önce veliye iletin.',
                        'seviye-storefront'
                    ); ?>
                </p>
                <dl class="scp-summary-list" data-scp-registration-summary-list></dl>
                <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-dismiss-summary>
                    <?php esc_html_e('Kapat', 'seviye-storefront'); ?>
                </button>
            </div>
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

    <?php if (current_user_can('scp_manage_products') || current_user_can('scp_view_products')) : ?>
        <?php $scp_can_manage_products = current_user_can('scp_manage_products'); ?>
        <section class="scp-card" id="scp-products-panel">
            <div class="scp-card__header">
                <h2><?php esc_html_e('Ürünler', 'seviye-storefront'); ?></h2>
                <?php if ($scp_can_manage_products) : ?>
                    <button type="button" class="scp-btn" data-scp-new-product>
                        <?php esc_html_e('Yeni Ürün', 'seviye-storefront'); ?>
                    </button>
                <?php endif; ?>
            </div>

            <p class="scp-form__hint">
                <?php esc_html_e(
                    'Ürünler tüm şubelerin ortak kataloğundadır. Her şube, kendi öğrenci/velisi için bir ürünü ayrı ayrı aktif ya da pasif yapabilir.',
                    'seviye-storefront'
                ); ?>
            </p>

            <p class="scp-status" data-scp-products-status></p>

            <div class="scp-table-wrapper">
                <table class="scp-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th><?php esc_html_e('ID', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Ürün', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Fiyat (TRY)', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Kategori', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Stok', 'seviye-storefront'); ?></th>
                            <?php if ($scp_can_manage_products) : ?>
                                <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                                <th></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody data-scp-products-body></tbody>
                </table>
            </div>

            <?php if ($scp_can_manage_products) : ?>
                <form class="scp-form" data-scp-product-form hidden>
                    <input type="hidden" name="id">
                    <input type="hidden" name="image_id">

                    <div class="scp-form__row">
                        <label>
                            <span><?php esc_html_e('Ürün Adı', 'seviye-storefront'); ?></span>
                            <input type="text" name="name" required>
                        </label>
                        <label>
                            <span><?php esc_html_e('Fiyat (TRY)', 'seviye-storefront'); ?></span>
                            <input type="number" min="0" step="0.01" name="price" required>
                        </label>
                    </div>

                    <label>
                        <span><?php esc_html_e('Açıklama', 'seviye-storefront'); ?></span>
                        <textarea name="description" rows="3"></textarea>
                    </label>

                    <div class="scp-form__row">
                        <label>
                            <span><?php esc_html_e('Kategori', 'seviye-storefront'); ?></span>
                            <input type="text" name="category" placeholder="<?php esc_attr_e('ör. Kırtasiye', 'seviye-storefront'); ?>">
                        </label>
                        <label class="scp-checkbox">
                            <input type="checkbox" name="manage_stock" data-scp-manage-stock>
                            <span><?php esc_html_e('Stok takibi yap', 'seviye-storefront'); ?></span>
                        </label>
                        <label data-scp-stock-quantity-field hidden>
                            <span><?php esc_html_e('Stok Adedi', 'seviye-storefront'); ?></span>
                            <input type="number" min="0" step="1" name="stock_quantity">
                        </label>
                        <label data-scp-low-stock-field hidden>
                            <span>
                                <?php esc_html_e('Düşük Stok Eşiği (isteğe bağlı)', 'seviye-storefront'); ?>
                            </span>
                            <input type="number" min="0" step="1" name="low_stock_amount">
                        </label>
                    </div>

                    <div class="scp-form__row" data-scp-variant-fields>
                        <label>
                            <span><?php esc_html_e('Bedenler (virgülle ayırın, ör. S,M,L,XL)', 'seviye-storefront'); ?></span>
                            <input type="text" name="sizes" placeholder="S,M,L,XL">
                        </label>
                        <label>
                            <span><?php esc_html_e('Renkler (virgülle ayırın, ör. Kırmızı,Mavi)', 'seviye-storefront'); ?></span>
                            <input type="text" name="colors" placeholder="Kırmızı,Mavi">
                        </label>
                    </div>
                    <p class="scp-form__hint" data-scp-variant-hint>
                        <?php esc_html_e(
                            'Beden ve/veya renk girilirse ürün varyantlı oluşturulur, her kombinasyon için ayrı stok/fiyat sonradan "Varyantları Düzenle" ile ayarlanır. Mevcut bir ürünün varyant yapısı sonradan değiştirilemez.',
                            'seviye-storefront'
                        ); ?>
                    </p>

                    <p class="scp-form__hint" data-scp-variant-locked-notice hidden>
                        <?php esc_html_e(
                            'Bu ürün varyantlı - fiyat/stok tek tek her varyant için "Varyantları Düzenle" ile ayarlanır.',
                            'seviye-storefront'
                        ); ?>
                    </p>

                    <label>
                        <span><?php esc_html_e('Görsel', 'seviye-storefront'); ?></span>
                        <img class="scp-product-image-preview" data-scp-product-image-preview hidden alt="">
                        <input type="file" accept="image/*" data-scp-product-image-input>
                        <span class="scp-status" data-scp-product-image-status></span>
                    </label>

                    <div class="scp-form__actions">
                        <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                        <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-product>
                            <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--ghost" data-scp-edit-variations hidden>
                            <?php esc_html_e('Varyantları Düzenle', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--danger" data-scp-delete-product hidden>
                            <?php esc_html_e('Sil', 'seviye-storefront'); ?>
                        </button>
                    </div>
                </form>

                <div class="scp-card scp-card--nested" data-scp-product-variations-panel hidden>
                    <div class="scp-card__header">
                        <h3><?php esc_html_e('Varyantlar', 'seviye-storefront'); ?></h3>
                    </div>
                    <div class="scp-table-wrapper">
                        <table class="scp-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Varyant', 'seviye-storefront'); ?></th>
                                    <th><?php esc_html_e('Fiyat (TRY)', 'seviye-storefront'); ?></th>
                                    <th><?php esc_html_e('Stok Adedi', 'seviye-storefront'); ?></th>
                                </tr>
                            </thead>
                            <tbody data-scp-product-variations-list></tbody>
                        </table>
                    </div>
                    <div class="scp-form__actions">
                        <button type="button" class="scp-btn" data-scp-save-variations>
                            <?php esc_html_e('Kaydet', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--ghost" data-scp-close-product-variations>
                            <?php esc_html_e('Kapat', 'seviye-storefront'); ?>
                        </button>
                    </div>
                </div>

                <div class="scp-card scp-card--nested" data-scp-product-branches-panel hidden>
                    <div class="scp-card__header">
                        <h3><?php esc_html_e('Şube Bazlı Durum', 'seviye-storefront'); ?></h3>
                    </div>
                    <p class="scp-form__hint">
                        <?php esc_html_e(
                            'Listelenmeyen bir şube bu ürün için varsayılan olarak aktiftir.',
                            'seviye-storefront'
                        ); ?>
                    </p>
                    <ul class="scp-list" data-scp-product-branches-list></ul>
                    <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-close-product-branches>
                        <?php esc_html_e('Kapat', 'seviye-storefront'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (current_user_can('scp_manage_coupons')) : ?>
        <section class="scp-card" id="scp-coupons-panel">
            <div class="scp-card__header">
                <h2><?php esc_html_e('Kampanya Kodları', 'seviye-storefront'); ?></h2>
                <button type="button" class="scp-btn" data-scp-new-coupon>
                    <?php esc_html_e('Yeni Kod', 'seviye-storefront'); ?>
                </button>
            </div>

            <p class="scp-status" data-scp-coupons-status></p>

            <div class="scp-table-wrapper">
                <table class="scp-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Kod', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('İndirim', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Kullanım', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Son Geçerlilik', 'seviye-storefront'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-scp-coupons-body></tbody>
                </table>
            </div>

            <form class="scp-form" data-scp-coupon-form hidden>
                <input type="hidden" name="id">

                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Kod', 'seviye-storefront'); ?></span>
                        <input type="text" name="code" required placeholder="OKUL2026">
                    </label>
                    <label>
                        <span><?php esc_html_e('İndirim Türü', 'seviye-storefront'); ?></span>
                        <select name="discount_type" required>
                            <option value="percent"><?php esc_html_e('Yüzde (%)', 'seviye-storefront'); ?></option>
                            <option value="fixed_cart">
                                <?php esc_html_e('Sabit Tutar (Sepet)', 'seviye-storefront'); ?>
                            </option>
                            <option value="fixed_product">
                                <?php esc_html_e('Sabit Tutar (Ürün)', 'seviye-storefront'); ?>
                            </option>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Tutar', 'seviye-storefront'); ?></span>
                        <input type="number" name="amount" min="0" step="0.01" required>
                    </label>
                </div>

                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Kullanım Limiti (isteğe bağlı)', 'seviye-storefront'); ?></span>
                        <input type="number" name="usage_limit" min="1" step="1" placeholder="1">
                    </label>
                    <label>
                        <span><?php esc_html_e('Son Geçerlilik Tarihi (isteğe bağlı)', 'seviye-storefront'); ?></span>
                        <input type="date" name="expiry_date">
                    </label>
                </div>

                <div class="scp-form__row">
                    <label>
                        <span><?php esc_html_e('Açıklama (isteğe bağlı)', 'seviye-storefront'); ?></span>
                        <input type="text" name="description">
                    </label>
                </div>

                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                    <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-coupon>
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

    <?php if (current_user_can('scp_manage_purchase_orders')) : ?>
        <section class="scp-card" id="scp-depo-panel">
            <div class="scp-card__header">
                <h2><?php esc_html_e('Depo', 'seviye-storefront'); ?></h2>
            </div>

            <p class="scp-status" data-scp-depo-status></p>

            <div class="scp-card scp-card--nested">
                <div class="scp-card__header">
                    <h3><?php esc_html_e('Tedarikçiler', 'seviye-storefront'); ?></h3>
                    <button type="button" class="scp-btn" data-scp-new-supplier>
                        <?php esc_html_e('Yeni Tedarikçi', 'seviye-storefront'); ?>
                    </button>
                </div>

                <div class="scp-table-wrapper">
                    <table class="scp-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Ad', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('İletişim', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Telefon', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('E-posta', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody data-scp-suppliers-body></tbody>
                    </table>
                </div>

                <form class="scp-form" data-scp-supplier-form hidden>
                    <input type="hidden" name="id">

                    <div class="scp-form__row">
                        <label>
                            <span><?php esc_html_e('Ad', 'seviye-storefront'); ?></span>
                            <input type="text" name="name" required>
                        </label>
                        <label>
                            <span><?php esc_html_e('Yetkili', 'seviye-storefront'); ?></span>
                            <input type="text" name="contact_name">
                        </label>
                        <label>
                            <span><?php esc_html_e('Telefon', 'seviye-storefront'); ?></span>
                            <input type="text" name="phone">
                        </label>
                    </div>

                    <div class="scp-form__row">
                        <label>
                            <span><?php esc_html_e('E-posta', 'seviye-storefront'); ?></span>
                            <input type="email" name="email">
                        </label>
                        <label>
                            <span><?php esc_html_e('Vergi No', 'seviye-storefront'); ?></span>
                            <input type="text" name="tax_number">
                        </label>
                        <label data-scp-supplier-status-field hidden>
                            <span><?php esc_html_e('Durum', 'seviye-storefront'); ?></span>
                            <select name="status">
                                <option value="active"><?php esc_html_e('Aktif', 'seviye-storefront'); ?></option>
                                <option value="passive"><?php esc_html_e('Pasif', 'seviye-storefront'); ?></option>
                            </select>
                        </label>
                    </div>

                    <div class="scp-form__row">
                        <label>
                            <span><?php esc_html_e('Adres', 'seviye-storefront'); ?></span>
                            <input type="text" name="address">
                        </label>
                    </div>

                    <div class="scp-form__actions">
                        <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                        <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-supplier>
                            <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                        </button>
                        <button type="button" class="scp-btn scp-btn--danger" data-scp-delete-supplier hidden>
                            <?php esc_html_e('Sil', 'seviye-storefront'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <div class="scp-card scp-card--nested">
                <div class="scp-card__header">
                    <h3><?php esc_html_e('Satın Alma Siparişleri', 'seviye-storefront'); ?></h3>
                    <button type="button" class="scp-btn" data-scp-new-purchase-order>
                        <?php esc_html_e('Yeni Sipariş', 'seviye-storefront'); ?>
                    </button>
                </div>

                <div class="scp-table-wrapper">
                    <table class="scp-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Kod', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Tedarikçi', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Durum', 'seviye-storefront'); ?></th>
                                <th><?php esc_html_e('Beklenen Tarih', 'seviye-storefront'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody data-scp-purchase-orders-body></tbody>
                    </table>
                </div>

                <form class="scp-form" data-scp-purchase-order-form hidden>
                    <div class="scp-form__row">
                        <label>
                            <span><?php esc_html_e('Tedarikçi', 'seviye-storefront'); ?></span>
                            <select name="supplier_id" required></select>
                        </label>
                        <label>
                            <span><?php esc_html_e('Beklenen Tarih (isteğe bağlı)', 'seviye-storefront'); ?></span>
                            <input type="date" name="expected_date">
                        </label>
                        <label>
                            <span><?php esc_html_e('Not (isteğe bağlı)', 'seviye-storefront'); ?></span>
                            <input type="text" name="note">
                        </label>
                    </div>

                    <h4><?php esc_html_e('Kalemler', 'seviye-storefront'); ?></h4>
                    <div class="scp-table-wrapper">
                        <table class="scp-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Ürün ID', 'seviye-storefront'); ?></th>
                                    <th><?php esc_html_e('Adet', 'seviye-storefront'); ?></th>
                                    <th><?php esc_html_e('Birim Maliyet (isteğe bağlı)', 'seviye-storefront'); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-scp-purchase-order-items></tbody>
                        </table>
                    </div>
                    <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-add-po-item>
                        <?php esc_html_e('Kalem Ekle', 'seviye-storefront'); ?>
                    </button>

                    <div class="scp-form__actions">
                        <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                        <button type="button" class="scp-btn scp-btn--ghost" data-scp-cancel-purchase-order>
                            <?php esc_html_e('Vazgeç', 'seviye-storefront'); ?>
                        </button>
                    </div>
                </form>

                <div class="scp-card scp-card--nested" data-scp-po-detail hidden>
                    <div class="scp-card__header">
                        <h4 data-scp-po-detail-title></h4>
                        <div>
                            <button type="button" class="scp-btn scp-btn--small" data-scp-po-send hidden>
                                <?php esc_html_e('Gönder', 'seviye-storefront'); ?>
                            </button>
                            <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-po-cancel hidden>
                                <?php esc_html_e('İptal Et', 'seviye-storefront'); ?>
                            </button>
                            <button type="button" class="scp-btn scp-btn--ghost scp-btn--small" data-scp-close-po-detail>
                                <?php esc_html_e('Kapat', 'seviye-storefront'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="scp-table-wrapper">
                        <table class="scp-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Ürün ID', 'seviye-storefront'); ?></th>
                                    <th><?php esc_html_e('Sipariş', 'seviye-storefront'); ?></th>
                                    <th><?php esc_html_e('Teslim Alınan', 'seviye-storefront'); ?></th>
                                    <th><?php esc_html_e('Kalan', 'seviye-storefront'); ?></th>
                                    <th data-scp-po-receive-header hidden>
                                        <?php esc_html_e('Şimdi Teslim Al', 'seviye-storefront'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody data-scp-po-detail-items></tbody>
                        </table>
                    </div>

                    <button type="button" class="scp-btn" data-scp-po-receive hidden>
                        <?php esc_html_e('Mal Kabul Et', 'seviye-storefront'); ?>
                    </button>
                </div>
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

    <?php if (current_user_can('scp_send_broadcast') || current_user_can('scp_send_own_branch_broadcast')) : ?>
        <section class="scp-card" id="scp-broadcast-panel">
            <div class="scp-card__header">
                <h2><?php esc_html_e('Toplu Duyuru', 'seviye-storefront'); ?></h2>
            </div>

            <p class="scp-status" data-scp-broadcast-status></p>

            <form class="scp-form" data-scp-broadcast-form>
                <label data-scp-broadcast-branch-field hidden>
                    <span><?php esc_html_e('Alıcı Şube', 'seviye-storefront'); ?></span>
                    <select name="branch_id"></select>
                </label>
                <label>
                    <span><?php esc_html_e('Başlık', 'seviye-storefront'); ?></span>
                    <input type="text" name="subject" required>
                </label>
                <label>
                    <span><?php esc_html_e('Mesaj', 'seviye-storefront'); ?></span>
                    <textarea name="body" rows="5" required></textarea>
                </label>
                <fieldset class="scp-form__row">
                    <label class="scp-checkbox">
                        <input type="checkbox" name="channel_email" checked>
                        <span><?php esc_html_e('E-posta', 'seviye-storefront'); ?></span>
                    </label>
                    <label class="scp-checkbox">
                        <input type="checkbox" name="channel_panel" checked>
                        <span><?php esc_html_e('Panel Bildirimi', 'seviye-storefront'); ?></span>
                    </label>
                    <label class="scp-checkbox">
                        <input type="checkbox" name="channel_sms">
                        <span><?php esc_html_e('SMS', 'seviye-storefront'); ?></span>
                    </label>
                </fieldset>
                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('Gönder', 'seviye-storefront'); ?></button>
                </div>
            </form>
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

    <?php if (scp_current_zone() === 'admin' && current_user_can('scp_manage_notification_settings')) : ?>
        <section class="scp-card" id="scp-email-settings-panel">
            <div class="scp-card__header">
                <h2><?php esc_html_e('E-posta Ayarları (Gmail SMTP)', 'seviye-storefront'); ?></h2>
            </div>

            <p class="scp-form__hint">
                <?php esc_html_e(
                    'Veliler sipariş verdiğinde otomatik gönderilen bildirim e-postaları bu Gmail hesabı üzerinden gider.',
                    'seviye-storefront'
                ); ?>
                <?php esc_html_e(
                    'Uygulama Şifresi, Google hesabınızın normal şifresi değildir - Google hesap ayarlarından oluşturulur.',
                    'seviye-storefront'
                ); ?>
            </p>

            <p class="scp-status" data-scp-email-settings-status></p>

            <form class="scp-form" data-scp-email-settings-form>
                <label>
                    <span><?php esc_html_e('Gmail Adresi', 'seviye-storefront'); ?></span>
                    <input type="email" name="email" required>
                </label>
                <label>
                    <span>
                        <?php esc_html_e('Uygulama Şifresi (değiştirmek istemiyorsanız boş bırakın)', 'seviye-storefront'); ?>
                    </span>
                    <input type="password" name="app_password" autocomplete="new-password">
                </label>
                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('Kaydet', 'seviye-storefront'); ?></button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <?php if (scp_current_zone() === 'admin' && current_user_can('scp_manage_api_keys')) : ?>
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
    <?php endif; ?>

    <?php if (scp_current_zone() === 'admin' && current_user_can('scp_manage_core_settings')) : ?>
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
    <?php endif; ?>

    <?php if (scp_current_zone() === 'admin' && current_user_can('scp_view_audit_logs')) : ?>
        <section class="scp-card" id="scp-activity-log-panel">
            <div class="scp-card__header">
                <h2><?php esc_html_e('Aktivite Günlüğü', 'seviye-storefront'); ?></h2>
            </div>

            <p class="scp-status" data-scp-activity-log-status></p>

            <form class="scp-form scp-form--inline" data-scp-activity-log-form>
                <label>
                    <span><?php esc_html_e('Kanal', 'seviye-storefront'); ?></span>
                    <select name="channel">
                        <option value=""><?php esc_html_e('Tümü', 'seviye-storefront'); ?></option>
                        <option value="activity"><?php esc_html_e('Panel İşlemleri', 'seviye-storefront'); ?></option>
                        <option value="security.auth">
                            <?php esc_html_e('Giriş/Kimlik Doğrulama', 'seviye-storefront'); ?>
                        </option>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Seviye', 'seviye-storefront'); ?></span>
                    <select name="level">
                        <option value=""><?php esc_html_e('Tümü', 'seviye-storefront'); ?></option>
                        <option value="info"><?php esc_html_e('Bilgi', 'seviye-storefront'); ?></option>
                        <option value="warning"><?php esc_html_e('Uyarı', 'seviye-storefront'); ?></option>
                        <option value="error"><?php esc_html_e('Hata', 'seviye-storefront'); ?></option>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Kullanıcı ID', 'seviye-storefront'); ?></span>
                    <input type="number" min="1" name="user_id">
                </label>
                <label>
                    <span><?php esc_html_e('Başlangıç', 'seviye-storefront'); ?></span>
                    <input type="date" name="from">
                </label>
                <label>
                    <span><?php esc_html_e('Bitiş', 'seviye-storefront'); ?></span>
                    <input type="date" name="to">
                </label>
                <label>
                    <span><?php esc_html_e('Ara', 'seviye-storefront'); ?></span>
                    <input type="text" name="search">
                </label>

                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('Getir', 'seviye-storefront'); ?></button>
                </div>
            </form>

            <div class="scp-table-wrapper">
                <table class="scp-table" data-scp-activity-log-table hidden>
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Tarih', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Kullanıcı', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Kanal', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('Seviye', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('İşlem', 'seviye-storefront'); ?></th>
                            <th><?php esc_html_e('IP', 'seviye-storefront'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-scp-activity-log-body></tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php
get_footer();
