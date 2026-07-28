<?php

declare(strict_types=1);

namespace Seviye\Branches\Domain;

/**
 * A pure-PHP (no WordPress dependency) slugifier with a Turkish
 * transliteration table, since sanitize_title() would work but would tie
 * this domain logic to WordPress being loaded to even unit test it.
 */
final class Slug
{
    private const TURKISH_MAP = [
        'ç' => 'c', 'Ç' => 'c',
        'ğ' => 'g', 'Ğ' => 'g',
        'ı' => 'i', 'I' => 'i', 'İ' => 'i',
        'ö' => 'o', 'Ö' => 'o',
        'ş' => 's', 'Ş' => 's',
        'ü' => 'u', 'Ü' => 'u',
    ];

    public static function fromString(string $value): string
    {
        $transliterated = strtr($value, self::TURKISH_MAP);
        $lower = mb_strtolower($transliterated, 'UTF-8');
        $slug = preg_replace('/[^a-z0-9]+/', '-', $lower) ?? '';

        return trim($slug, '-');
    }
}
