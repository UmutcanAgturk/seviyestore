<?php

declare(strict_types=1);

namespace Seviye\Depo\Domain;

/**
 * "Şubeler arası stok transferi" (Faz 4'ün doğal devamı - her depo artık
 * ayrı, bir şube fazla stoğunu başka bir depoya aktarabilmeli).
 *
 * WooCommerce'in tek bir `stock_quantity` alanı vardır - depo başına ayrı
 * bir stok havuzu YOKTUR (bkz. docs/ARCHITECTURE.md, "Kural"). Bu yüzden
 * bir transfer iki FARKLI WC ürününü taşır: `fromProductId` (kaynak
 * depodaki ürün kaydı) ve `toProductId` (hedef depodaki ürün kaydı - genelde
 * hedef şubenin kendi kataloğuna daha önce eklediği "aynı ürün"ün kendi
 * kaydı). `fromBranchId`/`toBranchId`, tıpkı satın alma siparişindeki
 * `branchId` gibi, YAZMA anında Commerce'in ProductOwnership'inden
 * (`scp_commerce_product_owner_branch_id` filtre köprüsü) çözümlenip
 * donduruluyor - kullanıcı tarafından girilmiyor, ürünün sahipliğinden
 * türetiliyor (bkz. StockTransfersRestController::store()).
 *
 * PENDING -> COMPLETED iki adımlı: `store()` hiçbir stok değişikliği
 * yapmaz, yalnızca bir kayıt açar - fiziksel mal henüz yola çıkmamıştır.
 * `complete()` (hedef depo tarafından "teslim aldım" onayı, PurchaseOrder'ın
 * receive()'ıyla aynı gerekçe) hem kaynaktan düşürür hem hedefe ekler VE
 * StockMovementRepositoryInterface'e biri TRANSFER_OUT biri TRANSFER_IN
 * olmak üzere iki defter satırı yazar - bir depo hem "neden azaldığını"
 * hem diğer depo "neden arttığını" kendi tarafından görebilsin.
 */
final class StockTransfer
{
    public function __construct(
        public readonly int $id,
        public readonly int $fromProductId,
        public readonly int $toProductId,
        public readonly int $quantity,
        public readonly ?int $fromBranchId,
        public readonly ?int $toBranchId,
        public readonly StockTransferStatus $status,
        public readonly ?string $note,
        public readonly int $requestedByUserId,
        public readonly ?int $completedByUserId,
        public readonly string $createdAt,
        public readonly ?string $completedAt
    ) {
    }
}
