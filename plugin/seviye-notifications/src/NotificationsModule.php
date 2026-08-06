<?php

declare(strict_types=1);

namespace Seviye\Notifications;

use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationRunner;
use Seviye\Core\Events\EventBusInterface;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleInterface;
use Seviye\Core\Rbac\RbacManager;
use Seviye\Core\Rbac\Role;
use Seviye\Core\Settings\SettingsRepositoryInterface;
use Seviye\Depo\Contracts\PurchaseSuggestionSummaryInterface;
use Seviye\Finance\Contracts\HakedisTotalsInterface;
use Seviye\Notifications\Channel\EmailChannel;
use Seviye\Notifications\Channel\GmailSmtpConfigurator;
use Seviye\Notifications\Channel\NetgsmSmsChannel;
use Seviye\Notifications\Channel\PanelChannel;
use Seviye\Notifications\Channel\WhatsAppChannel;
use Seviye\Notifications\Database\Migrations\CreateNotificationsTable;
use Seviye\Notifications\Database\Migrations\CreateScheduledBroadcastsTable;
use Seviye\Notifications\Dispatch\NotificationDispatcher;
use Seviye\Notifications\Dispatch\NotificationDispatcherInterface;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Http\BroadcastRestController;
use Seviye\Notifications\Http\EmailSettingsRestController;
use Seviye\Notifications\Http\NotificationsRestController;
use Seviye\Notifications\Http\NotificationsSettingsRestController;
use Seviye\Notifications\Http\ScheduledBroadcastHooks;
use Seviye\Notifications\Http\WeeklyDigestHooks;
use Seviye\Notifications\Rbac\NotificationCapability;
use Seviye\Notifications\Recipient\RecipientResolverInterface;
use Seviye\Notifications\Recipient\WpRecipientResolver;
use Seviye\Notifications\Repository\NotificationRepositoryInterface;
use Seviye\Notifications\Repository\ScheduledBroadcastRepositoryInterface;
use Seviye\Notifications\Repository\WpdbNotificationRepository;
use Seviye\Notifications\Repository\WpdbScheduledBroadcastRepository;
use Seviye\Notifications\Support\LowStockNotificationListener;
use Seviye\Notifications\Support\OrderPlacedNotificationListener;
use Seviye\Notifications\Support\OrderStatusNotificationListener;
use Seviye\Notifications\Support\PasswordResetNotificationListener;
use Seviye\Notifications\Support\WeeklyDigestBuilder;
use Seviye\Parents\Contracts\ParentContactLookupInterface;
use Seviye\Students\Contracts\BranchParentLookupInterface;

/**
 * Depends on Core (everything), Parents (Contracts\ParentContactLookupInterface,
 * the platform's sole phone-number source, needed for the SMS channel),
 * Finance (Contracts\HakedisTotalsInterface) and Depo
 * (Contracts\PurchaseSuggestionSummaryInterface) - the latter two only for
 * WeeklyDigestHooks. Every EventBus listener here still reacts purely to
 * documented event names/payloads dispatched by other modules, never their
 * Contracts or internal classes, and works correctly whether or not any
 * other Seviye module happens to be active; a channel simply fails to
 * deliver (recorded as such, never silently dropped) if its recipient data
 * or gateway credentials aren't available yet. WeeklyDigestHooks is the one
 * exception that genuinely needs two other modules' Contracts to compute
 * its numbers - if Finance or Depo is inactive, its container binding is
 * simply unavailable and the digest fails to register (see "Requires
 * Plugins" in seviye-notifications.php, which now hard-requires both).
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
            ScheduledBroadcastRepositoryInterface::class,
            static fn (ServiceContainer $c): WpdbScheduledBroadcastRepository => new WpdbScheduledBroadcastRepository(
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
                    NotificationChannel::WHATSAPP->value => new WhatsAppChannel(
                        $c->get(SettingsRepositoryInterface::class)
                    ),
                ]
            )
        );

        $container->get(MigrationRunner::class)->register(new CreateNotificationsTable());
        $container->get(MigrationRunner::class)->register(new CreateScheduledBroadcastsTable());

        // Deferred to `init` (not resolved here in boot()): NotificationDispatcherInterface's
        // factory resolves Parents' Contracts\ParentContactLookupInterface. Like
        // Commerce's WooCommerce hooks (see CommerceModule::boot()), this module
        // cannot rely on Parents' module having booted first - ModuleRegistry::bootAll()
        // boots modules in plugin registration order, not dependency order. `init`
        // always fires after every module's boot() has run.
        add_action('init', static function () use ($container): void {
            $passwordResetListener = new PasswordResetNotificationListener(
                $container->get(NotificationDispatcherInterface::class)
            );
            $container->get(EventBusInterface::class)->listen(
                'security.password_reset_requested',
                [$passwordResetListener, 'onPasswordResetRequested']
            );

            // "Veliler sipariş verdiğinde otomatik olarak velilerin mailine
            // mail gidecek bir sistem" - see OrderPlacedNotificationListener
            // and Seviye\Commerce\Http\OrderPersistenceHooks, which fires
            // this event. Same deferred-to-`init` reasoning as the listener
            // above: Commerce may not have booted yet when this module's own
            // boot() runs.
            $orderPlacedListener = new OrderPlacedNotificationListener(
                $container->get(NotificationDispatcherInterface::class)
            );
            $container->get(EventBusInterface::class)->listen(
                'commerce.order_placed',
                [$orderPlacedListener, 'onOrderPlaced']
            );

            // "İade/iptal akışı" - see OrderStatusNotificationListener and
            // OrderPersistenceHooks::syncOrderStatus()/onOrderRefunded(),
            // which fire these two events. Same deferred-to-`init` reasoning
            // as the listener above.
            $orderStatusListener = new OrderStatusNotificationListener(
                $container->get(NotificationDispatcherInterface::class)
            );
            $container->get(EventBusInterface::class)->listen(
                'commerce.order_cancelled',
                [$orderStatusListener, 'onOrderCancelled']
            );
            $container->get(EventBusInterface::class)->listen(
                'commerce.order_refunded',
                [$orderStatusListener, 'onOrderRefunded']
            );

            // "Kargoya verildi/teslim edildi" - see
            // AdminOrdersRestController::ship()/deliver(), which fire these
            // two events. Same deferred-to-`init` reasoning as the
            // listeners above.
            $container->get(EventBusInterface::class)->listen(
                'commerce.order_shipped',
                [$orderStatusListener, 'onOrderShipped']
            );
            $container->get(EventBusInterface::class)->listen(
                'commerce.order_delivered',
                [$orderStatusListener, 'onOrderDelivered']
            );

            // "Düşük stok uyarısı" - see LowStockNotificationListener and
            // Seviye\Commerce\Http\LowStockNotificationHooks, which fires
            // this event. Same deferred-to-`init` reasoning as the
            // listeners above.
            $lowStockListener = new LowStockNotificationListener(
                $container->get(NotificationDispatcherInterface::class)
            );
            $container->get(EventBusInterface::class)->listen(
                'commerce.product_low_stock',
                [$lowStockListener, 'onLowStock']
            );

            // "Haftalık/aylık özet e-postaları" - not an EventBus reaction
            // (nothing external fires a "week passed" event), a WP Cron job
            // instead - see WeeklyDigestHooks's own docblock. Deferred to
            // `init` for the same reason as the listeners above: it reads
            // Finance's HakedisTotalsInterface and Depo's
            // PurchaseSuggestionSummaryInterface, neither of which is
            // guaranteed bound yet inside this module's own boot().
            (new WeeklyDigestHooks(
                $container->get(HakedisTotalsInterface::class),
                $container->get(PurchaseSuggestionSummaryInterface::class),
                $container->get(NotificationDispatcherInterface::class),
                new WeeklyDigestBuilder()
            ))->register();

            // "Zamanlanmış toplu duyuru" - see ScheduledBroadcastHooks's own
            // docblock. Deferred to `init` for the same reason as the
            // listeners above: it depends on Students' BranchParentLookupInterface,
            // not guaranteed bound yet inside this module's own boot(). The
            // register() call here only wires the `add_action()` handler -
            // it does not itself schedule anything; each individual
            // scheduled broadcast's one-shot event is registered by
            // Http\BroadcastRestController::send() at creation time.
            (new ScheduledBroadcastHooks(
                $container->get(ScheduledBroadcastRepositoryInterface::class),
                $container->get(BranchParentLookupInterface::class),
                $container->get(NotificationDispatcherInterface::class)
            ))->register();
        });

        // "Google maili özelinde göndereceğiz" - routes wp_mail() (hence
        // EmailChannel above) through Gmail's SMTP relay whenever
        // seviye/v1/notifications/email-settings has credentials on file;
        // a no-op otherwise (see GmailSmtpConfigurator's class docblock).
        // Registered directly, not deferred: SettingsRepositoryInterface is
        // a Core-owned binding, not another module's Contract.
        (new GmailSmtpConfigurator($container->get(SettingsRepositoryInterface::class)))->register();

        $container->get(RbacManager::class)->grantCapability(
            Role::GENEL_MERKEZ,
            NotificationCapability::MANAGE_NOTIFICATION_SETTINGS->value
        );

        // "Toplu duyuru sistemi" - Genel Merkez/Bölge Müdürü may broadcast
        // to any branch (or every one); Şube Müdürü only their own - see
        // Http\BroadcastRestController.
        $rbac = $container->get(RbacManager::class);
        $rbac->grantCapability(Role::GENEL_MERKEZ, NotificationCapability::SEND_BROADCAST->value);
        $rbac->grantCapability(Role::BOLGE_MUDURU, NotificationCapability::SEND_BROADCAST->value);
        $rbac->grantCapability(Role::SUBE_MUDURU, NotificationCapability::SEND_OWN_BRANCH_BROADCAST->value);

        $container->get(RestApiRegistrar::class)->register(
            static fn (): BroadcastRestController => new BroadcastRestController(
                $container->get(BranchParentLookupInterface::class),
                $container->get(BranchMembershipInterface::class),
                $container->get(NotificationDispatcherInterface::class),
                $container->get(ScheduledBroadcastRepositoryInterface::class)
            )
        );

        $container->get(RestApiRegistrar::class)->register(
            static fn (): NotificationsSettingsRestController => new NotificationsSettingsRestController(
                $container->get(SettingsRepositoryInterface::class)
            )
        );

        $container->get(RestApiRegistrar::class)->register(
            static fn (): EmailSettingsRestController => new EmailSettingsRestController(
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
