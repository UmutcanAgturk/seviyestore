<?php

declare(strict_types=1);

namespace Seviye\Core\Module;

use Seviye\Core\Container\ServiceContainer;

/**
 * Contract every Seviye module plugin (Students, Parents, Branches, Pricing,
 * Commerce, Finance, Reports, Notifications, API, Security...) implements to
 * plug into Core.
 *
 * A module must never reference another module's classes directly. All
 * cross-module needs go through services resolved from the container
 * (EventBusInterface, RbacManager, MigrationRunner, ...).
 *
 * Registration pattern, from the module's own main plugin file:
 *
 *   add_action('plugins_loaded', static function (): void {
 *       if (!class_exists(\Seviye\Core\Plugin::class)) {
 *           return;
 *       }
 *       \Seviye\Core\Plugin::instance()->modules()->register(new StudentsModule());
 *   }, 10);
 *
 * Core boots its container at plugins_loaded priority 0 and calls boot() on
 * every registered module at priority 20, so registering at the default
 * priority (10) is always safe.
 */
interface ModuleInterface
{
    /**
     * Unique, stable identifier, e.g. "students", "parents", "branches".
     */
    public function slug(): string;

    /**
     * Called once Core has booted. Use this to register migrations,
     * capabilities, event listeners and REST controllers via the container.
     */
    public function boot(ServiceContainer $container): void;
}
