<?php

declare(strict_types=1);

namespace Seviye\Pricing\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Pricing\Support\PriceRuleImportParser;

final class PriceRuleImportParserTest extends TestCase
{
    public function testParsesWellFormedRows(): void
    {
        $csv = "product_id,scope,target_id,price\n"
            . "12,branch,7,99.90\n"
            . "12,general,,79.90\n";

        $rows = (new PriceRuleImportParser())->parse($csv);

        self::assertCount(2, $rows);

        self::assertSame(2, $rows[0]->lineNumber);
        self::assertSame('12', $rows[0]->productId);
        self::assertSame('branch', $rows[0]->scope);
        self::assertSame('7', $rows[0]->targetId);
        self::assertSame('99.90', $rows[0]->price);
        self::assertNull($rows[0]->error);

        self::assertSame('general', $rows[1]->scope);
        self::assertNull($rows[1]->targetId);
    }

    public function testHeaderOrderDoesNotMatter(): void
    {
        $csv = "price,product_id,scope\n49.90,12,general\n";

        $rows = (new PriceRuleImportParser())->parse($csv);

        self::assertCount(1, $rows);
        self::assertSame('12', $rows[0]->productId);
        self::assertSame('49.90', $rows[0]->price);
    }

    public function testHeaderIsCaseInsensitive(): void
    {
        $csv = "Product_Id,Scope,Price\n12,general,49.90\n";

        $rows = (new PriceRuleImportParser())->parse($csv);

        self::assertSame('12', $rows[0]->productId);
    }

    public function testScopeIsNormalizedToLowercase(): void
    {
        $csv = "product_id,scope,price\n12,GENERAL,49.90\n";

        $rows = (new PriceRuleImportParser())->parse($csv);

        self::assertSame('general', $rows[0]->scope);
        self::assertNull($rows[0]->error);
    }

    public function testFlagsAnUnrecognizedScope(): void
    {
        $csv = "product_id,scope,price\n12,platform,49.90\n";

        $rows = (new PriceRuleImportParser())->parse($csv);

        self::assertCount(1, $rows);
        self::assertNotNull($rows[0]->error);
        self::assertStringContainsString('platform', $rows[0]->error);
    }

    public function testFlagsAMissingRequiredField(): void
    {
        $csv = "product_id,scope,price\n12,,49.90\n";

        $rows = (new PriceRuleImportParser())->parse($csv);

        self::assertCount(1, $rows);
        self::assertNotNull($rows[0]->error);
        self::assertStringContainsString('scope', $rows[0]->error);
    }

    public function testFlagsAColumnCountMismatch(): void
    {
        $csv = "product_id,scope,price\n12,general\n";

        $rows = (new PriceRuleImportParser())->parse($csv);

        self::assertCount(1, $rows);
        self::assertNotNull($rows[0]->error);
    }

    public function testSkipsBlankLines(): void
    {
        $csv = "product_id,scope,price\n\n12,general,49.90\n\n";

        $rows = (new PriceRuleImportParser())->parse($csv);

        self::assertCount(1, $rows);
    }

    public function testStripsAUtf8BomFromTheHeaderLine(): void
    {
        $csv = "\xEF\xBB\xBFproduct_id,scope,price\n12,general,49.90\n";

        $rows = (new PriceRuleImportParser())->parse($csv);

        self::assertCount(1, $rows);
        self::assertNull($rows[0]->error);
    }

    public function testEmptyInputProducesNoRows(): void
    {
        self::assertSame([], (new PriceRuleImportParser())->parse(''));
    }

    public function testHeaderOnlyInputProducesNoRows(): void
    {
        self::assertSame([], (new PriceRuleImportParser())->parse('product_id,scope,price'));
    }
}
