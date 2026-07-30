<?php

/**
 * One-click setup: bundles all 11 Seviye plugin zips inside the theme
 * itself (`inc/bundled-plugins/*.zip`, built via `composer install
 * --no-dev` the same way task "Package all plugins as installable
 * WordPress zips" did) so activating this theme alone is enough to bring
 * up the whole platform - the end user never has to hunt down 11 separate
 * plugin zips.
 *
 * Deliberately procedural and hand-rolled, not the TGMPA library many
 * themes use for "required plugins" - TGMPA is built for the general case
 * (arbitrary sources, version checks, a bulk-upgrader UI, multisite,
 * "recommended vs required"...). This platform is a closed set of exactly
 * 11 known, privately-owned plugins with an already-fixed, already
 * documented activation order (see root README.md) - a purpose-built,
 * much smaller installer is the same "avoid a heavy dependency, hand-roll
 * a minimal but genuinely valid implementation" call this codebase already
 * made for TOTP (Security) and the XLSX writer (Reports).
 *
 * WooCommerce is the one exception: it is fetched live from wordpress.org's
 * own official download endpoint via WP core's own Plugin_Upgrader - the
 * exact same mechanism clicking "Install Now" in wp-admin uses - rather
 * than bundled, since it is a large, independently-updated third-party
 * plugin this platform does not own or redistribute.
 *
 * Each step is driven by its own AJAX round-trip (not one long blocking
 * request) specifically so a slow WooCommerce download from wordpress.org
 * can never trigger a PHP execution timeout on the "install everything"
 * click - see assets/js/setup-wizard.js.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SCP_BUNDLED_PLUGINS_DIR', SCP_THEME_DIR . '/inc/bundled-plugins');

/**
 * A fixed, documented demo T.C. Kimlik No (passes the exact checksum
 * algorithm Seviye\Security\Auth\TcNumber::isValid() enforces) and
 * password for the platform's first Genel Merkez account - provisioned by
 * the setup wizard's last step so the person running it has real
 * credentials to log into the SITE (not wp-admin) with immediately. This
 * is a demo/bootstrap credential, not a real citizen's ID - meant to be
 * rotated (via "Şifremi Unuttum" once e-posta bildirimleri gerçek bir posta
 * sunucusuna bağlıysa, ya da wp-admin > Kullanıcılar üzerinden) right after
 * first login.
 */
const SCP_DEMO_ADMIN_TC_NO = '19000000156';
const SCP_DEMO_ADMIN_PASSWORD = 'Sv#Kurulum2026!Merkez';

/**
 * @return list<array{id: string, label: string, type: string, slug?: string, file?: string}>
 */
function scp_setup_steps(): array
{
    return [
        scp_bundled_setup_step('wporg', 'woocommerce', 'WooCommerce', 'woocommerce/woocommerce.php'),
        scp_bundled_setup_step('bundled', 'seviye-core', 'Seviye Core', 'seviye-core/seviye-core.php'),
        scp_bundled_setup_step('bundled', 'seviye-security', 'Seviye Security', 'seviye-security/seviye-security.php'),
        scp_bundled_setup_step('bundled', 'seviye-branches', 'Seviye Branches', 'seviye-branches/seviye-branches.php'),
        scp_bundled_setup_step('bundled', 'seviye-students', 'Seviye Students', 'seviye-students/seviye-students.php'),
        scp_bundled_setup_step('bundled', 'seviye-parents', 'Seviye Parents', 'seviye-parents/seviye-parents.php'),
        scp_bundled_setup_step('bundled', 'seviye-pricing', 'Seviye Pricing', 'seviye-pricing/seviye-pricing.php'),
        scp_bundled_setup_step('bundled', 'seviye-commerce', 'Seviye Commerce', 'seviye-commerce/seviye-commerce.php'),
        scp_bundled_setup_step('bundled', 'seviye-finance', 'Seviye Finance', 'seviye-finance/seviye-finance.php'),
        scp_bundled_setup_step('bundled', 'seviye-reports', 'Seviye Reports', 'seviye-reports/seviye-reports.php'),
        scp_bundled_setup_step(
            'bundled',
            'seviye-notifications',
            'Seviye Notifications',
            'seviye-notifications/seviye-notifications.php'
        ),
        scp_bundled_setup_step('bundled', 'seviye-api', 'Seviye API', 'seviye-api/seviye-api.php'),
        ['id' => 'demo-admin', 'label' => 'Genel Merkez hesabı', 'type' => 'demo-admin'],
    ];
}

/**
 * @return array{id: string, label: string, type: string, slug: string, file: string}
 */
function scp_bundled_setup_step(string $type, string $slug, string $label, string $pluginFile): array
{
    return ['id' => $slug, 'label' => $label, 'type' => $type, 'slug' => $slug, 'file' => $pluginFile];
}

function scp_find_setup_step(string $id): ?array
{
    foreach (scp_setup_steps() as $step) {
        if ($step['id'] === $id) {
            return $step;
        }
    }

    return null;
}

add_action('after_switch_theme', 'scp_flag_needs_setup');

function scp_flag_needs_setup(): void
{
    set_transient('scp_redirect_to_setup', true, MINUTE_IN_SECONDS);
}

/**
 * The standard "redirect once, right after activation" pattern (WooCommerce
 * itself does the same) - a transient rather than a permanent option so a
 * later, unrelated visit to wp-admin never redirects again.
 */
add_action('admin_init', 'scp_maybe_redirect_to_setup');

function scp_maybe_redirect_to_setup(): void
{
    if (!get_transient('scp_redirect_to_setup')) {
        return;
    }

    delete_transient('scp_redirect_to_setup');

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only presence check for a WP core admin-generated query param, not a form submission.
    if (wp_doing_ajax() || !current_user_can('install_plugins') || isset($_GET['activate-multi'])) {
        return;
    }

    wp_safe_redirect(admin_url('admin.php?page=scp-kurulum'));

    exit;
}

add_action('admin_menu', 'scp_register_setup_page');

function scp_register_setup_page(): void
{
    add_menu_page(
        __('Seviye Kurulum', 'seviye-storefront'),
        __('Seviye Kurulum', 'seviye-storefront'),
        'install_plugins',
        'scp-kurulum',
        'scp_render_setup_page',
        'dashicons-admin-plugins',
        3
    );
}

function scp_render_setup_page(): void
{
    ?>
    <div class="wrap scp-setup-wrap">
        <h1><?php esc_html_e('Seviye Commerce Platform Kurulumu', 'seviye-storefront'); ?></h1>
        <p>
            <?php esc_html_e(
                'Bu sihirbaz WooCommerce\'i ve platformun 11 Seviye eklentisini sırasıyla kurup etkinleştirir.',
                'seviye-storefront'
            ); ?>
        </p>
        <p>
            <?php esc_html_e('WooCommerce yüklü değilse wordpress.org\'dan indirilir.', 'seviye-storefront'); ?>
            <?php esc_html_e('Seviye eklentileri bu temayla birlikte gelir.', 'seviye-storefront'); ?>
            <?php esc_html_e('Son adımda ilk Genel Merkez hesabı oluşturulur.', 'seviye-storefront'); ?>
        </p>

        <button type="button" id="scp-setup-start" class="button button-primary">
            <?php esc_html_e('Kurulumu Başlat', 'seviye-storefront'); ?>
        </button>

        <ol id="scp-setup-steps" class="scp-setup-steps">
            <?php foreach (scp_setup_steps() as $step) : ?>
                <li data-step="<?php echo esc_attr($step['id']); ?>" class="scp-setup-steps__item">
                    <span class="scp-setup-steps__label"><?php echo esc_html($step['label']); ?></span>
                    <span class="scp-setup-steps__status" data-status="pending">
                        <?php esc_html_e('Bekliyor', 'seviye-storefront'); ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ol>

        <div id="scp-setup-credentials" class="scp-setup-credentials" hidden>
            <h2><?php esc_html_e('Genel Merkez giriş bilgileri', 'seviye-storefront'); ?></h2>
            <p><?php esc_html_e('Bu bilgiler yalnızca burada gösterilir, hemen not edin.', 'seviye-storefront'); ?></p>
            <table class="widefat" style="max-width: 480px;">
                <tbody>
                    <tr>
                        <th><?php esc_html_e('T.C. Kimlik No', 'seviye-storefront'); ?></th>
                        <td><code data-scp-credential="tc_no"></code></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Şifre', 'seviye-storefront'); ?></th>
                        <td><code data-scp-credential="password"></code></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

add_action('admin_enqueue_scripts', 'scp_enqueue_setup_assets');

function scp_enqueue_setup_assets(string $hook): void
{
    if ($hook !== 'toplevel_page_scp-kurulum') {
        return;
    }

    wp_enqueue_style('scp-setup', SCP_THEME_URL . '/assets/css/setup.css', [], SCP_THEME_VERSION);
    wp_enqueue_script('scp-setup', SCP_THEME_URL . '/assets/js/setup-wizard.js', [], SCP_THEME_VERSION, true);

    wp_localize_script('scp-setup', 'scpSetup', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('scp_setup'),
        'steps' => wp_list_pluck(scp_setup_steps(), 'id'),
    ]);

    wp_localize_script('scp-setup', 'scpSetupText', [
        'running' => __('Kuruluyor...', 'seviye-storefront'),
        'done' => __('Tamamlandı', 'seviye-storefront'),
        'failed' => __('Hata', 'seviye-storefront'),
        'genericError' => __('Bağlantı hatası.', 'seviye-storefront'),
        'allDone' => __('Kurulum tamamlandı.', 'seviye-storefront'),
    ]);
}

add_action('wp_ajax_scp_setup_step', 'scp_handle_setup_step');

function scp_handle_setup_step(): void
{
    check_ajax_referer('scp_setup', 'nonce');

    if (!current_user_can('install_plugins')) {
        wp_send_json_error(['message' => __('Bu işlem için yetkiniz yok.', 'seviye-storefront')], 403);
    }

    $stepId = isset($_POST['step']) ? sanitize_key((string) wp_unslash($_POST['step'])) : '';
    $step = scp_find_setup_step($stepId);

    if ($step === null) {
        wp_send_json_error(['message' => __('Geçersiz kurulum adımı.', 'seviye-storefront')], 400);
    }

    try {
        $result = scp_run_setup_step($step);
    } catch (\Throwable $exception) {
        // A raw PHP fatal here (uncaught in a module's own activation code)
        // would otherwise surface as an opaque HTTP 500 with no JSON body -
        // exactly what admin-ajax.php returns on an uncaught exception, and
        // exactly what setup-wizard.js's generic "Bağlantı hatası" message
        // means. Catching \Throwable (covers both Exception and Error -
        // TypeError, undefined method calls, ...) turns that into a real,
        // visible message instead - the one class of failure this can't
        // catch is a genuinely unrecoverable fatal (memory exhaustion, a
        // parse error), which stays a raw 500.
        wp_send_json_error([
            'message' => sprintf(
                /* translators: 1: exception class, 2: exception message, 3: file, 4: line */
                __('%1$s: %2$s (%3$s:%4$d)', 'seviye-storefront'),
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ),
        ], 500);
    }

    if (!$result['success']) {
        wp_send_json_error($result);
    }

    wp_send_json_success($result);
}

/**
 * @param array{id: string, label: string, type: string, slug?: string, file?: string} $step
 * @return array{success: bool, message?: string, credentials?: array{tc_no: string, password: string}}
 */
function scp_run_setup_step(array $step): array
{
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    if ($step['type'] === 'demo-admin') {
        return scp_provision_demo_admin();
    }

    $pluginFile = $step['file'];

    if (!file_exists(WP_PLUGIN_DIR . '/' . $pluginFile)) {
        $installed = $step['type'] === 'bundled'
            ? scp_install_bundled_plugin($step['slug'])
            : scp_install_from_wordpress_org($step['slug']);

        if (!$installed['success']) {
            return $installed;
        }
    }

    if (is_plugin_active($pluginFile)) {
        return ['success' => true, 'message' => sprintf(
            /* translators: %s: plugin/module label */
            __('%s zaten etkindi.', 'seviye-storefront'),
            $step['label']
        )];
    }

    $activated = activate_plugin($pluginFile);

    if (is_wp_error($activated)) {
        return ['success' => false, 'message' => $activated->get_error_message()];
    }

    return ['success' => true, 'message' => sprintf(
        /* translators: %s: plugin/module label */
        __('%s kuruldu ve etkinleştirildi.', 'seviye-storefront'),
        $step['label']
    )];
}

/**
 * @return array{success: bool, message?: string}
 */
function scp_install_bundled_plugin(string $slug): array
{
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $zipPath = SCP_BUNDLED_PLUGINS_DIR . '/' . $slug . '.zip';

    if (!file_exists($zipPath)) {
        return ['success' => false, 'message' => sprintf(
            /* translators: %s: plugin slug */
            __('%s.zip pakette bulunamadı.', 'seviye-storefront'),
            $slug
        )];
    }

    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    $result = $upgrader->install($zipPath);

    return scp_upgrader_result_to_step_result($result, $slug);
}

/**
 * @return array{success: bool, message?: string}
 */
function scp_install_from_wordpress_org(string $slug): array
{
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    $result = $upgrader->install('https://downloads.wordpress.org/plugin/' . $slug . '.latest-stable.zip');

    return scp_upgrader_result_to_step_result($result, $slug);
}

/**
 * @return array{success: bool, message?: string}
 */
function scp_upgrader_result_to_step_result(mixed $result, string $slug): array
{
    if (is_wp_error($result)) {
        return ['success' => false, 'message' => $result->get_error_message()];
    }

    if ($result !== true) {
        return ['success' => false, 'message' => sprintf(
            /* translators: %s: plugin slug */
            __('%s kurulumu başarısız oldu.', 'seviye-storefront'),
            $slug
        )];
    }

    return ['success' => true];
}

/**
 * @return array{success: bool, message?: string, credentials?: array{tc_no: string, password: string}}
 */
function scp_provision_demo_admin(): array
{
    if (get_option('scp_demo_admin_created')) {
        return ['success' => true, 'message' => __('Genel Merkez hesabı zaten oluşturulmuştu.', 'seviye-storefront')];
    }

    $coreAndSecurityActive = class_exists(\Seviye\Core\Plugin::class)
        && class_exists(\Seviye\Security\Identity\WpdbIdentityGateway::class);

    if (!$coreAndSecurityActive) {
        return ['success' => false, 'message' => __(
            'Seviye Core/Security henüz etkin değil - önceki adımların tamamlanmasını bekleyin.',
            'seviye-storefront'
        )];
    }

    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    $email = 'genel-merkez+demo@' . ($host !== null && $host !== '' ? $host : 'example.com');

    $userId = wp_insert_user([
        'user_login' => 'genel-merkez-demo',
        'user_pass' => SCP_DEMO_ADMIN_PASSWORD,
        'user_email' => $email,
        'display_name' => __('Genel Merkez (Demo)', 'seviye-storefront'),
        'role' => 'scp_genel_merkez',
    ]);

    if (is_wp_error($userId)) {
        return ['success' => false, 'message' => $userId->get_error_message()];
    }

    $container = \Seviye\Core\Plugin::instance()->container();
    $identities = $container->get(\Seviye\Security\Identity\IdentityGatewayInterface::class);
    $identities->link(\Seviye\Security\Auth\TcNumber::fromString(SCP_DEMO_ADMIN_TC_NO), (int) $userId);

    update_option('scp_demo_admin_created', true);

    return [
        'success' => true,
        'message' => __('Genel Merkez hesabı oluşturuldu.', 'seviye-storefront'),
        'credentials' => [
            'tc_no' => SCP_DEMO_ADMIN_TC_NO,
            'password' => SCP_DEMO_ADMIN_PASSWORD,
        ],
    ];
}
