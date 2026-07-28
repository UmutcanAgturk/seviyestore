<?php

declare(strict_types=1);

namespace Seviye\Core;

use Seviye\Core\Container\ServiceContainer;
use Seviye\Core\Events\Event;
use Seviye\Core\Events\EventBusInterface;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Module\ModuleRegistry;
use Seviye\Core\Support\CoreServiceProvider;

/**
 * Core's bootstrap orchestrator and the single integration point every
 * Seviye module talks to: `Plugin::instance()->modules()->register(...)`.
 */
final class Plugin
{
    private static ?self $instance = null;

    private readonly ServiceContainer $container;

    private bool $booted = false;

    private function __construct()
    {
        $this->container = new ServiceContainer();
        (new CoreServiceProvider())->register($this->container);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function container(): ServiceContainer
    {
        return $this->container;
    }

    public function modules(): ModuleRegistry
    {
        return $this->container->get(ModuleRegistry::class);
    }

    /**
     * Boots the container early (plugins_loaded priority 0), letting modules
     * register themselves at the default priority (10), then boots every
     * registered module and the REST API at priority 20.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        load_plugin_textdomain('seviye-core', false, dirname(plugin_basename(SCP_CORE_FILE)) . '/languages');

        add_action('plugins_loaded', function (): void {
            $this->modules()->bootAll($this->container);
            $this->container->get(RestApiRegistrar::class)->boot();
            $this->container->get(EventBusInterface::class)->dispatch(new Event('core.booted'));
        }, 20);
    }
}
