<?php

declare(strict_types=1);

namespace Seviye\Notifications;

use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Events\EventBusInterface;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\Role;
use Seviye\Core\Settings\SettingsRepositoryInterface;
use Seviye\Notifications\Channel\EmailChannel;
use Seviye\Notifications\Channel\NetgsmSmsChannel;
use Seviye\Notifications\Channel\PanelChannel;
use Seviye\Notifications\Database\Migrations\CreateNotificationsTable;
use Seviye\Notifications\Dispatch\NotificationDispatcher;
use Seviye\Notifications\Dispatch\NotificationDispatcherInterface;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Http\NotificationsRestController;
use Seviye\Notifications\Http\NotificationsSettingsRestController;
use Seviye\Notifications\Rbac\NotificationCapability;
use Seviye\Notifications\Recipient\RecipientResolverInterface;
use Seviye\Notifications\Recipient\WpRecipientResolver;
use Seviye\Notifications\Repository\NotificationRepositoryInterface;
use Seviye\Notifications\Repository\WpdbNotificationRepository;
use Seviye\Notifications\Support\PasswordResetNotificationListener;
use Seviye\Parents\Contracts\ParentContactLookupInterface;

/**
 * Depends on Core (everything) and Parents (only for
 * Contracts\ParentContactLookupInterface, the platform's sole phone-number
 * source, needed for the SMS channel) - like Finance's HakedisEventListener,
 * this module's EventBus listeners react purely to documented event
 * names/payloads dispatched by other modules, never their Contracts or
 * internal classes. It works correctly whether or not any other Seviye
 * module happens to be active; a channel simply fails to deliver (recorded
 * as such, never silently dropped) if its recipient data or gateway
 * credentials aren't available yet.
 */
final class NotificationsModule implements ModuleInterface
{
    public function slug(): string
    {
        return 'notifications';
    }

    public function boot(ServiceContainer $container): void
    {
        $container->singleton(
            NotificationRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbNotificationRepository => new WpdbNotificationRepository(
                $c->get(ConnectionInterface::class)
            )
        );

        $container->singleton(
            RecipientResolverInterface::class,
            static fn (ServiceContainer $c): WpRecipientResolver => new WpRecipientResolver(
                $c->get(ParentContactLookupInterface::class)
            )
        );

        $container->singleton(
            NotificationDispatcherInterface::class,
            static fn (ServiceContainer $c): NotificationDispatcher => new NotificationDispatcher(
                $c->get(NotificationRepositoryInterface::class),
                $c->get(RecipientResolverInterface::class),
                [
                    NotificationChannel::EMAIL->value => new EmailChannel(),
                    NotificationChannel::PANEL->value => new PanelChannel(),
                    NotificationChannel::SMS->value => new NetgsmSmsChannel(
                        $c->get(SettingsRepositoryInterface::class)
                    ),
                ]
            )
        );

        $container->get(MigrationRunner::class)->register(new CreateNotificationsTable());

        // Deferred to `init` (not resolved here in boot()): NotificationDispatcherInterface's
        // factory resolves Parents' Contracts\ParentContactLookupInterface. Like
        // Commerce's WooCommerce hooks (see CommerceModule::boot()), this module
        // cannot rely on Parents' module having booted first - ModuleRegistry::bootAll()
        // boots modules in plugin registration order, not dependency order. `init`
        // always fires after every module's boot() has run.
        add_action('init', static function () use ($container): void {
            $listener = new PasswordResetNotificationListener($container->get(NotificationDispatcherInterface::class));
            $container->get(EventBusInterface::class)->listen(
                'security.password_reset_requested',
                [$listener, 'onPasswordResetRequested']
            );
        });

        $container->get(RbacManager::class)->grantCapability(
            Role::GENEL_MERKEZ,
            NotificationCapability::MANAGE_NOTIFICATION_SETTINGS->value
        );

        $container->get(RestApiRegistrar::class)->register(
            static fn (): NotificationsSettingsRestController => new NotificationsSettingsRestController(
                $container->get(SettingsRepositoryInterface::class)
            )
        );

        $container->get(RestApiRegistrar::class)->register(
            static fn (): NotificationsRestController => new NotificationsRestController(
                $container->get(NotificationRepositoryInterface::class)
            )
        );
    }
}
