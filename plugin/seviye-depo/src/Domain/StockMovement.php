<?php

declare(strict_types=1);

namespace Seviye\Depo\Domain;

/**
 * One append-only defter (ledger) row - never edited or deleted after
 * creation, the same immutability principle as Finance's HakedisEntry
 * (bkz. o sınıfın docblock'u), stok geçmişi için uygulanmış hali.
 *
 * `quantityDelta` işaretlidir (+/-): stoğu ARTIRAN her olay (mal kabul,
 * pozitif sayım farkı, iade) pozitif, AZALTAN her olay (negatif sayım
 * farkı, manuel düzeltme) negatif. WooCommerce'in kendi sipariş bazlı
 * stok düşürmesi burada AYRICA kaydedilmez - bu defter yalnızca Depo
 * modülünün kendi yazdığı hareketleri tutar, WC'nin native satış
 * düşürmesinin bir kopyasını değil (bkz. docs/ARCHITECTURE.md, "Kural":
 * stok WC'nin tek doğruluk kaynağı olmaya devam eder, bu defter yalnızca
 * NEDEN değiştiğinin kaydını tutar).
 */
final class StockMovement
{
    public function __construct(
        public readonly int $id,
        public readonly int $productId,
        public readonly StockMovementType $type,
        public readonly int $quantityDelta,
        public readonly ?string $referenceType,
        public readonly ?int $referenceId,
        public readonly ?string $note,
        public readonly int $createdByUserId,
        public readonly string $createdAt,
        public readonly ?int $branchId = null
    ) {
    }
}
