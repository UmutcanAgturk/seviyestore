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
 * lookup.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
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

    <?php if (current_user_can('scp_manage_students')) : ?>
        <section class="scp-card" id="scp-students-panel">
            <div class="scp-card__header">
                <h2><?php esc_html_e('Öğrenciler', 'seviye-storefront'); ?></h2>
                <button type="button" class="scp-btn" data-scp-new-student>
                    <?php esc_html_e('Yeni Öğrenci', 'seviye-storefront'); ?>
                </button>
            </div>

            <p class="scp-status" data-scp-students-status></p>

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
        <p><?php esc_html_e('Bu panelin içeriği, ilgili modüller geliştirildikçe burada yer alacak.', 'seviye-storefront'); ?></p>
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
                <p class="scp-hakedis-balance" data-scp-hakedis-own-balance></p>
            </div>

            <table class="scp-table" data-scp-hakedis-all hidden>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Şube', 'seviye-storefront'); ?></th>
                        <th><?php esc_html_e('Bakiye (TRY)', 'seviye-storefront'); ?></th>
                    </tr>
                </thead>
                <tbody data-scp-hakedis-all-body></tbody>
            </table>
        </section>
    <?php endif; ?>
</div>
<?php
get_footer();
