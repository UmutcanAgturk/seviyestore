<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Domain;

/**
 * quantityRequested şube müdürünün girdiği miktar - değişmez. freeQuantityApplied/
 * paidQuantity/unitPrice ise Genel Merkez'in approve() anında
 * {@see \Seviye\SubeSiparis\Support\BranchOrderSplitCalculator} ile
 * hesaplanıp YAZILIR (sipariş SUBMITTED olduğu sürece hepsi 0/null) - bu
 * alanların DRAFT/SUBMITTED aşamasında henüz anlamı yoktur, yalnızca
 * approve() sonrası donmuş bir kayıttır (o anki kota tüketimine göre; aynı
 * ürüne sonradan farklı bir kota tanımlansa bile bu sipariş kalemi
 * değişmez).
 */
final class BranchOrderItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $branchOrderId,
        public readonly int $productId,
        public readonly int $quantityRequested,
        public readonly int $freeQuantityApplied,
        public readonly int $paidQuantity,
        public readonly ?float $unitPrice
    ) {
    }

    public function hasPaidPortion(): bool
    {
        return $this->paidQuantity > 0;
    }

    public function paidAmount(): float
    {
        if ($this->unitPrice === null) {
            return 0.0;
        }

        return round($this->paidQuantity * $this->unitPrice, 2);
    }
}
