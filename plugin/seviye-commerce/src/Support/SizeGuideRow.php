<?php

declare(strict_types=1);

namespace Seviye\Commerce\Support;

/**
 * One row of the "Beden Rehberi" (size guide) - a rough age/height range
 * a parent can match against to pick the right beden. All range fields
 * are optional (null = "not specified for this row") since HQ may only
 * know age OR height for a given size, not always both.
 */
final class SizeGuideRow
{
    public function __construct(
        public readonly string $label,
        public readonly ?int $ageMin = null,
        public readonly ?int $ageMax = null,
        public readonly ?int $heightMinCm = null,
        public readonly ?int $heightMaxCm = null
    ) {
    }

    /**
     * @return array{label: string, age_min: ?int, age_max: ?int, height_min_cm: ?int, height_max_cm: ?int}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'age_min' => $this->ageMin,
            'age_max' => $this->ageMax,
            'height_min_cm' => $this->heightMinCm,
            'height_max_cm' => $this->heightMaxCm,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            trim((string) ($data['label'] ?? '')),
            self::nullableInt($data['age_min'] ?? null),
            self::nullableInt($data['age_max'] ?? null),
            self::nullableInt($data['height_min_cm'] ?? null),
            self::nullableInt($data['height_max_cm'] ?? null)
        );
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
