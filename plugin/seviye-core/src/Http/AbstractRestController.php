<?php

declare(strict_types=1);

namespace Seviye\Core\Http;

/**
 * Base class for REST controllers registered through {@see RestApiRegistrar}.
 * Concrete endpoints live in their owning module (e.g. Seviye API), this
 * class only provides the shared registration contract and a small
 * permission-callback helper.
 */
abstract class AbstractRestController
{
    abstract public function registerRoutes(): void;

    /**
     * @return callable(): bool
     */
    protected function requireCapability(string $capability): callable
    {
        return static fn (): bool => current_user_can($capability);
    }
}
