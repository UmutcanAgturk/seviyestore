<?php

declare(strict_types=1);

namespace Seviye\Core\Tests\Unit\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use Seviye\Core\Container\ServiceContainer;

final class ServiceContainerTest extends TestCase
{
    public function testSingletonReturnsSameInstanceOnEveryCall(): void
    {
        $container = new ServiceContainer();
        $container->singleton(\stdClass::class, static fn (): \stdClass => new \stdClass());

        self::assertSame($container->get(\stdClass::class), $container->get(\stdClass::class));
    }

    public function testBindReturnsANewInstanceOnEveryCall(): void
    {
        $container = new ServiceContainer();
        $container->bind(\stdClass::class, static fn (): \stdClass => new \stdClass());

        self::assertNotSame($container->get(\stdClass::class), $container->get(\stdClass::class));
    }

    public function testHasReflectsRegisteredBindings(): void
    {
        $container = new ServiceContainer();

        self::assertFalse($container->has(\stdClass::class));

        $container->bind(\stdClass::class, static fn (): \stdClass => new \stdClass());

        self::assertTrue($container->has(\stdClass::class));
    }

    public function testGetThrowsWhenServiceIsNotRegistered(): void
    {
        $container = new ServiceContainer();

        $this->expectException(NotFoundExceptionInterface::class);

        $container->get('unregistered.service');
    }
}
