<?php

declare(strict_types=1);

namespace Seviye\Core\Http;

use Closure;

/**
 * Collects REST controller factories from Core and every module and
 * registers their routes under the shared "seviye/v1" namespace on
 * rest_api_init.
 *
 * Controllers are registered as factories, not ready-made instances,
 * specifically so a module can depend on another module's container
 * bindings (e.g. Students resolving Branches' Contracts\BranchMembershipInterface)
 * without caring which module's plugins_loaded callback happened to run
 * first: the factory only runs inside rest_api_init, by which point every
 * module has finished booting regardless of load order.
 */
final class RestApiRegistrar
{
    public const NAMESPACE = 'seviye/v1';

    /** @var list<Closure(): AbstractRestController> */
    private array $factories = [];

    /**
     * @param Closure(): AbstractRestController $factory
     */
    public function register(Closure $factory): void
    {
        $this->factories[] = $factory;
    }

    public function boot(): void
    {
        add_action('rest_api_init', function (): void {
            foreach ($this->factories as $factory) {
                $factory()->registerRoutes();
            }
        });
    }
}
