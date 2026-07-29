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
</div>
<?php
get_footer();
