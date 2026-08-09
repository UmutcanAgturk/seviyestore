<?php

/**
 * Öğrenciler - its own page (/admin/ogrenciler, /sube/ogrenciler, see
 * inc/zones.php's scp_menu_pages()) instead of a section inside the big
 * /admin or /sube dashboard - "her bir menü için ayrı bir sayfa yap"
 * (bölüm 65), the same split templates/orders-admin.php/products-admin.php
 * already got. Reached at all already implies scp_manage_students passed -
 * see scp_menu_pages(). Markup/ids/data-attributes moved here VERBATIM
 * from templates/zone.php so assets/js/students-panel.js keeps working
 * unchanged.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="scp-panel">
    <h1><?php esc_html_e('Öğrenciler', 'seviye-storefront'); ?></h1>
    <p>
        <a href="<?php echo esc_url(home_url('/' . scp_current_zone())); ?>">
            <?php esc_html_e('← Panele Dön', 'seviye-storefront'); ?>
        </a>
    </p>

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

        <div class="scp-card scp-card--nested">
            <div class="scp-card__header">
                <h3><?php esc_html_e('Toplu İçe Aktarma (CSV)', 'seviye-storefront'); ?></h3>
            </div>

            <p class="scp-form__hint">
                <?php esc_html_e(
                    'CSV dosyasının ilk satırı başlık olmalı: first_name, last_name, education_year, class_name, tc_no (isteğe bağlı). Tüm satırlar aşağıda seçilen tek bir şubeye eklenir.',
                    'seviye-storefront'
                ); ?>
                <a href="#" data-scp-download-import-template>
                    <?php esc_html_e('Örnek şablonu indir', 'seviye-storefront'); ?>
                </a>
            </p>

            <form class="scp-form scp-form--inline" data-scp-import-form>
                <label data-scp-import-branch-field hidden>
                    <span><?php esc_html_e('Şube', 'seviye-storefront'); ?></span>
                    <select name="branch_id"></select>
                </label>
                <label>
                    <span><?php esc_html_e('CSV Dosyası', 'seviye-storefront'); ?></span>
                    <input type="file" accept=".csv,text/csv" name="csv_file" required>
                </label>
                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn"><?php esc_html_e('İçe Aktar', 'seviye-storefront'); ?></button>
                </div>
            </form>

            <div data-scp-import-result hidden>
                <p data-scp-import-summary></p>
                <ul class="scp-list" data-scp-import-errors></ul>
            </div>
        </div>

        <div class="scp-card scp-card--nested">
            <div class="scp-card__header">
                <h3><?php esc_html_e('Toplu Sınıf/Eğitim Yılı Geçişi', 'seviye-storefront'); ?></h3>
            </div>

            <p class="scp-form__hint">
                <?php esc_html_e(
                    'Seçilen eğitim yılındaki tüm aktif öğrenciler bir sonraki eğitim yılına taşınır. Sınıf eşlemesi opsiyoneldir - her satıra "eski sınıf=yeni sınıf" yazın (ör. 5-A=6-A); eşlemesi olmayan sınıf adı değişmeden kalır.',
                    'seviye-storefront'
                ); ?>
            </p>

            <form class="scp-form scp-form--inline" data-scp-promote-form>
                <label data-scp-promote-branch-field hidden>
                    <span><?php esc_html_e('Şube', 'seviye-storefront'); ?></span>
                    <select name="branch_id"></select>
                </label>
                <label>
                    <span><?php esc_html_e('Mevcut Eğitim Yılı', 'seviye-storefront'); ?></span>
                    <input type="text" name="from_education_year" placeholder="2025-2026" required>
                </label>
                <div class="scp-form__actions">
                    <button type="submit" class="scp-btn">
                        <?php esc_html_e('Toplu Geçiş Yap', 'seviye-storefront'); ?>
                    </button>
                </div>
            </form>

            <label>
                <span><?php esc_html_e('Sınıf Eşleme (opsiyonel)', 'seviye-storefront'); ?></span>
                <textarea data-scp-promote-class-map rows="3" placeholder="5-A=6-A&#10;5-B=6-B"></textarea>
            </label>

            <div data-scp-promote-result hidden>
                <p data-scp-promote-summary></p>
            </div>
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
                    <select name="class_name" required>
                        <option value=""></option>
                        <?php foreach (scp_grade_level_options() as $scp_grade_level) : ?>
                            <option value="<?php echo esc_attr($scp_grade_level); ?>">
                                <?php echo esc_html($scp_grade_level); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                        <span>
                            <?php esc_html_e('Dönem', 'seviye-storefront'); ?>
                            <?php
                            $spendingLimitPeriodTip = __(
                                'Aylık: her takvim ayı başında sıfırlanır. Dönemlik: bir tarih sınırı yoktur - öğrencinin ödemesi tamamlanmış TÜM siparişlerinin toplamına göre hesaplanır.',
                                'seviye-storefront'
                            );
                            ?>
                            <span
                                class="scp-help-tip"
                                tabindex="0"
                                data-tip="<?php echo esc_attr($spendingLimitPeriodTip); ?>"
                                aria-label="<?php echo esc_attr($spendingLimitPeriodTip); ?>"
                            >?</span>
                        </span>
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
</div>
