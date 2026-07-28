<?php

declare(strict_types=1);

namespace Seviye\Core\Http;

/**
 * Collects REST controllers from Core and every module and registers their
 * routes under the shared "seviye/v1" namespace on rest_api_init.
 */
final class RestApiRegistrar
{
    public const NAMESPACE = 'seviye/v1';

    /** @var list<AbstractRestController> */
    private array $controllers = [];

    public function register(AbstractRestController $controller): void
    {
        $this->controllers[] = $controller;
    }

    public function boot(): void
    {
        add_action('rest_api_init', function (): void {
            foreach ($this->controllers as $controller) {
                $controller->registerRoutes();
            }
        });
    }
}
