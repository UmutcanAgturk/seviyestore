<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Fakes;

use Seviye\Core\Settings\SettingsRepositoryInterface;

final class FakeSettingsRepository implements SettingsRepositoryInterface
{
    /** @var array<string, string> */
    public array $values = [];

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, string $value): void
    {
        $this->values[$key] = $value;
    }
}
