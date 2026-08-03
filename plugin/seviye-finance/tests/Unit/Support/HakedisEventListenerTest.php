<?php

declare(strict_types=1);

namespace Seviye\Finance\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Core\Events\Event;
use Seviye\Finance\Domain\HakedisEntryType;
use Seviye\Finance\Support\HakedisEventListener;
use Seviye\Finance\Tests\Fakes\FakeHakedisRepository;

final class HakedisEventListenerTest extends TestCase
{
    private function payload(): array
    {
        return [
            'order_id' => 500,
            'order_item_id' => 3,
            'student_id' => 42,
            'branch_id' => 7,
            'commission_rate' => 12.5,
            'price' => 200.0,
            'vat_amount' => 36.0,
            'branch_share' => 25.0,
            'hq_share' => 175.0,
        ];
    }

    public function testOnOrderLineItemCompletedRecordsAPositiveEarnedEntry(): void
    {
        $repository = new FakeHakedisRepository();
        $listener = new HakedisEventListener($repository);

        $listener->onOrderLineItemCompleted(new Event('commerce.order_line_item_completed', $this->payload()));

        self::assertCount(1, $repository->entries);
        $entry = $repository->entries[0];
        self::assertSame(25.0, $entry->amount);
        self::assertSame(36.0, $entry->vatAmount);
        self::assertSame(HakedisEntryType::EARNED, $entry->type);
        self::assertSame(7, $entry->branchId);
        self::assertSame(25.0, $repository->balanceForBranch(7));
    }

    public function testOnOrderLineItemReversedRecordsANegativeReversedEntry(): void
    {
        $repository = new FakeHakedisRepository();
        $listener = new HakedisEventListener($repository);

        $listener->onOrderLineItemReversed(new Event('commerce.order_line_item_reversed', $this->payload()));

        self::assertCount(1, $repository->entries);
        $entry = $repository->entries[0];
        self::assertSame(-25.0, $entry->amount);
        self::assertSame(HakedisEntryType::REVERSED, $entry->type);
        self::assertSame(-25.0, $repository->balanceForBranch(7));
    }

    public function testCompletedThenReversedNetsToZeroBalance(): void
    {
        $repository = new FakeHakedisRepository();
        $listener = new HakedisEventListener($repository);

        $listener->onOrderLineItemCompleted(new Event('commerce.order_line_item_completed', $this->payload()));
        $listener->onOrderLineItemReversed(new Event('commerce.order_line_item_reversed', $this->payload()));

        self::assertSame(0.0, $repository->balanceForBranch(7));
    }

    public function testDuplicateCompletedEventIsIgnored(): void
    {
        $repository = new FakeHakedisRepository();
        $listener = new HakedisEventListener($repository);

        $event = new Event('commerce.order_line_item_completed', $this->payload());
        $listener->onOrderLineItemCompleted($event);
        $listener->onOrderLineItemCompleted($event);

        self::assertCount(1, $repository->entries);
        self::assertSame(25.0, $repository->balanceForBranch(7));
    }

    public function testOnOrderLineItemPartiallyReversedRecordsAFractionOfTheBranchShare(): void
    {
        $repository = new FakeHakedisRepository();
        $listener = new HakedisEventListener($repository);

        $payload = $this->payload() + ['refund_id' => 900, 'reversal_ratio' => 0.4];
        $listener->onOrderLineItemPartiallyReversed(new Event('commerce.order_line_item_partially_reversed', $payload));

        self::assertCount(1, $repository->entries);
        $entry = $repository->entries[0];
        self::assertSame(-10.0, $entry->amount);
        self::assertSame(HakedisEntryType::PARTIAL_REVERSAL, $entry->type);
        self::assertSame(900, $entry->refundId);
        self::assertSame(-10.0, $repository->balanceForBranch(7));
    }

    public function testTwoDistinctRefundsOnTheSameItemBothRecordTheirOwnPartialReversal(): void
    {
        $repository = new FakeHakedisRepository();
        $listener = new HakedisEventListener($repository);

        $listener->onOrderLineItemPartiallyReversed(new Event(
            'commerce.order_line_item_partially_reversed',
            $this->payload() + ['refund_id' => 900, 'reversal_ratio' => 0.4]
        ));
        $listener->onOrderLineItemPartiallyReversed(new Event(
            'commerce.order_line_item_partially_reversed',
            $this->payload() + ['refund_id' => 901, 'reversal_ratio' => 0.2]
        ));

        self::assertCount(2, $repository->entries);
        self::assertSame(-15.0, $repository->balanceForBranch(7));
    }

    public function testReplayingTheSameRefundIdIsIgnored(): void
    {
        $repository = new FakeHakedisRepository();
        $listener = new HakedisEventListener($repository);

        $event = new Event(
            'commerce.order_line_item_partially_reversed',
            $this->payload() + ['refund_id' => 900, 'reversal_ratio' => 0.4]
        );
        $listener->onOrderLineItemPartiallyReversed($event);
        $listener->onOrderLineItemPartiallyReversed($event);

        self::assertCount(1, $repository->entries);
    }
}
