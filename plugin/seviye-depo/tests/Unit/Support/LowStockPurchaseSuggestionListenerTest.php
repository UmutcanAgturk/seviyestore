<?php

declare(strict_types=1);

namespace Seviye\Depo\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Core\Events\Event;
use Seviye\Depo\Domain\PurchaseSuggestionStatus;
use Seviye\Depo\Support\LowStockPurchaseSuggestionListener;
use Seviye\Depo\Tests\Fakes\FakePurchaseSuggestionRepository;

final class LowStockPurchaseSuggestionListenerTest extends TestCase
{
    public function testCreatesAPendingSuggestionForASimpleProduct(): void
    {
        $repository = new FakePurchaseSuggestionRepository();
        $listener = new LowStockPurchaseSuggestionListener($repository);

        $listener->onLowStock(new Event('commerce.product_low_stock', [
            'product_id' => 500,
            'variation_id' => null,
            'product_name' => 'Okul Forması',
            'stock_quantity' => 2,
        ]));

        self::assertCount(1, $repository->suggestions);
        $suggestion = $repository->suggestions[0];
        self::assertSame(500, $suggestion->productId);
        self::assertSame(PurchaseSuggestionStatus::PENDING, $suggestion->status);
        self::assertStringContainsString('Okul Forması', (string) $suggestion->reason);
    }

    public function testUsesTheVariationIdWhenTheLowStockProductIsAVariation(): void
    {
        $repository = new FakePurchaseSuggestionRepository();
        $listener = new LowStockPurchaseSuggestionListener($repository);

        $listener->onLowStock(new Event('commerce.product_low_stock', [
            'product_id' => 500,
            'variation_id' => 512,
            'product_name' => 'Okul Forması - S / Mavi',
            'stock_quantity' => 1,
        ]));

        self::assertCount(1, $repository->suggestions);
        self::assertSame(512, $repository->suggestions[0]->productId);
    }

    public function testDoesNotOpenASecondSuggestionWhilePendingOneExists(): void
    {
        $repository = new FakePurchaseSuggestionRepository();
        $listener = new LowStockPurchaseSuggestionListener($repository);
        $event = new Event('commerce.product_low_stock', [
            'product_id' => 500,
            'variation_id' => null,
            'product_name' => 'Okul Forması',
            'stock_quantity' => 2,
        ]);

        $listener->onLowStock($event);
        $listener->onLowStock($event);

        self::assertCount(1, $repository->suggestions);
    }

    public function testOpensANewSuggestionAgainAfterThePreviousOneWasDismissed(): void
    {
        $repository = new FakePurchaseSuggestionRepository();
        $listener = new LowStockPurchaseSuggestionListener($repository);
        $event = new Event('commerce.product_low_stock', [
            'product_id' => 500,
            'variation_id' => null,
            'product_name' => 'Okul Forması',
            'stock_quantity' => 2,
        ]);

        $listener->onLowStock($event);
        $repository->dismiss($repository->suggestions[0]->id);
        $listener->onLowStock($event);

        self::assertCount(2, $repository->suggestions);
    }
}
