<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Unit\Privacy;

use PHPUnit\Framework\TestCase;
use Seviye\Security\Privacy\PrivacyExportBuilder;

final class PrivacyExportBuilderTest extends TestCase
{
    public function testAssemblesEveryGivenPieceUnderItsOwnKey(): void
    {
        $export = (new PrivacyExportBuilder())->build(
            '2026-08-02 10:00:00',
            ['user_id' => 12, 'username' => 'ada'],
            ['tc_no' => '12345678901', 'two_factor_enabled' => true],
            ['phone' => '+905551112233'],
            [['id' => 5, 'first_name' => 'Deniz']]
        );

        self::assertSame('2026-08-02 10:00:00', $export['generated_at']);
        self::assertSame(['user_id' => 12, 'username' => 'ada'], $export['account']);
        self::assertSame(['tc_no' => '12345678901', 'two_factor_enabled' => true], $export['identity']);
        self::assertSame(['phone' => '+905551112233'], $export['parent_profile']);
        self::assertSame([['id' => 5, 'first_name' => 'Deniz']], $export['children']);
    }

    public function testMissingOptionalPiecesStayNull(): void
    {
        $export = (new PrivacyExportBuilder())->build(
            '2026-08-02 10:00:00',
            ['user_id' => 12, 'username' => 'ada'],
            null,
            null,
            []
        );

        self::assertNull($export['identity']);
        self::assertNull($export['parent_profile']);
        self::assertSame([], $export['children']);
    }
}
