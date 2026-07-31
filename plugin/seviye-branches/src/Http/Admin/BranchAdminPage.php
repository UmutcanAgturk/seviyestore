<?php

declare(strict_types=1);

namespace Seviye\Branches\Http\Admin;

use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Branches\Domain\Branch;
use Seviye\Branches\Domain\BranchStatus;
use Seviye\Branches\Domain\CommissionRate;
use Seviye\Branches\Domain\Iban;
use Seviye\Branches\Domain\Slug;
use Seviye\Branches\Rbac\BranchCapability;
use Seviye\Branches\Repository\BranchRepositoryInterface;

/**
 * Native wp-admin counterpart to the theme's own /admin Şube Yönetimi
 * panel - the theme panel is reachable only by a WP user carrying a Seviye
 * role (Genel Merkez/Bölge Müdürü), which the site's actual WordPress
 * administrator (hosting-level account) never has by default. This page
 * exists so that account can create/edit branches directly from wp-admin
 * without needing a Seviye role of its own, same reasoning as Security's
 * "Seviye Kullanıcılar" menu (see docs/ARCHITECTURE.md, bölüm 20). Gated
 * on BranchCapability::MANAGE_BRANCHES, granted to both Role::GENEL_MERKEZ/
 * Role::BOLGE_MUDURU and WordPress' native `administrator` role (see
 * BranchesModule::boot()). Not unit tested, same as every other direct
 * WP-admin-touching adapter in this codebase (see docs/ARCHITECTURE.md,
 * "Test stratejisi").
 *
 * Also the only place in the whole codebase that assigns a Şube Müdürü to a
 * branch (scp_branch_users) - {@see BranchMembershipInterface::assign()}
 * previously had no caller anywhere, native wp-admin or theme panel, so
 * there was no way to do this at all before this page grew the "Yetkili"
 * section below the edit form.
 */
final class BranchAdminPage
{
    public const SLUG = 'scp-subeler';
    private const NONCE_ACTION = 'scp_branch_admin';

    public function __construct(
        private readonly BranchRepositoryInterface $branches,
        private readonly BranchMembershipInterface $memberships
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_scp_create_branch', [$this, 'handleCreate']);
        add_action('admin_post_scp_save_branch', [$this, 'handleSave']);
        add_action('admin_post_scp_assign_branch_manager', [$this, 'handleAssignManager']);
        add_action('admin_post_scp_unassign_branch_manager', [$this, 'handleUnassignManager']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('Seviye Şubeler', 'seviye-branches'),
            __('Seviye Şubeler', 'seviye-branches'),
            BranchCapability::MANAGE_BRANCHES->value,
            self::SLUG,
            [$this, 'render'],
            'dashicons-store',
            4
        );
    }

    public function render(): void
    {
        if (!current_user_can(BranchCapability::MANAGE_BRANCHES->value)) {
            wp_die(esc_html__('Bu sayfayı görüntüleme yetkiniz yok.', 'seviye-branches'));
        }

        $notice = $this->readNotice();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Seviye Şubeler', 'seviye-branches'); ?></h1>

            <?php if ($notice !== null) : ?>
                <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
                    <p><?php echo esc_html($notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e('Yeni Şube Ekle', 'seviye-branches'); ?></h2>
            <?php $this->renderCreateForm(); ?>

            <h2><?php esc_html_e('Mevcut Şubeler', 'seviye-branches'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Ad', 'seviye-branches'); ?></th>
                        <th><?php esc_html_e('IBAN', 'seviye-branches'); ?></th>
                        <th><?php esc_html_e('Komisyon (%)', 'seviye-branches'); ?></th>
                        <th><?php esc_html_e('Telefon / Adres', 'seviye-branches'); ?></th>
                        <th><?php esc_html_e('Durum', 'seviye-branches'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $users = get_users(['fields' => ['ID', 'display_name']]); ?>
                    <?php foreach ($this->branches->all() as $branch) : ?>
                        <?php $this->renderRow($branch, $users); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <style>
        .scp-branch-row-fields td { padding: 4px 10px 4px 0; vertical-align: top; }
        .scp-branch-row-fields input[type="text"],
        .scp-branch-row-fields input[type="number"],
        .scp-branch-row-fields select { width: 100%; max-width: 160px; }
        .scp-branch-managers { margin-top: 12px; padding-top: 12px; border-top: 1px solid #dcdcde; }
        .scp-branch-managers ul { margin: 6px 0; }
        .scp-branch-managers li { display: inline-flex; align-items: center; gap: 6px; margin-right: 12px; }
        .scp-branch-managers form.scp-inline { display: inline-flex; align-items: center; gap: 6px; }
        </style>
        <?php
    }

    private function renderCreateForm(): void
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width: 480px;">
            <?php wp_nonce_field(self::NONCE_ACTION); ?>
            <input type="hidden" name="action" value="scp_create_branch">
            <table class="form-table">
                <tr>
                    <th><label for="scp_new_branch_name"><?php esc_html_e('Ad', 'seviye-branches'); ?></label></th>
                    <td>
                        <input type="text" id="scp_new_branch_name" name="name" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="scp_new_branch_iban"><?php esc_html_e('IBAN', 'seviye-branches'); ?></label></th>
                    <td>
                        <input
                            type="text"
                            id="scp_new_branch_iban"
                            name="iban"
                            class="regular-text"
                            placeholder="TR..."
                        >
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="scp_new_branch_commission">
                            <?php esc_html_e('Komisyon (%)', 'seviye-branches'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="scp_new_branch_commission"
                            name="commission_rate"
                            step="0.01"
                            min="0"
                            max="100"
                            required
                        >
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="scp_new_branch_phone"><?php esc_html_e('Telefon', 'seviye-branches'); ?></label>
                    </th>
                    <td><input type="text" id="scp_new_branch_phone" name="phone" class="regular-text"></td>
                </tr>
                <tr>
                    <th>
                        <label for="scp_new_branch_address"><?php esc_html_e('Adres', 'seviye-branches'); ?></label>
                    </th>
                    <td><input type="text" id="scp_new_branch_address" name="address" class="regular-text"></td>
                </tr>
            </table>
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Şubeyi Oluştur', 'seviye-branches'); ?>
            </button>
        </form>
        <?php
    }

    /**
     * @param list<\WP_User> $users
     */
    private function renderRow(Branch $branch, array $users): void
    {
        ?>
        <tr>
            <td colspan="6">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field(self::NONCE_ACTION); ?>
                    <input type="hidden" name="action" value="scp_save_branch">
                    <input type="hidden" name="branch_id" value="<?php echo esc_attr((string) $branch->id); ?>">
                    <table class="scp-branch-row-fields">
                        <tr>
                            <td>
                                <input type="text" name="name" value="<?php echo esc_attr($branch->name); ?>" required>
                            </td>
                            <td>
                                <input
                                    type="text"
                                    name="iban"
                                    value="<?php echo esc_attr($branch->iban?->value() ?? ''); ?>"
                                    placeholder="TR..."
                                >
                            </td>
                            <td>
                                <input
                                    type="number"
                                    name="commission_rate"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    value="<?php echo esc_attr((string) $branch->commissionRate->percentage()); ?>"
                                    required
                                >
                            </td>
                            <td>
                                <input
                                    type="text"
                                    name="phone"
                                    value="<?php echo esc_attr($branch->phone ?? ''); ?>"
                                    placeholder="<?php esc_attr_e('Telefon', 'seviye-branches'); ?>"
                                ><br>
                                <input
                                    type="text"
                                    name="address"
                                    value="<?php echo esc_attr($branch->address ?? ''); ?>"
                                    placeholder="<?php esc_attr_e('Adres', 'seviye-branches'); ?>"
                                >
                            </td>
                            <td>
                                <select name="status">
                                    <?php foreach (BranchStatus::cases() as $status) : ?>
                                        <option
                                            value="<?php echo esc_attr($status->value); ?>"
                                            <?php selected($branch->status === $status); ?>
                                        ><?php echo esc_html($status->value); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button type="submit" class="button button-primary">
                                    <?php esc_html_e('Kaydet', 'seviye-branches'); ?>
                                </button>
                            </td>
                        </tr>
                    </table>
                </form>

                <div class="scp-branch-managers">
                    <strong><?php esc_html_e('Şube Müdürü / Yetkili', 'seviye-branches'); ?></strong>
                    <?php $this->renderBranchManagers($branch, $users); ?>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * @param list<\WP_User> $users
     */
    private function renderBranchManagers(Branch $branch, array $users): void
    {
        $assignedUserIds = $this->memberships->usersForBranch($branch->id);
        ?>
        <ul>
            <?php foreach ($assignedUserIds as $userId) : ?>
                <?php $user = get_userdata($userId); ?>
                <li>
                    <?php echo esc_html($user !== false ? $user->display_name : sprintf('#%d', $userId)); ?>
                    <form
                        method="post"
                        class="scp-inline"
                        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    >
                        <?php wp_nonce_field(self::NONCE_ACTION); ?>
                        <input type="hidden" name="action" value="scp_unassign_branch_manager">
                        <input type="hidden" name="branch_id" value="<?php echo esc_attr((string) $branch->id); ?>">
                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $userId); ?>">
                        <button type="submit" class="button button-link-delete">
                            <?php esc_html_e('Kaldır', 'seviye-branches'); ?>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
            <?php if ($assignedUserIds === []) : ?>
                <li><em><?php esc_html_e('Atanmış yetkili yok.', 'seviye-branches'); ?></em></li>
            <?php endif; ?>
        </ul>
        <form method="post" class="scp-inline" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field(self::NONCE_ACTION); ?>
            <input type="hidden" name="action" value="scp_assign_branch_manager">
            <input type="hidden" name="branch_id" value="<?php echo esc_attr((string) $branch->id); ?>">
            <select name="user_id" required>
                <option value=""><?php esc_html_e('Kullanıcı seçin...', 'seviye-branches'); ?></option>
                <?php foreach ($users as $user) : ?>
                    <option value="<?php echo esc_attr((string) $user->ID); ?>">
                        <?php echo esc_html($user->display_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button">
                <?php esc_html_e('Ata', 'seviye-branches'); ?>
            </button>
        </form>
        <?php
    }

    public function handleCreate(): void
    {
        check_admin_referer(self::NONCE_ACTION);

        if (!current_user_can(BranchCapability::MANAGE_BRANCHES->value)) {
            wp_die(esc_html__('Bu işlem için yetkiniz yok.', 'seviye-branches'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

        if ($name === '') {
            $this->redirectWithNotice('error', __('Şube adı gerekli.', 'seviye-branches'));
        }

        try {
            $iban = $this->resolveIban();
            $commissionRate = $this->resolveCommissionRate();
            $slug = $this->uniqueSlugFor($name);

            $this->branches->create(
                $name,
                $slug,
                $iban,
                $commissionRate,
                $this->stringOrNull('phone'),
                $this->stringOrNull('address')
            );
        } catch (\Throwable $exception) {
            // \Throwable (not just InvalidArgumentException - a WP
            // fatal-error screen with no detail is far less useful than
            // the real message) - a raw PHP fatal here would otherwise
            // surface as WordPress' own opaque "Bu sitede ciddi bir sorun
            // çıktı" screen, same class of failure the setup wizard's
            // scp_handle_setup_step() already guards against.
            $this->redirectWithNotice('error', sprintf(
                '%s: %s (%s:%d)',
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
        }

        $this->redirectWithNotice('success', __('Şube oluşturuldu.', 'seviye-branches'));
    }

    public function handleSave(): void
    {
        check_admin_referer(self::NONCE_ACTION);

        if (!current_user_can(BranchCapability::MANAGE_BRANCHES->value)) {
            wp_die(esc_html__('Bu işlem için yetkiniz yok.', 'seviye-branches'));
        }

        $branchId = isset($_POST['branch_id']) ? (int) $_POST['branch_id'] : 0;

        if ($this->branches->find($branchId) === null) {
            $this->redirectWithNotice('error', __('Şube bulunamadı.', 'seviye-branches'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $statusValue = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';

        if ($name === '') {
            $this->redirectWithNotice('error', __('Şube adı gerekli.', 'seviye-branches'));
        }

        $status = BranchStatus::tryFrom($statusValue);

        if ($status === null) {
            $this->redirectWithNotice('error', __('Geçersiz durum.', 'seviye-branches'));
        }

        try {
            $iban = $this->resolveIban();
            $commissionRate = $this->resolveCommissionRate();

            $this->branches->update(
                $branchId,
                $name,
                $iban,
                $commissionRate,
                $this->stringOrNull('phone'),
                $this->stringOrNull('address'),
                $status
            );
        } catch (\Throwable $exception) {
            $this->redirectWithNotice('error', sprintf(
                '%s: %s (%s:%d)',
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
        }

        $this->redirectWithNotice('success', __('Kaydedildi.', 'seviye-branches'));
    }

    public function handleAssignManager(): void
    {
        check_admin_referer(self::NONCE_ACTION);

        if (!current_user_can(BranchCapability::MANAGE_BRANCHES->value)) {
            wp_die(esc_html__('Bu işlem için yetkiniz yok.', 'seviye-branches'));
        }

        $branchId = isset($_POST['branch_id']) ? (int) $_POST['branch_id'] : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

        if ($this->branches->find($branchId) === null) {
            $this->redirectWithNotice('error', __('Şube bulunamadı.', 'seviye-branches'));
        }

        if ($userId <= 0 || get_userdata($userId) === false) {
            $this->redirectWithNotice('error', __('Kullanıcı bulunamadı.', 'seviye-branches'));
        }

        try {
            $this->memberships->assign($userId, $branchId);
        } catch (\Throwable $exception) {
            $this->redirectWithNotice('error', sprintf(
                '%s: %s (%s:%d)',
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
        }

        $this->redirectWithNotice('success', __('Yetkili atandı.', 'seviye-branches'));
    }

    public function handleUnassignManager(): void
    {
        check_admin_referer(self::NONCE_ACTION);

        if (!current_user_can(BranchCapability::MANAGE_BRANCHES->value)) {
            wp_die(esc_html__('Bu işlem için yetkiniz yok.', 'seviye-branches'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

        if ($userId <= 0) {
            $this->redirectWithNotice('error', __('Kullanıcı bulunamadı.', 'seviye-branches'));
        }

        try {
            $this->memberships->unassign($userId);
        } catch (\Throwable $exception) {
            $this->redirectWithNotice('error', sprintf(
                '%s: %s (%s:%d)',
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
        }

        $this->redirectWithNotice('success', __('Yetkili kaldırıldı.', 'seviye-branches'));
    }

    private function resolveIban(): ?Iban
    {
        $raw = $this->stringOrNull('iban');

        return $raw !== null ? Iban::fromString($raw) : null;
    }

    private function resolveCommissionRate(): CommissionRate
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $raw = isset($_POST['commission_rate']) ? (float) wp_unslash($_POST['commission_rate']) : 0.0;

        return CommissionRate::fromPercentage($raw);
    }

    private function uniqueSlugFor(string $name): string
    {
        $slug = Slug::fromString($name);

        if ($this->branches->slugExists($slug)) {
            $slug .= '-' . substr(md5(uniqid('', true)), 0, 6);
        }

        return $slug;
    }

    private function stringOrNull(string $field): ?string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_admin_referer().
        $value = isset($_POST[$field]) ? trim(sanitize_text_field(wp_unslash($_POST[$field]))) : '';

        return $value !== '' ? $value : null;
    }

    private function redirectWithNotice(string $type, string $message): void
    {
        set_transient(
            'scp_branch_admin_notice_' . get_current_user_id(),
            ['type' => $type, 'message' => $message],
            30
        );

        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG));

        exit;
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function readNotice(): ?array
    {
        $key = 'scp_branch_admin_notice_' . get_current_user_id();
        $notice = get_transient($key);

        if (!is_array($notice)) {
            return null;
        }

        delete_transient($key);

        return $notice;
    }
}
