<?php

declare(strict_types=1);

namespace Seviye\Pricing\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Pricing\Contracts\PriceSource;
use Seviye\Pricing\Domain\PriceScope;
use Seviye\Pricing\Support\PriceResolver;
use Seviye\Pricing\Tests\Fakes\FakePriceRuleRepository;
use Seviye\Pricing\Tests\Fakes\FakeStudentLookup;

final class PriceResolverTest extends TestCase
{
    public function testFallsBackToWooCommercePriceWhenNoRuleMatchesAnything(): void
    {
        $resolver = new PriceResolver(new FakePriceRuleRepository(), new FakeStudentLookup());

        $result = $resolver->resolve(100, null, null, 149.90);

        self::assertSame(149.90, $result->amount);
        self::assertSame(PriceSource::FALLBACK, $result->source);
    }

    public function testGeneralRuleWinsOverFallback(): void
    {
        $rules = new FakePriceRuleRepository();
        $rules->put(100, PriceScope::general(), 129.90);

        $resolver = new PriceResolver($rules, new FakeStudentLookup());
        $result = $resolver->resolve(100, null, null, 149.90);

        self::assertSame(129.90, $result->amount);
        self::assertSame(PriceSource::GENERAL, $result->source);
    }

    public function testExplicitBranchRuleWinsOverGeneral(): void
    {
        $rules = new FakePriceRuleRepository();
        $rules->put(100, PriceScope::general(), 129.90);
        $rules->put(100, PriceScope::forBranch(7), 99.90);

        $resolver = new PriceResolver($rules, new FakeStudentLookup());
        $result = $resolver->resolve(100, null, 7, 149.90);

        self::assertSame(99.90, $result->amount);
        self::assertSame(PriceSource::BRANCH, $result->source);
    }

    public function testStudentRuleWinsOverBranchAndGeneral(): void
    {
        $rules = new FakePriceRuleRepository();
        $rules->put(100, PriceScope::general(), 129.90);
        $rules->put(100, PriceScope::forBranch(7), 99.90);
        $rules->put(100, PriceScope::forStudent(3), 49.90);

        $resolver = new PriceResolver($rules, new FakeStudentLookup());
        $result = $resolver->resolve(100, 3, 7, 149.90);

        self::assertSame(49.90, $result->amount);
        self::assertSame(PriceSource::STUDENT, $result->source);
    }

    public function testBranchIdIsDerivedFromStudentWhenNotGivenExplicitly(): void
    {
        $rules = new FakePriceRuleRepository();
        $rules->put(100, PriceScope::forBranch(7), 99.90);

        $students = new FakeStudentLookup();
        $students->put(3, 7);

        $resolver = new PriceResolver($rules, $students);
        $result = $resolver->resolve(100, 3, null, 149.90);

        self::assertSame(99.90, $result->amount);
        self::assertSame(PriceSource::BRANCH, $result->source);
    }

    public function testUnknownStudentWithNoBranchIdFallsThroughToGeneral(): void
    {
        $rules = new FakePriceRuleRepository();
        $rules->put(100, PriceScope::general(), 129.90);

        $resolver = new PriceResolver($rules, new FakeStudentLookup());
        $result = $resolver->resolve(100, 999, null, 149.90);

        self::assertSame(129.90, $result->amount);
        self::assertSame(PriceSource::GENERAL, $result->source);
    }
}
