<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Seviye\Security\Routing\IpAllowlist;

final class IpAllowlistTest extends TestCase
{
    public function testEmptyAllowlistAllowsEveryRequest(): void
    {
        self::assertTrue(IpAllowlist::isAllowed([], '203.0.113.5'));
    }

    public function testBlankAndWhitespaceOnlyEntriesAreTreatedAsAnEmptyAllowlist(): void
    {
        self::assertTrue(IpAllowlist::isAllowed(['   ', ''], '1.2.3.4'));
    }

    public function testExactIpv4MatchIsAllowed(): void
    {
        self::assertTrue(IpAllowlist::isAllowed(['10.0.0.5'], '10.0.0.5'));
    }

    public function testDifferentIpv4IsDenied(): void
    {
        self::assertFalse(IpAllowlist::isAllowed(['10.0.0.5'], '10.0.0.6'));
    }

    public function testIpv4CidrRangeMatch(): void
    {
        self::assertTrue(IpAllowlist::isAllowed(['192.168.1.0/24'], '192.168.1.200'));
    }

    public function testIpv4CidrRangeMismatch(): void
    {
        self::assertFalse(IpAllowlist::isAllowed(['192.168.1.0/24'], '192.168.2.1'));
    }

    public function testIpv4CidrWithANonByteAlignedMask(): void
    {
        self::assertTrue(IpAllowlist::isAllowed(['10.1.0.0/20'], '10.1.15.254'));
        self::assertFalse(IpAllowlist::isAllowed(['10.1.0.0/20'], '10.1.16.1'));
    }

    public function testIpv6AddressesAreComparedByValueNotText(): void
    {
        // "::1" and "0:0:0:0:0:0:0:1" are the same address in different textual forms.
        self::assertTrue(IpAllowlist::isAllowed(['::1'], '0:0:0:0:0:0:0:1'));
    }

    public function testAnIpv4AllowlistEntryNeverMatchesAnIpv6Address(): void
    {
        self::assertFalse(IpAllowlist::isAllowed(['::1'], '127.0.0.1'));
    }

    public function testNullIpIsDeniedWhenAllowlistIsActive(): void
    {
        self::assertFalse(IpAllowlist::isAllowed(['10.0.0.5'], null));
    }

    public function testAMalformedEntryIsIgnoredRatherThanCrashing(): void
    {
        self::assertTrue(IpAllowlist::isAllowed(['not-an-ip', '10.0.0.5'], '10.0.0.5'));
    }

    public function testMultipleEntriesMatchIfAnyOneMatches(): void
    {
        self::assertTrue(IpAllowlist::isAllowed(['10.0.0.1', '10.0.0.2', '10.0.0.3'], '10.0.0.2'));
    }

    public function testParseEntriesSplitsOnNewlinesAndDropsBlankLines(): void
    {
        self::assertSame(
            ['10.0.0.1', '192.168.1.0/24'],
            IpAllowlist::parseEntries("10.0.0.1\n\n192.168.1.0/24\n")
        );
    }

    public function testParseEntriesReturnsAnEmptyListForNullOrBlankInput(): void
    {
        self::assertSame([], IpAllowlist::parseEntries(null));
        self::assertSame([], IpAllowlist::parseEntries("   \n  "));
    }
}
