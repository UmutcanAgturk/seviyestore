<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'scp_enqueue_assets');

function scp_enqueue_assets(): void
{
    if (!is_user_logged_in()) {
        scp_enqueue_auth_assets();

        return;
    }

    wp_enqueue_style(
        'scp-theme',
        SCP_THEME_URL . '/assets/css/theme.css',
        [],
        scp_asset_version('/assets/css/theme.css')
    );
    wp_enqueue_style(
        'scp-panel',
        SCP_THEME_URL . '/assets/css/panel.css',
        ['scp-theme'],
        scp_asset_version('/assets/css/panel.css')
    );

    if (class_exists('WooCommerce')) {
        wp_enqueue_style(
            'scp-woocommerce',
            SCP_THEME_URL . '/assets/css/woocommerce.css',
            ['scp-theme'],
            scp_asset_version('/assets/css/woocommerce.css')
        );
    }

    scp_enqueue_panel_assets();
}

/**
 * REST calls made from an already-authenticated screen carry a WordPress
 * cookie, so - unlike the pre-login seviye/v1/auth/* endpoints in
 * inc/assets.php's auth counterpart - WordPress' own cookie-nonce check
 * (rest_cookie_check_errors(), wired in by core on every request) rejects
 * them without a valid X-WP-Nonce header. Every script enqueued here gets
 * one via scpPanel.nonce.
 */
function scp_enqueue_panel_assets(): void
{
    $zone = (string) get_query_var('scp_zone');

    // Shared by every panel script below (see assets/js/scp-api-fetch.js) -
    // registered once here rather than re-declared identically in all 11 of
    // them, and the one place that recognizes a stale X-WP-Nonce
    // ("Çerez denetlenemedi") and turns it into an actionable message.
    wp_enqueue_script(
        'scp-api-fetch',
        SCP_THEME_URL . '/assets/js/scp-api-fetch.js',
        [],
        scp_asset_version('/assets/js/scp-api-fetch.js'),
        true
    );

    // Design-system foundation (toast/modal/kebab-menu/command-palette/
    // skeleton/success-pulse helpers) - see scp-ui-kit.js's own docblock.
    // No dependency on scp-api-fetch (self-contained), enqueued first so
    // its globals (window.scpToast etc.) exist before any panel script
    // that might call them runs.
    wp_enqueue_script(
        'scp-ui-kit',
        SCP_THEME_URL . '/assets/js/scp-ui-kit.js',
        [],
        scp_asset_version('/assets/js/scp-ui-kit.js'),
        true
    );

    $localized = [
        'restUrl' => esc_url_raw(rest_url('seviye/v1/')),
        'wpRestRoot' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest'),
    ];

    $text = [
        'loadError' => __('Veriler yüklenirken bir hata oluştu.', 'seviye-storefront'),
        'saveError' => __('Kaydedilirken bir hata oluştu.', 'seviye-storefront'),
        'saved' => __('Kaydedildi.', 'seviye-storefront'),
        'edit' => __('Düzenle', 'seviye-storefront'),
        'remove' => __('Kaldır', 'seviye-storefront'),
        'details' => __('Detay', 'seviye-storefront'),
        'parentLinked' => __('Veli bağlandı.', 'seviye-storefront'),
        'profileSaved' => __('Profiliniz güncellendi.', 'seviye-storefront'),
        'noChildren' => __('Sisteme bağlı bir öğrenci bulunamadı.', 'seviye-storefront'),
        'scopeGeneral' => __('Genel', 'seviye-storefront'),
        'scopeBranch' => __('Şube', 'seviye-storefront'),
        'scopeStudent' => __('Öğrenci', 'seviye-storefront'),
        'branchIdLabel' => __('Şube ID', 'seviye-storefront'),
        'studentIdLabel' => __('Öğrenci ID', 'seviye-storefront'),
        'confirmDeletePriceRule' => __('Bu fiyat kuralını silmek istediğinize emin misiniz?', 'seviye-storefront'),
        'priceRuleDeleted' => __('Fiyat kuralı silindi.', 'seviye-storefront'),
        'statusActive' => __('Aktif', 'seviye-storefront'),
        'statusInactive' => __('Pasif', 'seviye-storefront'),
        'methodBankTransfer' => __('Banka Havalesi', 'seviye-storefront'),
        'methodCash' => __('Nakit', 'seviye-storefront'),
        'methodOther' => __('Diğer', 'seviye-storefront'),
        'settlementRecorded' => __('Tahsilat kaydedildi.', 'seviye-storefront'),
        'noSettlements' => __('Henüz tahsilat kaydı yok.', 'seviye-storefront'),
        'allBranches' => __('Tüm Şubeler', 'seviye-storefront'),
        'noReportData' => __('Seçilen kriterlere uygun kayıt bulunamadı.', 'seviye-storefront'),
        'noNotifications' => __('Bildirim yok.', 'seviye-storefront'),
        'smsConfigured' => __('NetGSM bağlantısı yapılandırıldı.', 'seviye-storefront'),
        'smsNotConfigured' => __('NetGSM bağlantısı henüz yapılandırılmadı.', 'seviye-storefront'),
        'emailConfigured' => __('Gmail bağlantısı yapılandırıldı.', 'seviye-storefront'),
        'emailNotConfigured' => __('Gmail bağlantısı henüz yapılandırılmadı.', 'seviye-storefront'),
        'passwordChanged' => __('Şifreniz güncellendi.', 'seviye-storefront'),
        'passwordTooWeak' => __('Yeni şifre en az 8 karakter olmalı.', 'seviye-storefront'),
        'addressSaved' => __('Adres bilgileri kaydedildi.', 'seviye-storefront'),
        'overviewOrdersLabel' => __('sipariş', 'seviye-storefront'),
        'trendChartLabel' => __('Son 30 günün günlük ciro trend grafiği', 'seviye-storefront'),
        'broadcastSentSuffix' => __('alıcıya gönderildi.', 'seviye-storefront'),
        'variantStock' => __('Varyantlı', 'seviye-storefront'),
        'apiKeyActive' => __('Aktif', 'seviye-storefront'),
        'apiKeyRevoked' => __('İptal Edildi', 'seviye-storefront'),
        'apiKeyRevokeAction' => __('İptal Et', 'seviye-storefront'),
        'confirmRevokeApiKey' => __('Bu API anahtarını iptal etmek istediğinize emin misiniz?', 'seviye-storefront'),
        'twoFactorEnabled' => __('İki adımlı doğrulama etkinleştirildi.', 'seviye-storefront'),
        'twoFactorDisabled' => __('İki adımlı doğrulama devre dışı bırakıldı.', 'seviye-storefront'),
        'twoFactorInvalidCode' => __('Kod hatalı. Lütfen tekrar deneyin.', 'seviye-storefront'),
        'twoFactorWrongPassword' => __('Şifre hatalı.', 'seviye-storefront'),
        'summaryStudent' => __('Öğrenci', 'seviye-storefront'),
        'summaryBranch' => __('Şube', 'seviye-storefront'),
        'summaryStudentTcNo' => __('Öğrenci T.C. Kimlik No', 'seviye-storefront'),
        'summaryClass' => __('Sınıf', 'seviye-storefront'),
        'summaryEducationYear' => __('Eğitim Yılı', 'seviye-storefront'),
        'summaryParent' => __('Veli', 'seviye-storefront'),
        'summaryParentEmail' => __('Veli E-posta', 'seviye-storefront'),
        'summaryTcNo' => __('Veli T.C. Kimlik No', 'seviye-storefront'),
        'summaryPassword' => __('Veli Şifresi', 'seviye-storefront'),
        'summaryNotSet' => __('Girilmedi', 'seviye-storefront'),
        'summaryTcNoError' => __('T.C. Kimlik No bağlanamadı', 'seviye-storefront'),
        'summaryLinkedExistingNote' => __(
            'Bu e-posta zaten kayıtlı bir veli hesabına ait. Yeni şifre oluşturulmadı, öğrenci mevcut hesaba bağlandı.',
            'seviye-storefront'
        ),
        'save' => __('Kaydet', 'seviye-storefront'),
        'cancel' => __('Vazgeç', 'seviye-storefront'),
        'parentUpdated' => __('Veli bilgileri güncellendi.', 'seviye-storefront'),
        'confirmDeleteStudent' => __(
            'Bu öğrenciyi kalıcı olarak silmek istediğinize emin misiniz? Bu işlem geri alınamaz.',
            'seviye-storefront'
        ),
        'studentDeleted' => __('Öğrenci silindi.', 'seviye-storefront'),
        'importing' => __('İçe aktarılıyor…', 'seviye-storefront'),
        /* translators: 1: imported count, 2: error row count - tokens replaced client-side (students-panel.js) */
        'importSummary' => __('%1$d öğrenci içe aktarıldı, %2$d satırda hata oluştu.', 'seviye-storefront'),
        /* translators: 1: CSV line number, 2: error message - tokens replaced client-side, see students-panel.js */
        'importErrorLine' => __('Satır %1$d: %2$s', 'seviye-storefront'),
        'spendingLimitNone' => __('Bu öğrenci için harcama limiti tanımlı değil.', 'seviye-storefront'),
        'spendingLimitSaved' => __('Harcama limiti kaydedildi.', 'seviye-storefront'),
        'spendingLimitRemoved' => __('Harcama limiti kaldırıldı.', 'seviye-storefront'),
        'spendingLimitLoadError' => __('Harcama limiti yüklenemedi.', 'seviye-storefront'),
        'spendingLimitPeriodMonthly' => __('Aylık', 'seviye-storefront'),
        'spendingLimitPeriodTerm' => __('Dönemlik', 'seviye-storefront'),
        'spendingLimitSpent' => __('Harcanan', 'seviye-storefront'),
        'spendingLimitRemaining' => __('Kalan', 'seviye-storefront'),
        'couponSaved' => __('Kampanya kodu kaydedildi.', 'seviye-storefront'),
        'couponDeleted' => __('Kampanya kodu silindi.', 'seviye-storefront'),
        'confirmDeleteCoupon' => __(
            'Bu kampanya kodunu kalıcı olarak silmek istediğinize emin misiniz? Bu işlem geri alınamaz.',
            'seviye-storefront'
        ),
        'confirmDeleteSupplier' => __(
            'Bu tedarikçiyi kalıcı olarak silmek istediğinize emin misiniz? Bu işlem geri alınamaz.',
            'seviye-storefront'
        ),
        'supplierDeleted' => __('Tedarikçi silindi.', 'seviye-storefront'),
        'confirmCancelPurchaseOrder' => __(
            'Bu satın alma siparişini iptal etmek istediğinize emin misiniz?',
            'seviye-storefront'
        ),
        'stockReceived' => __('Mal kabul kaydedildi, stok güncellendi.', 'seviye-storefront'),
        'poStatus_draft' => __('Taslak', 'seviye-storefront'),
        'poStatus_sent' => __('Gönderildi', 'seviye-storefront'),
        'poStatus_partially_received' => __('Kısmen Teslim Alındı', 'seviye-storefront'),
        'poStatus_completed' => __('Tamamlandı', 'seviye-storefront'),
        'poStatus_cancelled' => __('İptal Edildi', 'seviye-storefront'),
        'stockCountStatus_open' => __('Açık', 'seviye-storefront'),
        'stockCountStatus_completed' => __('Tamamlandı', 'seviye-storefront'),
        'confirmCompleteStockCount' => __(
            'Bu sayımı tamamlamak istediğinize emin misiniz? Farklar stoğa uygulanacak.',
            'seviye-storefront'
        ),
        'stockCountCompleted' => __('Sayım tamamlandı, farklar stoğa uygulandı.', 'seviye-storefront'),
        'noStockCounts' => __('Henüz bir stok sayımı başlatılmadı.', 'seviye-storefront'),
        'suggestionStatus_pending' => __('Bekliyor', 'seviye-storefront'),
        'suggestionStatus_dismissed' => __('Reddedildi', 'seviye-storefront'),
        'suggestionStatus_converted' => __('Siparişe Çevrildi', 'seviye-storefront'),
        'confirmDismissSuggestion' => __('Bu öneriyi reddetmek istediğinize emin misiniz?', 'seviye-storefront'),
        'suggestionDismissed' => __('Öneri reddedildi.', 'seviye-storefront'),
        'suggestionConverted' => __('Öneri satın alma siparişine çevrildi.', 'seviye-storefront'),
        'convertToOrder' => __('Siparişe Çevir', 'seviye-storefront'),
        'dismiss' => __('Reddet', 'seviye-storefront'),
        'noPurchaseSuggestions' => __('Bekleyen bir satın alma önerisi yok.', 'seviye-storefront'),
        'productSaved' => __('Ürün kaydedildi.', 'seviye-storefront'),
        'productDeleted' => __('Ürün silindi.', 'seviye-storefront'),
        'confirmDeleteProduct' => __(
            'Bu ürünü kalıcı olarak silmek istediğinize emin misiniz? Bu işlem geri alınamaz.',
            'seviye-storefront'
        ),
        'manageBranches' => __('Şubeler', 'seviye-storefront'),
        'uploadingImage' => __('Yükleniyor…', 'seviye-storefront'),
        'imageUploadError' => __('Görsel yüklenirken bir hata oluştu.', 'seviye-storefront'),
        'branchStatusSaved' => __('Şube durumu güncellendi.', 'seviye-storefront'),
        'brandingSaved' => __('Logo güncellendi.', 'seviye-storefront'),
        'brandingRemoved' => __('Logo kaldırıldı.', 'seviye-storefront'),
        'noOrders' => __('Henüz bir siparişiniz yok.', 'seviye-storefront'),
        'orderNumberLabel' => __('Sipariş No', 'seviye-storefront'),
        'orderDateLabel' => __('Tarih', 'seviye-storefront'),
        'orderPaymentMethodLabel' => __('Ödeme Yöntemi', 'seviye-storefront'),
        'orderSubtotalLabel' => __('Ara Toplam', 'seviye-storefront'),
        'orderTaxLabel' => __('KDV', 'seviye-storefront'),
        'orderTotalLabel' => __('Genel Toplam', 'seviye-storefront'),
        'orderItemProductLabel' => __('Ürün', 'seviye-storefront'),
        'orderItemStudentLabel' => __('Öğrenci', 'seviye-storefront'),
        'orderItemQuantityLabel' => __('Adet', 'seviye-storefront'),
        'orderItemUnitPriceLabel' => __('Birim Fiyat', 'seviye-storefront'),
        'orderItemTaxLabel' => __('KDV', 'seviye-storefront'),
        'orderItemTotalLabel' => __('Ara Toplam', 'seviye-storefront'),
        'orderCustomerLabel' => __('Veli', 'seviye-storefront'),
        'orderCustomerEmailLabel' => __('Veli E-posta', 'seviye-storefront'),
        'orderRefundedTotalLabel' => __('İade Edilen', 'seviye-storefront'),
        'cancelOrderAction' => __('İptal Et', 'seviye-storefront'),
        'refundOrderAction' => __('İade Et', 'seviye-storefront'),
        'confirmCancelOrder' => __('Bu siparişi iptal etmek istediğinize emin misiniz?', 'seviye-storefront'),
        'orderCancelled' => __('Sipariş iptal edildi.', 'seviye-storefront'),
        'refundAmountPrompt' => __('İade tutarı (TRY):', 'seviye-storefront'),
        'orderRefunded' => __('İade kaydedildi.', 'seviye-storefront'),
        'noActivityLogData' => __('Seçilen kriterlere uygun kayıt bulunamadı.', 'seviye-storefront'),
        'activityStudentCreated' => __('Öğrenci oluşturuldu', 'seviye-storefront'),
        'activityStudentUpdated' => __('Öğrenci güncellendi', 'seviye-storefront'),
        'activityStudentDeleted' => __('Öğrenci silindi', 'seviye-storefront'),
        'activityParentLinked' => __('Veli bağlandı', 'seviye-storefront'),
        'activityParentUpdated' => __('Veli bilgileri güncellendi', 'seviye-storefront'),
        'activityParentUnlinked' => __('Veli bağlantısı kaldırıldı', 'seviye-storefront'),
        'activityProductCreated' => __('Ürün oluşturuldu', 'seviye-storefront'),
        'activityProductUpdated' => __('Ürün güncellendi', 'seviye-storefront'),
        'activityProductDeleted' => __('Ürün silindi', 'seviye-storefront'),
        'activityProductBranchStatusChanged' => __('Ürün şube durumu güncellendi', 'seviye-storefront'),
        'activityPriceRuleCreated' => __('Fiyat kuralı oluşturuldu', 'seviye-storefront'),
        'activityPriceRuleUpdated' => __('Fiyat kuralı güncellendi', 'seviye-storefront'),
        'activityPriceRuleDeleted' => __('Fiyat kuralı silindi', 'seviye-storefront'),
        'activityBranchCreated' => __('Şube oluşturuldu', 'seviye-storefront'),
        'activityBranchUpdated' => __('Şube güncellendi', 'seviye-storefront'),
        'activitySettlementRecorded' => __('Tahsilat kaydedildi', 'seviye-storefront'),
        'activityBrandingSaved' => __('Logo güncellendi', 'seviye-storefront'),
        'activityBrandingRemoved' => __('Logo kaldırıldı', 'seviye-storefront'),
        'activityLoginSucceeded' => __('Giriş başarılı', 'seviye-storefront'),
        'activityLoginFailed' => __('Giriş başarısız', 'seviye-storefront'),
        'activityLoginThrottled' => __('Giriş denemesi sınırlandırıldı', 'seviye-storefront'),
        'sessionExpired' => __(
            'Oturum bilgisi güncel değil. Lütfen sayfayı yenileyip tekrar deneyin.',
            'seviye-storefront'
        ),
        'privacyExported' => __('Verileriniz indirildi.', 'seviye-storefront'),
        'privacyDeletionRequested' => __('Silme talebiniz gönderildi.', 'seviye-storefront'),
        'confirmPrivacyDeletion' => __(
            'Hesap kimlik bilgilerinizin silinmesini talep etmek istediğinize emin misiniz?',
            'seviye-storefront'
        ),
        'confirmPrivacyApprove' => __(
            'Bu talebi onaylayıp kullanıcının kimlik bilgilerini anonimleştirmek istediğinize emin misiniz?',
            'seviye-storefront'
        ),
        'privacyResolutionNotePrompt' => __('Sonuç notu (opsiyonel):', 'seviye-storefront'),
        'privacyApproveAction' => __('Onayla ve Anonimleştir', 'seviye-storefront'),
        'privacyRejectAction' => __('Reddet', 'seviye-storefront'),
        'noPrivacyRequests' => __('Bekleyen bir KVKK talebi yok.', 'seviye-storefront'),
        'privacyTypeExport' => __('Veri İhracı', 'seviye-storefront'),
        'privacyTypeDeletion' => __('Silme Talebi', 'seviye-storefront'),
        'privacyStatus_pending' => __('Bekliyor', 'seviye-storefront'),
        'privacyStatus_completed' => __('Tamamlandı', 'seviye-storefront'),
        'privacyStatus_rejected' => __('Reddedildi', 'seviye-storefront'),
        'supplierNoOrders' => __('Size ait bir satın alma siparişi yok.', 'seviye-storefront'),
        'supplierItemsLabel' => __('kalem', 'seviye-storefront'),
        'supplierUnitsLabel' => __('adet', 'seviye-storefront'),
        'supplierMarkShippedAction' => __('Gönderildi Olarak İşaretle', 'seviye-storefront'),
        'supplierMarkedShipped' => __('Kargo bilgisi kaydedildi.', 'seviye-storefront'),
        'supportStatus_open' => __('Bekliyor', 'seviye-storefront'),
        'supportStatus_answered' => __('Yanıtlandı', 'seviye-storefront'),
        'supportStatus_closed' => __('Kapatıldı', 'seviye-storefront'),
        'supportNoTickets' => __('Bir destek talebi yok.', 'seviye-storefront'),
        'supportGenelMerkezLabel' => __('Genel Merkez', 'seviye-storefront'),
        'supportStaffLabel' => __('Personel', 'seviye-storefront'),
        'supportVeliLabel' => __('Veli', 'seviye-storefront'),
        'supportTicketCreated' => __('Destek talebiniz gönderildi.', 'seviye-storefront'),
        'supportTicketClosed' => __('Destek talebi kapatıldı.', 'seviye-storefront'),
        /* translators: %s: education year being promoted from - token replaced client-side (students-panel.js) */
        'confirmPromoteStudents' => __(
            '%s eğitim yılındaki tüm aktif öğrenciler bir sonraki eğitim yılına taşınacak. Bu işlem geri alınamaz. Devam edilsin mi?',
            'seviye-storefront'
        ),
        'promoting' => __('Geçiş uygulanıyor…', 'seviye-storefront'),
        /* translators: 1: promoted count, 2: new year - tokens replaced client-side, see students-panel.js */
        'promoteSummary' => __('%1$d öğrenci %2$s eğitim yılına taşındı.', 'seviye-storefront'),
        'broadcastScheduled' => __('Duyuru zamanlandı.', 'seviye-storefront'),
        'broadcastNoScheduled' => __('Zamanlanmış bir duyuru yok.', 'seviye-storefront'),
        'broadcastCancelScheduled' => __('İptal Et', 'seviye-storefront'),
        'broadcastScheduledStatus_pending' => __('Bekliyor', 'seviye-storefront'),
        'broadcastScheduledStatus_sent' => __('Gönderildi', 'seviye-storefront'),
        'broadcastScheduledStatus_cancelled' => __('İptal Edildi', 'seviye-storefront'),
        'commandPalettePlaceholder' => __('Bir bölüme git…', 'seviye-storefront'),
        'commandPaletteEmpty' => __('Eşleşme yok.', 'seviye-storefront'),
        'skipToContent' => __('İçeriğe geç', 'seviye-storefront'),
        'openCommandPalette' => __('Bul', 'seviye-storefront'),
    ];

    if (in_array($zone, ['admin', 'sube'], true) && current_user_can('scp_manage_students')) {
        $handle = 'scp-students-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/students-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/students-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', array_merge($localized, [
            'canManageAllBranches' => current_user_can('scp_manage_branches'),
        ]));
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    if ($zone === 'admin' && current_user_can('scp_manage_branches')) {
        $handle = 'scp-branches-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/branches-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/branches-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    if (
        in_array($zone, ['admin', 'sube'], true)
        && (current_user_can('scp_manage_products') || current_user_can('scp_view_products'))
    ) {
        $handle = 'scp-products-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/products-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/products-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', array_merge($localized, [
            'canManageProducts' => current_user_can('scp_manage_products'),
            'canManageAllBranches' => current_user_can('scp_manage_branches'),
        ]));
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    $canViewOrders = current_user_can('scp_view_orders') || current_user_can('scp_view_own_branch_orders');
    $isAdminOrdersPage = in_array($zone, ['admin', 'sube'], true)
        && rtrim((string) get_query_var('scp_zone_path'), '/') === 'siparisler';

    if ($isAdminOrdersPage && $canViewOrders) {
        $handle = 'scp-admin-orders-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/admin-orders-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/admin-orders-panel.js'),
            true
        );
        $canCancelOrders = current_user_can('scp_cancel_orders') || current_user_can('scp_cancel_own_branch_orders');

        wp_localize_script($handle, 'scpPanel', array_merge($localized, [
            'canViewAllBranches' => current_user_can('scp_view_orders'),
            'canCancelOrders' => $canCancelOrders,
            'canRefundOrders' => current_user_can('scp_refund_orders'),
        ]));
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    if (in_array($zone, ['admin', 'sube'], true) && current_user_can('scp_manage_pricing')) {
        $handle = 'scp-pricing-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/pricing-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/pricing-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', array_merge($localized, [
            'canManageAllBranches' => current_user_can('scp_manage_branches'),
            'canManageBasePricing' => current_user_can('scp_manage_base_pricing'),
        ]));
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    if (in_array($zone, ['admin', 'sube'], true) && current_user_can('scp_manage_purchase_orders')) {
        $handle = 'scp-depo-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/depo-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/depo-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    if ($zone === 'admin' && current_user_can('scp_manage_coupons')) {
        $handle = 'scp-coupons-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/coupons-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/coupons-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    $isParentZone = current_user_can('scp_view_own_children') || current_user_can('scp_manage_own_profile');

    if ($zone === 'profilim' && $isParentZone) {
        $handle = 'scp-parent-dashboard';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/parent-dashboard.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/parent-dashboard.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    // "Tedarikçi portalı" - never capability-gated (the closed Role enum
    // has no role for this), reached only via the scp_depo_supplier_id_for_user
    // filter check in inc/access-gate.php - see that filter's docblock in
    // Depo/DepoModule::boot().
    if ($zone === 'tedarikci') {
        $handle = 'scp-supplier-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/supplier-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/supplier-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    if ($zone === 'siparislerim' && $isParentZone) {
        $handle = 'scp-orders-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/orders-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/orders-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    $canViewHakedis = current_user_can('scp_view_hakedis') || current_user_can('scp_view_own_hakedis');

    if (in_array($zone, ['admin', 'sube'], true) && $canViewHakedis) {
        $handle = 'scp-hakedis-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/hakedis-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/hakedis-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', array_merge($localized, [
            'canViewAllBranches' => current_user_can('scp_view_hakedis'),
            'canRecordSettlement' => current_user_can('scp_record_hakedis_settlement'),
        ]));
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    $canViewReports = current_user_can('scp_view_reports') || current_user_can('scp_view_own_reports');

    if (in_array($zone, ['admin', 'sube'], true) && $canViewReports) {
        $handle = 'scp-overview-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/overview-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/overview-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    if (in_array($zone, ['admin', 'sube'], true) && $canViewReports) {
        $handle = 'scp-reports-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/reports-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/reports-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', array_merge($localized, [
            'canViewAllBranches' => current_user_can('scp_view_reports'),
        ]));
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    $canSendBroadcast = current_user_can('scp_send_broadcast') || current_user_can('scp_send_own_branch_broadcast');

    if (in_array($zone, ['admin', 'sube'], true) && $canSendBroadcast) {
        $handle = 'scp-broadcast-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/broadcast-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/broadcast-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', array_merge($localized, [
            'canViewAllBranches' => current_user_can('scp_send_broadcast'),
        ]));
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    // Every logged-in user manages their own account's 2FA, in every zone -
    // unlike every other script above, this one is never capability-gated.
    $handle = 'scp-account-security';
    wp_enqueue_script(
        $handle,
        SCP_THEME_URL . '/assets/js/account-security.js',
        ['scp-api-fetch'],
        scp_asset_version('/assets/js/account-security.js'),
        true
    );
    wp_localize_script($handle, 'scpPanel', $localized);
    wp_localize_script($handle, 'scpPanelText', $text);

    // "KVKK: veri ihracı/silme talebi" - self-service, same "never
    // capability-gated" reasoning as account-security.js above; the admin
    // review queue this same script also binds (only rendered in zone.php
    // when scp_manage_privacy_requests is granted) needs the capability
    // flag regardless of zone/page, so it is localized here too rather
    // than only alongside the admin-only queue markup.
    $handle = 'scp-privacy-requests-panel';
    wp_enqueue_script(
        $handle,
        SCP_THEME_URL . '/assets/js/privacy-requests-panel.js',
        ['scp-api-fetch'],
        scp_asset_version('/assets/js/privacy-requests-panel.js'),
        true
    );
    wp_localize_script($handle, 'scpPanel', array_merge($localized, [
        'canManagePrivacyRequests' => current_user_can('scp_manage_privacy_requests'),
    ]));
    wp_localize_script($handle, 'scpPanelText', $text);

    // "Destek Talepleri" - self-service card (scp_submit_support_ticket,
    // only rendered when that capability is held - see
    // templates/partials/support-tickets.php) + staff queue (only when
    // scpPanel.canManageSupportTickets, see templates/zone.php). Enqueued
    // unconditionally like scp-privacy-requests-panel above; the script
    // itself checks for each root element's presence.
    $handle = 'scp-support-tickets-panel';
    wp_enqueue_script(
        $handle,
        SCP_THEME_URL . '/assets/js/support-tickets-panel.js',
        ['scp-api-fetch'],
        scp_asset_version('/assets/js/support-tickets-panel.js'),
        true
    );
    wp_localize_script($handle, 'scpPanel', array_merge($localized, [
        'canManageSupportTickets' => current_user_can('scp_manage_support_tickets'),
    ]));
    wp_localize_script($handle, 'scpPanelText', $text);

    if ($zone === 'admin' && current_user_can('scp_manage_security_settings')) {
        $handle = 'scp-ip-allowlist-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/ip-allowlist-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/ip-allowlist-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    if ($zone === 'admin' && current_user_can('scp_manage_notification_settings')) {
        $handle = 'scp-notifications-settings-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/notifications-settings-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/notifications-settings-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    if ($zone === 'admin' && current_user_can('scp_manage_notification_settings')) {
        $handle = 'scp-email-settings-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/email-settings-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/email-settings-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    if ($zone === 'admin' && current_user_can('scp_manage_api_keys')) {
        $handle = 'scp-api-keys-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/api-keys-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/api-keys-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    if ($zone === 'admin' && current_user_can('scp_manage_core_settings')) {
        $handle = 'scp-branding-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/branding-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/branding-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    if ($zone === 'admin' && current_user_can('scp_view_audit_logs')) {
        $handle = 'scp-activity-log-panel';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/activity-log-panel.js',
            ['scp-api-fetch'],
            scp_asset_version('/assets/js/activity-log-panel.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }

    // Every logged-in user reads their own panel-içi bildirimler, in every
    // zone (and on WooCommerce shop/product pages, via header.php) - like
    // account-security.js, never capability-gated.
    $handle = 'scp-notifications-bell';
    wp_enqueue_script(
        $handle,
        SCP_THEME_URL . '/assets/js/notifications-bell.js',
        ['scp-api-fetch'],
        scp_asset_version('/assets/js/notifications-bell.js'),
        true
    );
    wp_localize_script($handle, 'scpPanel', $localized);
    wp_localize_script($handle, 'scpPanelText', $text);

    if (function_exists('is_product') && is_product() && current_user_can('scp_view_own_children')) {
        $handle = 'scp-product-student-picker';
        wp_enqueue_script(
            $handle,
            SCP_THEME_URL . '/assets/js/product-student-picker.js',
            [],
            scp_asset_version('/assets/js/product-student-picker.js'),
            true
        );
        wp_localize_script($handle, 'scpPanel', $localized);
        wp_localize_script($handle, 'scpPanelText', $text);
    }
}

function scp_enqueue_auth_assets(): void
{
    wp_enqueue_style(
        'scp-auth',
        SCP_THEME_URL . '/assets/css/auth.css',
        [],
        scp_asset_version('/assets/css/auth.css')
    );
    wp_enqueue_script(
        'scp-auth',
        SCP_THEME_URL . '/assets/js/auth.js',
        [],
        scp_asset_version('/assets/js/auth.js'),
        true
    );

    wp_localize_script('scp-auth', 'scpAuth', [
        'restUrl' => esc_url_raw(rest_url('seviye/v1/auth/')),
        'token' => scp_requested_password_token(),
    ]);

    wp_localize_script('scp-auth', 'scpAuthText', [
        'invalidCredentials' => __('T.C. Kimlik No veya şifre hatalı.', 'seviye-storefront'),
        'throttled' => __('Çok fazla deneme yaptınız. Lütfen birkaç dakika sonra tekrar deneyin.', 'seviye-storefront'),
        'networkError' => __('Bağlantı hatası. Lütfen tekrar deneyin.', 'seviye-storefront'),
        'genericError' => __('Bir hata oluştu. Lütfen tekrar deneyin.', 'seviye-storefront'),
        'weakPassword' => __('Şifre en az 8 karakter olmalıdır.', 'seviye-storefront'),
        'invalidToken' => __('Bağlantının süresi dolmuş veya geçersiz. Lütfen tekrar talep edin.', 'seviye-storefront'),
        'passwordMismatch' => __('Şifreler eşleşmiyor.', 'seviye-storefront'),
        'resetLinkSent' => __('T.C. Kimlik No sistemde kayıtlıysa, bağlantı gönderildi.', 'seviye-storefront'),
        'passwordSet' => __('Şifreniz oluşturuldu. Giriş ekranına yönlendiriliyorsunuz...', 'seviye-storefront'),
        'invalidCode' => __('Kod hatalı.', 'seviye-storefront'),
        'twoFactorSessionExpired' => __('Doğrulama süresi doldu, lütfen tekrar giriş yapın.', 'seviye-storefront'),
    ]);
}

function scp_requested_password_token(): string
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route param, not a state-changing form submission; the token itself is verified (and single-use consumed) by PasswordTokenService, not trusted here.
    if (!isset($_GET['scp_token'])) {
        return '';
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
    return sanitize_text_field(wp_unslash($_GET['scp_token']));
}
