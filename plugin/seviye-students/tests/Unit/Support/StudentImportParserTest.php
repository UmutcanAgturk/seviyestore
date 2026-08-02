<?php

declare(strict_types=1);

namespace Seviye\Students\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Students\Support\StudentImportParser;

final class StudentImportParserTest extends TestCase
{
    public function testParsesWellFormedRows(): void
    {
        $csv = "first_name,last_name,education_year,class_name,tc_no\n"
            . "Ayşe,Yılmaz,2025-2026,5-A,12345678901\n"
            . "Mehmet,Demir,2025-2026,5-B,\n";

        $rows = (new StudentImportParser())->parse($csv);

        self::assertCount(2, $rows);

        self::assertSame(2, $rows[0]->lineNumber);
        self::assertSame('Ayşe', $rows[0]->firstName);
        self::assertSame('Yılmaz', $rows[0]->lastName);
        self::assertSame('2025-2026', $rows[0]->educationYear);
        self::assertSame('5-A', $rows[0]->className);
        self::assertSame('12345678901', $rows[0]->tcNo);
        self::assertNull($rows[0]->error);

        self::assertSame(3, $rows[1]->lineNumber);
        self::assertNull($rows[1]->tcNo);
    }

    public function testHeaderOrderDoesNotMatter(): void
    {
        $csv = "class_name,first_name,last_name,education_year\n5-A,Ayşe,Yılmaz,2025-2026\n";

        $rows = (new StudentImportParser())->parse($csv);

        self::assertCount(1, $rows);
        self::assertSame('Ayşe', $rows[0]->firstName);
        self::assertSame('5-A', $rows[0]->className);
    }

    public function testHeaderIsCaseInsensitive(): void
    {
        $csv = "First_Name,Last_Name,Education_Year,Class_Name\nAyşe,Yılmaz,2025-2026,5-A\n";

        $rows = (new StudentImportParser())->parse($csv);

        self::assertSame('Ayşe', $rows[0]->firstName);
    }

    public function testUnknownExtraColumnsAreIgnored(): void
    {
        $csv = "first_name,last_name,education_year,class_name,notes\nAyşe,Yılmaz,2025-2026,5-A,herhangi bir not\n";

        $rows = (new StudentImportParser())->parse($csv);

        self::assertCount(1, $rows);
        self::assertNull($rows[0]->error);
    }

    public function testFlagsAMissingRequiredField(): void
    {
        $csv = "first_name,last_name,education_year,class_name\nAyşe,,2025-2026,5-A\n";

        $rows = (new StudentImportParser())->parse($csv);

        self::assertCount(1, $rows);
        self::assertNotNull($rows[0]->error);
        self::assertStringContainsString('last_name', $rows[0]->error);
    }

    public function testFlagsAColumnCountMismatch(): void
    {
        $csv = "first_name,last_name,education_year,class_name\nAyşe,Yılmaz,2025-2026\n";

        $rows = (new StudentImportParser())->parse($csv);

        self::assertCount(1, $rows);
        self::assertNotNull($rows[0]->error);
    }

    public function testSkipsBlankLines(): void
    {
        $csv = "first_name,last_name,education_year,class_name\n\nAyşe,Yılmaz,2025-2026,5-A\n\n";

        $rows = (new StudentImportParser())->parse($csv);

        self::assertCount(1, $rows);
    }

    public function testStripsAUtf8BomFromTheHeaderLine(): void
    {
        $csv = "\xEF\xBB\xBFfirst_name,last_name,education_year,class_name\nAyşe,Yılmaz,2025-2026,5-A\n";

        $rows = (new StudentImportParser())->parse($csv);

        self::assertCount(1, $rows);
        self::assertNull($rows[0]->error);
    }

    public function testEmptyInputProducesNoRows(): void
    {
        self::assertSame([], (new StudentImportParser())->parse(''));
    }

    public function testHeaderOnlyInputProducesNoRows(): void
    {
        self::assertSame([], (new StudentImportParser())->parse('first_name,last_name,education_year,class_name'));
    }
}
