<?php

declare(strict_types=1);

namespace Seviye\Security\Http\Admin;

use Seviye\Core\Rbac\Role;
use Seviye\Security\Auth\TcNumber;
use Seviye\Security\Identity\IdentityGatewayInterface;
use WP_User;

/**
 * Native wp-admin counterpart to the theme's custom /admin, /sube panels:
 * a "Seviye Yetkilendirme" page under the "Seviye Kullanıcılar" top-level
 * menu (assign a Seviye role, link a T.C. Kimlik No, set a password for
 * any WP user), plus a T.C. Kimlik No field on WordPress' own
 * "Kullanıcıyı Düzenle" screen. Gated on {@see AdminAccess} - reachable by
 * both Genel Merkez staff and the site's real WordPress administrator; see
 * {@see SeviyeUsersMenu} for the menu tree this page lives in. Not unit
 * tested, same as every other WordPress-touching adapter in this codebase
 * (see docs/ARCHITECTURE.md, "Test stratejisi").
 */
final class UserAuthorizationAdminPage
{
    public const MENU_SLUG = 'scp-yetkilendirme';
    private const NONCE_ACTION = 'scp_user_authorization';
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(private readonly IdentityGatewayInterface $identities)
    {
    }

    public function registerActions(): void
    {
        add_action('admin_post_scp_save_authorization', [$this, 'handleSave']);
        add_action('admin_post_scp_unlink_tc_no', [$this, 'handleUnlink']);

        add_action('show_user_profile', [$this, 'renderProfileField']);
        add_action('edit_user_profile', [$this, 'renderProfileField']);
        add_action('user_profile_update_errors', [$this, 'validateProfileField'], 10, 3);
        add_action('personal_options_update', [$this, 'saveProfileField']);
        add_action('edit_user_profile_update', [$this, 'saveProfileField']);
    }

    public function render(): void
    {
        if (!AdminAccess::current()) {
            wp_die(esc_html__('Bu sayfayı görüntüleme yetkiniz yok.', 'seviye-security'));
        }

        $notice = $this->readNotice();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Seviye Yetkilendirme', 'seviye-security'); ?></h1>
            <p>
                <?php esc_html_e(
                    'Her kullanıcıya bir Seviye rolü atayın ve giriş için gereken T.C. Kimlik No eşleşmesini buradan yönetin. WordPress yöneticileri (administrator) bu listede gösterilmez.',
                    'seviye-security'
                ); ?>
            </p>

            <?php if ($notice !== null) : ?>
                <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
                    <p><?php echo esc_html($notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Kullanıcı', 'seviye-security'); ?></th>
                        <th><?php esc_html_e('Rol / T.C. Kimlik No / Şifre', 'seviye-security'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->assignableUsers() as $user) : ?>
                        <?php $this->renderRow($user); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
        function scpGenerateAuthorizationPassword(fieldId) {
            var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789#!?%';
            var randomValues = new Uint32Array(14);
            window.crypto.getRandomValues(randomValues);
            var password = '';
            for (var i = 0; i < randomValues.length; i++) {
                password += alphabet[randomValues[i] % alphabet.length];
            }
            var field = document.getElementById(fieldId);
            field.value = password;
            field.type = 'text';
        }
        </script>
        <?php
    }

    private function renderRow(WP_User $user): void
    {
        $currentRole = $this->currentSeviyeRole($user);
        $currentTcNo = $this->identities->findTcNumberByUserId($user->ID);

        ?>
        <tr>
            <td>
                <strong><?php echo esc_html($user->display_name); ?></strong><br>
                <span class="description"><?php echo esc_html($user->user_email); ?></span>
            </td>
            <td>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field(self::NONCE_ACTION); ?>
                    <input type="hidden" name="action" value="scp_save_authorization">
                    <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user->ID); ?>">
                    <select name="role">
                        <option value=""><?php esc_html_e('— rol atanmadı —', 'seviye-security'); ?></option>
                        <?php foreach (Role::cases() as $role) : ?>
                            <option
                                value="<?php echo esc_attr($role->value); ?>"
                                <?php selected($currentRole === $role); ?>
                            ><?php echo esc_html($role->label()); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input
                        type="text"
                        name="tc_no"
                        maxlength="11"
                        pattern="[0-9]{11}"
                        placeholder="<?php esc_attr_e('11 haneli T.C. Kimlik No', 'seviye-security'); ?>"
                        value="<?php echo esc_attr($currentTcNo?->value() ?? ''); ?>"
                    >
                    <input
                        type="text"
                        name="password"
                        id="scp_password_<?php echo esc_attr((string) $user->ID); ?>"
                        autocomplete="new-password"
                        placeholder="<?php esc_attr_e('Boş bırakılırsa değişmez', 'seviye-security'); ?>"
                    >
                    <button
                        type="button"
                        class="button"
                        onclick="scpGenerateAuthorizationPassword('scp_password_<?php echo esc_attr((string) $user->ID); ?>')"
                    ><?php esc_html_e('Rastgele oluştur', 'seviye-security'); ?></button>
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Kaydet', 'seviye-security'); ?>
                    </button>
                </form>
            </td>
            <td>
                <?php if ($currentTcNo !== null) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field(self::NONCE_ACTION); ?>
                        <input type="hidden" name="action" value="scp_unlink_tc_no">
                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user->ID); ?>">
                        <button type="submit" class="button-link-delete">
                            <?php esc_html_e('T.C. No eşleşmesini kaldır', 'seviye-security'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    public function handleSave(): void
    {
        check_admin_referer(self::NONCE_ACTION);

        if (!AdminAccess::current()) {
            wp_die(esc_html__('Bu işlem için yetkiniz yok.', 'seviye-security'));
        }

        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $user = get_userdata($userId);

        if ($user === false || in_array('administrator', $user->roles, true)) {
            $this->redirectWithNotice('error', __('Geçersiz kullanıcı.', 'seviye-security'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $roleValue = isset($_POST['role']) ? sanitize_key(wp_unslash($_POST['role'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $tcNoInput = isset($_POST['tc_no']) ? trim(sanitize_text_field(wp_unslash($_POST['tc_no']))) : '';

        // Validate both fields before applying either - a rejected T.C. No
        // must not leave the role change applied on its own.
        $role = null;

        if ($roleValue !== '') {
            $role = Role::tryFrom($roleValue);

            if ($role === null) {
                $this->redirectWithNotice('error', __('Geçersiz rol.', 'seviye-security'));
            }
        }

        $tcNumber = null;

        if ($tcNoInput !== '') {
            if (!TcNumber::isValid($tcNoInput)) {
                $this->redirectWithNotice('error', __('Geçersiz T.C. Kimlik No.', 'seviye-security'));
            }

            $tcNumber = TcNumber::fromString($tcNoInput);
            $existingOwnerId = $this->identities->findUserIdByTcNumber($tcNumber);

            if ($existingOwnerId !== null && $existingOwnerId !== $userId) {
                $this->redirectWithNotice(
                    'error',
                    __('Bu T.C. Kimlik No zaten başka bir kullanıcıya bağlı.', 'seviye-security')
                );
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $passwordInput = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';

        if ($passwordInput !== '' && mb_strlen($passwordInput) < self::MIN_PASSWORD_LENGTH) {
            $this->redirectWithNotice(
                'error',
                sprintf(
                    /* translators: %d: minimum password length */
                    __('Şifre en az %d karakter olmalı.', 'seviye-security'),
                    self::MIN_PASSWORD_LENGTH
                )
            );
        }

        if ($role !== null) {
            $user->set_role($role->value);
        }

        if ($tcNumber !== null) {
            $currentTcNo = $this->identities->findTcNumberByUserId($userId);

            if ($currentTcNo === null || $currentTcNo->value() !== $tcNumber->value()) {
                $this->identities->unlink($userId);
                $this->identities->link($tcNumber, $userId);
            }
        }

        if ($passwordInput !== '') {
            wp_set_password($passwordInput, $userId);

            $this->redirectWithNotice(
                'success',
                sprintf(
                    /* translators: %s: the new plaintext password, shown once so it can be handed to the user */
                    __('Kaydedildi. Yeni şifre: %s — bu şifreyi ilgili kişiye iletin, sayfa yenilendiğinde bir daha gösterilmeyecek.', 'seviye-security'),
                    $passwordInput
                )
            );
        }

        $this->redirectWithNotice('success', __('Kaydedildi.', 'seviye-security'));
    }

    public function handleUnlink(): void
    {
        check_admin_referer(self::NONCE_ACTION);

        if (!AdminAccess::current()) {
            wp_die(esc_html__('Bu işlem için yetkiniz yok.', 'seviye-security'));
        }

        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

        if ($userId > 0) {
            $this->identities->unlink($userId);
        }

        $this->redirectWithNotice('success', __('T.C. No eşleşmesi kaldırıldı.', 'seviye-security'));
    }

    public function renderProfileField(WP_User $user): void
    {
        if (!AdminAccess::current()) {
            return;
        }

        $currentTcNo = $this->identities->findTcNumberByUserId($user->ID);

        ?>
        <h2><?php esc_html_e('Seviye Commerce Platform', 'seviye-security'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="scp_tc_no"><?php esc_html_e('T.C. Kimlik No', 'seviye-security'); ?></label></th>
                <td>
                    <?php wp_nonce_field(self::NONCE_ACTION, 'scp_tc_no_nonce'); ?>
                    <input
                        type="text"
                        name="scp_tc_no"
                        id="scp_tc_no"
                        class="regular-text"
                        maxlength="11"
                        pattern="[0-9]{11}"
                        value="<?php echo esc_attr($currentTcNo?->value() ?? ''); ?>"
                    >
                    <p class="description">
                        <?php esc_html_e(
                            'Seviye giriş ekranında bu kullanıcıyı tanımlamak için kullanılır. Boş bırakırsanız mevcut eşleşme değişmez.',
                            'seviye-security'
                        ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * @param \WP_Error $errors
     * @param bool $update
     */
    public function validateProfileField($errors, $update, ?object $user = null): void
    {
        if (!isset($_POST['scp_tc_no_nonce']) || !AdminAccess::current()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified via wp_verify_nonce below.
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scp_tc_no_nonce'])), self::NONCE_ACTION)) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
        $tcNoInput = isset($_POST['scp_tc_no']) ? trim(sanitize_text_field(wp_unslash($_POST['scp_tc_no']))) : '';

        if ($tcNoInput === '') {
            return;
        }

        if (!TcNumber::isValid($tcNoInput)) {
            $errors->add('scp_tc_no_invalid', __('Geçersiz T.C. Kimlik No.', 'seviye-security'));

            return;
        }

        $existingOwnerId = $this->identities->findUserIdByTcNumber(TcNumber::fromString($tcNoInput));
        $editedUserId = $update && $user !== null ? (int) $user->ID : 0;

        if ($existingOwnerId !== null && $existingOwnerId !== $editedUserId) {
            $errors->add(
                'scp_tc_no_taken',
                __('Bu T.C. Kimlik No zaten başka bir kullanıcıya bağlı.', 'seviye-security')
            );
        }
    }

    public function saveProfileField(int $userId): void
    {
        if (
            !AdminAccess::current()
            || !isset($_POST['scp_tc_no_nonce'])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on the next line.
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scp_tc_no_nonce'])), self::NONCE_ACTION)
        ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
        $tcNoInput = isset($_POST['scp_tc_no']) ? trim(sanitize_text_field(wp_unslash($_POST['scp_tc_no']))) : '';

        if ($tcNoInput === '' || !TcNumber::isValid($tcNoInput)) {
            return;
        }

        $tcNumber = TcNumber::fromString($tcNoInput);
        $existingOwnerId = $this->identities->findUserIdByTcNumber($tcNumber);

        if ($existingOwnerId !== null && $existingOwnerId !== $userId) {
            return;
        }

        $currentTcNo = $this->identities->findTcNumberByUserId($userId);

        if ($currentTcNo === null || $currentTcNo->value() !== $tcNumber->value()) {
            $this->identities->unlink($userId);
            $this->identities->link($tcNumber, $userId);
        }
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
    private function assignableUsers(): array
    {
        $users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);

        return array_values(array_filter(
            $users,
            static fn (WP_User $user): bool => !in_array('administrator', $user->roles, true)
        ));
    }

    private function redirectWithNotice(string $type, string $message): void
    {
        set_transient('scp_auth_notice_' . get_current_user_id(), ['type' => $type, 'message' => $message], 30);

        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG));

        exit;
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function readNotice(): ?array
    {
        $key = 'scp_auth_notice_' . get_current_user_id();
        $notice = get_transient($key);

        if (!is_array($notice)) {
            return null;
        }

        delete_transient($key);

        return $notice;
    }
}
