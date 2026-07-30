<?php

declare(strict_types=1);

namespace Seviye\Security\Http\Admin;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Students\Contracts\StudentDirectoryEntry;
use Seviye\Students\Contracts\StudentDirectoryInterface;

/**
 * Read-only "Öğrenci" listing - students are not WP users at all (a
 * separate scp_students entity, see Seviye Students), so unlike the other
 * three "Seviye Kullanıcılar" pages this one has no edit/delete of its
 * own; full CRUD already exists in the theme's /admin, /sube panels via
 * seviye/v1/students. This page exists purely so Genel Merkez can see the
 * directory without leaving wp-admin.
 *
 * Security depends on Students'/Branches' Contracts here (composer.json
 * lists both as path requirements) but deliberately does NOT declare them
 * in its "Requires Plugins" header: Security is a foundational, early-
 * activating module (auth has to work before anything else does), so it
 * must still activate and function standalone even if Students/Branches
 * are not installed. The container itself (not a resolved instance) is
 * injected and only queried inside {@see render()} - resolving here, at
 * SecurityModule::boot() time, would repeat the exact module-boot-order
 * fatal documented in docs/ARCHITECTURE.md's "İkinci kural" (Commerce's
 * and Notifications' modules hit this before being fixed): `render()`
 * only ever runs once wp-admin actually loads this page, well after every
 * module's boot() has completed regardless of plugin registration order.
 */
final class StudentDirectoryPage
{
    public const SLUG = 'scp-kullanicilar-ogrenci';

    public function __construct(private readonly ServiceContainer $container)
    {
    }

    public function render(): void
    {
        if (!AdminAccess::current()) {
            wp_die(esc_html__('Bu sayfayı görüntüleme yetkiniz yok.', 'seviye-security'));
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Öğrenci', 'seviye-security'); ?></h1>

            <?php if (!$this->container->has(StudentDirectoryInterface::class)) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e(
                            'Bu listeyi görebilmek için Seviye Students eklentisinin etkin olması gerekiyor.',
                            'seviye-security'
                        ); ?>
                    </p>
                </div>
                <?php return; ?>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Ad Soyad', 'seviye-security'); ?></th>
                        <th><?php esc_html_e('Şube', 'seviye-security'); ?></th>
                        <th><?php esc_html_e('Eğitim Yılı', 'seviye-security'); ?></th>
                        <th><?php esc_html_e('Sınıf', 'seviye-security'); ?></th>
                        <th><?php esc_html_e('Durum', 'seviye-security'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->container->get(StudentDirectoryInterface::class)->all() as $entry) : ?>
                        <?php $this->renderRow($entry); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function renderRow(StudentDirectoryEntry $entry): void
    {
        $branches = $this->container->has(BranchLookupInterface::class)
            ? $this->container->get(BranchLookupInterface::class)
            : null;
        $branchName = $branches?->find($entry->branchId)?->name ?? '—';

        ?>
        <tr>
            <td><?php echo esc_html(trim($entry->firstName . ' ' . $entry->lastName)); ?></td>
            <td><?php echo esc_html($branchName); ?></td>
            <td><?php echo esc_html($entry->educationYear); ?></td>
            <td><?php echo esc_html($entry->className); ?></td>
            <td><?php echo esc_html($entry->status); ?></td>
        </tr>
        <?php
    }
}
