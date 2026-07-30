<?php

declare(strict_types=1);

namespace Seviye\Security\Http\Admin;

use Seviye\Core\Rbac\Role;
use Seviye\Security\Identity\IdentityGatewayInterface;
use WP_User;

/**
 * Backs both "Seviye Kullanıcılar" (every non-administrator WP user) and
 * "Veli" (the same table, filtered to Role::VELI) - one class, one
 * role filter parameter, since the two are the same listing/edit/delete
 * feature at different scopes, not two different features. Role/T.C.
 * Kimlik No/password assignment stays {@see UserAuthorizationAdminPage}'s
 * job; this page only edits basic account info (ad, e-posta) and deletes
 * accounts. Not unit tested, same as every other WordPress-touching
 * adapter in this codebase (see docs/ARCHITECTURE.md, "Test stratejisi").
 */
final class UserListPage
{
    public const SLUG_ALL = 'scp-kullanicilar';
    public const SLUG_VELI = 'scp-kullanicilar-veli';
    private const NONCE_ACTION = 'scp_user_list';

    public function __construct(private readonly IdentityGatewayInterface $identities)
    {
    }

    public function registerActions(): void
    {
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
