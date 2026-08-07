<?php

declare(strict_types=1);

namespace Seviye\Depo\Tests\Fakes;

use Seviye\Depo\Domain\PurchaseSuggestion;
use Seviye\Depo\Domain\PurchaseSuggestionStatus;
use Seviye\Depo\Repository\PurchaseSuggestionRepositoryInterface;

final class FakePurchaseSuggestionRepository implements PurchaseSuggestionRepositoryInterface
{
    /** @var list<PurchaseSuggestion> */
    public array $suggestions = [];

    private int $nextId = 1;

    public function create(int $productId, int $suggestedQuantity, ?string $reason, ?int $branchId = null): PurchaseSuggestion
    {
        $suggestion = new PurchaseSuggestion(
            $this->nextId++,
            $productId,
            $suggestedQuantity,
            PurchaseSuggestionStatus::PENDING,
            $reason,
            null,
            '2026-08-01 10:00:00',
            $branchId
        );

        $this->suggestions[] = $suggestion;

        return $suggestion;
    }

    public function hasPending(int $productId): bool
    {
        foreach ($this->suggestions as $suggestion) {
            if ($suggestion->productId === $productId && $suggestion->status === PurchaseSuggestionStatus::PENDING) {
                return true;
            }
        }

        return false;
    }

    public function find(int $id): ?PurchaseSuggestion
    {
        foreach ($this->suggestions as $suggestion) {
            if ($suggestion->id === $id) {
                return $suggestion;
            }
        }

        return null;
    }

    public function all(?PurchaseSuggestionStatus $status = null, int|false|null $branchId = false): array
    {
        return array_values(array_filter(
            $this->suggestions,
            static fn (PurchaseSuggestion $suggestion): bool => ($status === null || $suggestion->status === $status)
                && ($branchId === false || $suggestion->branchId === $branchId)
        ));
    }

    public function dismiss(int $id): void
    {
        $this->replaceStatus($id, PurchaseSuggestionStatus::DISMISSED, null);
    }

    public function convert(int $id, int $purchaseOrderId): void
    {
        $this->replaceStatus($id, PurchaseSuggestionStatus::CONVERTED, $purchaseOrderId);
    }

    private function replaceStatus(int $id, PurchaseSuggestionStatus $status, ?int $convertedPurchaseOrderId): void
    {
        foreach ($this->suggestions as $index => $suggestion) {
            if ($suggestion->id !== $id) {
                continue;
            }

            $this->suggestions[$index] = new PurchaseSuggestion(
                $suggestion->id,
                $suggestion->productId,
                $suggestion->suggestedQuantity,
                $status,
                $suggestion->reason,
                $convertedPurchaseOrderId,
                $suggestion->createdAt,
                $suggestion->branchId
            );
        }
    }
}
