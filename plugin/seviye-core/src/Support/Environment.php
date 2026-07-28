<?php

declare(strict_types=1);

namespace Seviye\Core\Support;

final class Environment
{
    public const MIN_PHP_VERSION = '8.2.0';

    public static function satisfiesPhpVersion(): bool
    {
        return version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=');
    }

    public static function isWooCommerceActive(): bool
    {
        return class_exists('WooCommerce');
    }
}
