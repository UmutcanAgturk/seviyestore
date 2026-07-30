<?php

declare(strict_types=1);

namespace Seviye\Security\Http\Admin;

use Seviye\Core\Rbac\Role;
use Seviye\Security\Auth\TcNumber;
use Seviye\Security\Identity\IdentityGatewayInterface;
use WP_User;

/**
 * Backs both "Seviye Kullanıcılar" (every non-administrator WP user) and
 * "Veli" (the same table, filtered to Role::VELI) - one class, one
 * role filter parameter, since the two are the same listing/edit/delete/
 * create feature at different scopes, not two different features. Also
 * the only place in wp-admin a brand-new WP user can be created without
 * native `create_users` - Genel Merkez never had that capability, so
 * before this page existed there was genuinely no way to onboard a new
 * person (existing-user edit alone, or WordPress' own "Kullanıcılar" -
 * unreachable to Genel Merkez - were the only options). Not unit tested,
 * same as every other WordPress-touching adapter in this codebase (see
 * docs/ARCHITECTURE.md, "Test stratejisi").
 */
final class UserListPage
{
    public const SLUG_ALL = 'scp-kullanicilar';
    public const SLUG_VELI = 'scp-kullanicilar-veli';
    private const NONCE_ACTION = 'scp_user_list';
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(private readonly IdentityGatewayInterface $identities)
    {
    }

    public function registerActions(): void
    {
        add_action('admin_post_scp_create_user', [$this, 'handleCreate']);
        add_action('admin_post_scp_save_user_details', [$this, 'handleSave']);
        add_action('admin_post_scp_delete_user', [$this, 'handleDelete']);
    }

    public function renderAll(): void
    {
        $this->render(null, self::SLUG_ALL, __('Seviye Kullanıcılar', 'seviye-security'));
    }

    public function renderVeli(): void
    {
        $this->render(Role::VELI, self::SLUG_VELI, __('Veli', 'seviye-security'));
    }

    private function render(?Role $roleFilter, string $slug, string $title): void
    {
        if (!AdminAccess::current()) {
            wp_die(esc_html__('Bu sayfayı görüntüleme yetkiniz yok.', 'seviye-security'));
        }

        $notice = $this->readNotice();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>

            <?php if ($notice !== null) : ?>
                <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
                    <p><?php echo esc_html($notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e('Yeni Kullanıcı Ekle', 'seviye-security'); ?></h2>
            <?php $this->renderCreateForm($slug, $roleFilter); ?>

            <h2><?php esc_html_e('Mevcut Kullanıcılar', 'seviye-security'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Ad / E-posta', 'seviye-security'); ?></th>
                        <th><?php esc_html_e('Seviye Rolü', 'seviye-security'); ?></th>
                        <th><?php esc_html_e('T.C. Kimlik No', 'seviye-security'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->users($roleFilter) as $user) : ?>
                        <?php $this->renderRow($user, $slug); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
        function scpGenerateUserListPassword(fieldId) {
            var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789#!?%';
            var randomValues = new Uint32Array(14);
            window.crypto.getRandomValues(randomValues);
            var password = '';
            for (var i = 0; i < randomValues.length; i++) {
                password += alphabet[randomValues[i] % alphabet.length];
            }
            document.getElementById(fieldId).value = password;
        }
        </script>
        <?php
    }

    private function renderCreateForm(string $slug, ?Role $roleFilter): void
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width: 480px;">
            <?php wp_nonce_field(self::NONCE_ACTION); ?>
            <input type="hidden" name="action" value="scp_create_user">
            <input type="hidden" name="redirect_slug" value="<?php echo esc_attr($slug); ?>">
            <table class="form-table">
                <tr>
                    <th><label for="scp_new_display_name"><?php esc_html_e('Ad Soyad', 'seviye-security'); ?></label></th>
                    <td><input type="text" id="scp_new_display_name" name="display_name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="scp_new_email"><?php esc_html_e('E-posta', 'seviye-security'); ?></label></th>
                    <td><input type="email" id="scp_new_email" name="email" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="scp_new_role"><?php esc_html_e('Seviye Rolü', 'seviye-security'); ?></label></th>
                    <td>
                        <select id="scp_new_role" name="role" required>
                            <option value=""><?php esc_html_e('— seçin —', 'seviye-security'); ?></option>
                            <?php foreach (Role::cases() as $role) : ?>
                                <option
                                    value="<?php echo esc_attr($role->value); ?>"
                                    <?php selected($roleFilter === $role); ?>
                                ><?php echo esc_html($role->label()); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="scp_new_tc_no"><?php esc_html_e('T.C. Kimlik No', 'seviye-security'); ?></label></th>
                    <td>
                        <input
                            type="text"
                            id="scp_new_tc_no"
                            name="tc_no"
                            maxlength="11"
                            pattern="[0-9]{11}"
                            class="regular-text"
                            required
                        >
                        <p class="description">
                            <?php esc_html_e(
                                'Zorunlu: kullanıcının Seviye giriş ekranından giriş yapabilmesi için gerekli.',
                                'seviye-security'
                            ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="scp_new_password"><?php esc_html_e('Şifre', 'seviye-security'); ?></label></th>
                    <td>
                        <input type="text" id="scp_new_password" name="password" class="regular-text" required>
                        <button
                            type="button"
                            class="button"
                            onclick="scpGenerateUserListPassword('scp_new_password')"
                        ><?php esc_html_e('Rastgele oluştur', 'seviye-security'); ?></button>
                    </td>
                </tr>
            </table>
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Kullanıcıyı Oluştur', 'seviye-security'); ?>
            </button>
        </form>
        <?php
    }

    private function renderRow(WP_User $user, string $slug): void
    {
        $currentRole = $this->currentSeviyeRole($user);
        $currentTcNo = $this->identities->findTcNumberByUserId($user->ID);
        $deleteConfirm = __('Bu kullanıcıyı kalıcı olarak silmek istediğinize emin misiniz?', 'seviye-security');

        ?>
        <tr>
            <td>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field(self::NONCE_ACTION); ?>
                    <input type="hidden" name="action" value="scp_save_user_details">
                    <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user->ID); ?>">
                    <input type="hidden" name="redirect_slug" value="<?php echo esc_attr($slug); ?>">
                    <input
                        type="text"
                        name="display_name"
                        value="<?php echo esc_attr($user->display_name); ?>"
                        required
                    ><br>
                    <input
                        type="email"
                        name="email"
                        value="<?php echo esc_attr($user->user_email); ?>"
                        required
                    ><br>
                    <button type="submit" class="button">
                        <?php esc_html_e('Kaydet', 'seviye-security'); ?>
                    </button>
                </form>
            </td>
            <td><?php echo $currentRole !== null ? esc_html($currentRole->label()) : '—'; ?></td>
            <td><?php echo $currentTcNo !== null ? esc_html($currentTcNo->value()) : '—'; ?></td>
            <td>
                <form
                    method="post"
                    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    onsubmit="return confirm('<?php echo esc_js($deleteConfirm); ?>');"
                >
                    <?php wp_nonce_field(self::NONCE_ACTION); ?>
                    <input type="hidden" name="action" value="scp_delete_user">
                    <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user->ID); ?>">
                    <input type="hidden" name="redirect_slug" value="<?php echo esc_attr($slug); ?>">
                    <button type="submit" class="button-link-delete">
                        <?php esc_html_e('Sil', 'seviye-security'); ?>
                    </button>
                </form>
            </td>
        </tr>
        <?php
    }

    public function handleCreate(): void
    {
        check_admin_referer(self::NONCE_ACTION);

        if (!AdminAccess::current()) {
            wp_die(esc_html__('Bu işlem için yetkiniz yok.', 'seviye-security'));
        }

        $redirectSlug = $this->redirectSlug();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $displayName = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $roleValue = isset($_POST['role']) ? sanitize_key(wp_unslash($_POST['role'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $tcNoInput = isset($_POST['tc_no']) ? trim(sanitize_text_field(wp_unslash($_POST['tc_no']))) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $passwordInput = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';

        if ($displayName === '' || $email === '' || !is_email($email)) {
            $this->redirectWithNotice($redirectSlug, 'error', __('Ad ve geçerli bir e-posta gerekli.', 'seviye-security'));
        }

        if (email_exists($email) !== false) {
            $this->redirectWithNotice($redirectSlug, 'error', __('Bu e-posta zaten kayıtlı.', 'seviye-security'));
        }

        $role = Role::tryFrom($roleValue);

        if ($role === null) {
            $this->redirectWithNotice($redirectSlug, 'error', __('Geçerli bir Seviye rolü seçin.', 'seviye-security'));
        }

        if (mb_strlen($passwordInput) < self::MIN_PASSWORD_LENGTH) {
            $this->redirectWithNotice(
                $redirectSlug,
                'error',
                sprintf(
                    /* translators: %d: minimum password length */
                    __('Şifre en az %d karakter olmalı.', 'seviye-security'),
                    self::MIN_PASSWORD_LENGTH
                )
            );
        }

        if ($tcNoInput === '') {
            $this->redirectWithNotice(
                $redirectSlug,
                'error',
                __('T.C. Kimlik No zorunlu - girilmezse kullanıcı giriş yapamaz.', 'seviye-security')
            );
        }

        if (!TcNumber::isValid($tcNoInput)) {
            $this->redirectWithNotice($redirectSlug, 'error', __('Geçersiz T.C. Kimlik No.', 'seviye-security'));
        }

        $tcNumber = TcNumber::fromString($tcNoInput);

        if ($this->identities->findUserIdByTcNumber($tcNumber) !== null) {
            $this->redirectWithNotice(
                $redirectSlug,
                'error',
                __('Bu T.C. Kimlik No zaten başka bir kullanıcıya bağlı.', 'seviye-security')
            );
        }

        $userId = wp_insert_user([
            'user_login' => $this->uniqueLoginFor($email),
            'user_email' => $email,
            'user_pass' => $passwordInput,
            'display_name' => $displayName,
            'role' => $role->value,
        ]);

        if (is_wp_error($userId)) {
            $this->redirectWithNotice($redirectSlug, 'error', $userId->get_error_message());
        }

        $this->identities->link($tcNumber, (int) $userId);

        $this->redirectWithNotice(
            $redirectSlug,
            'success',
            sprintf(
                /* translators: %s: the new plaintext password, shown once so it can be handed to the user */
                __('Kullanıcı oluşturuldu. Şifre: %s — bu şifreyi ilgili kişiye iletin, sayfa yenilendiğinde bir daha gösterilmeyecek.', 'seviye-security'),
                $passwordInput
            )
        );
    }

    /**
     * WordPress requires a unique username distinct from the email
     * address field - derives one from the email's local part and
     * disambiguates with a numeric suffix on collision.
     */
    private function uniqueLoginFor(string $email): string
    {
        $base = sanitize_user(strstr($email, '@', true) ?: $email, true);
        $base = $base !== '' ? $base : 'kullanici';
        $login = $base;
        $suffix = 2;

        while (username_exists($login)) {
            $login = $base . '-' . $suffix;
            $suffix++;
        }

        return $login;
    }

    public function handleSave(): void
    {
        check_admin_referer(self::NONCE_ACTION);

        if (!AdminAccess::current()) {
            wp_die(esc_html__('Bu işlem için yetkiniz yok.', 'seviye-security'));
        }

        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $user = get_userdata($userId);
        $redirectSlug = $this->redirectSlug();

        if ($user === false || in_array('administrator', $user->roles, true)) {
            $this->redirectWithNotice($redirectSlug, 'error', __('Geçersiz kullanıcı.', 'seviye-security'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $displayName = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

        if ($displayName === '' || $email === '' || !is_email($email)) {
            $this->redirectWithNotice($redirectSlug, 'error', __('Ad ve geçerli bir e-posta gerekli.', 'seviye-security'));
        }

        $existingByEmail = email_exists($email);

        if ($existingByEmail !== false && (int) $existingByEmail !== $userId) {
            $this->redirectWithNotice(
                $redirectSlug,
                'error',
                __('Bu e-posta zaten başka bir kullanıcıya ait.', 'seviye-security')
            );
        }

        wp_update_user(['ID' => $userId, 'display_name' => $displayName, 'user_email' => $email]);

        $this->redirectWithNotice($redirectSlug, 'success', __('Kaydedildi.', 'seviye-security'));
    }

    public function handleDelete(): void
    {
        check_admin_referer(self::NONCE_ACTION);

        if (!AdminAccess::current()) {
            wp_die(esc_html__('Bu işlem için yetkiniz yok.', 'seviye-security'));
        }

        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $user = get_userdata($userId);
        $redirectSlug = $this->redirectSlug();

        if ($user === false || in_array('administrator', $user->roles, true)) {
            $this->redirectWithNotice($redirectSlug, 'error', __('Geçersiz kullanıcı.', 'seviye-security'));
        }

        // Security's own table has no FK to wp_users (see
        // CreateUserIdentitiesTable's docblock) - clean up the identity
        // link ourselves before deleting. Other modules' scp_* rows that
        // reference this user_id (scp_student_parents, scp_branch_users,
        // ...) are outside Security's authority to touch (see
        // docs/ARCHITECTURE.md, "Kural") and are NOT cleaned up here.
        $this->identities->unlink($userId);

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        wp_delete_user($userId);

        $this->redirectWithNotice($redirectSlug, 'success', __('Kullanıcı silindi.', 'seviye-security'));
    }

    private function currentSeviyeRole(WP_User $user): ?Role
    {
        foreach (Role::cases() as $role) {
            if (in_array($role->value, $user->roles, true)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * @return list<WP_User>
     */
    private function users(?Role $roleFilter): array
    {
        $args = ['orderby' => 'display_name', 'order' => 'ASC'];

        if ($roleFilter !== null) {
            $args['role'] = $roleFilter->value;
        }

        $users = get_users($args);

        return array_values(array_filter(
            $users,
            static fn (WP_User $user): bool => !in_array('administrator', $user->roles, true)
        ));
    }

    private function redirectSlug(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer(), not a state-changing read.
        $slug = isset($_POST['redirect_slug']) ? sanitize_key(wp_unslash($_POST['redirect_slug'])) : self::SLUG_ALL;

        return in_array($slug, [self::SLUG_ALL, self::SLUG_VELI], true) ? $slug : self::SLUG_ALL;
    }

    private function redirectWithNotice(string $slug, string $type, string $message): void
    {
        set_transient(
            'scp_user_list_notice_' . get_current_user_id(),
            ['type' => $type, 'message' => $message],
            30
        );

        wp_safe_redirect(admin_url('admin.php?page=' . $slug));

        exit;
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function readNotice(): ?array
    {
        $key = 'scp_user_list_notice_' . get_current_user_id();
        $notice = get_transient($key);

        if (!is_array($notice)) {
            return null;
        }

        delete_transient($key);

        return $notice;
    }
}
