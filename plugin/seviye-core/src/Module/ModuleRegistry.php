<?php

declare(strict_types=1);

namespace Seviye\Core\Module;

use LogicException;
use Seviye\Core\Container\ServiceContainer;

final class ModuleRegistry
{
    /** @var array<string, ModuleInterface> */
    private array $modules = [];

    public function register(ModuleInterface $module): void
    {
        if (isset($this->modules[$module->slug()])) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
            throw new LogicException(sprintf('Module "%s" is already registered.', $module->slug()));
        }

        $this->modules[$module->slug()] = $module;
    }

    public function has(string $slug): bool
    {
        return isset($this->modules[$slug]);
    }

    /**
     * @return array<string, ModuleInterface>
     */
    public function all(): array
    {
        return $this->modules;
    }

    public function bootAll(ServiceContainer $container): void
    {
        foreach ($this->modules as $module) {
            $module->boot($container);
        }
    }
}
