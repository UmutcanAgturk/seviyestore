<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Support;

/**
 * Pure, DB-free karar mantığı: bir şubenin bir ürün için istediği miktarın
 * ne kadarı ücretsiz kotadan karşılanır, ne kadarı ücretli (kart ile ödenir)
 * kalır. Genel Merkez onayı (approve()) anında, o anki tüketim
 * (alreadyConsumed - bkz. Repository\BranchOrderRepositoryInterface::consumedFreeQuantity())
 * ile çağrılır; kararın DB'den tamamen ayrı, izole test edilebilir olması
 * için {@see \Seviye\Depo\Support\PurchaseOrderStatusCalculator} ile aynı
 * ilke.
 */
final class BranchOrderSplitCalculator
{
    /**
     * @return array{free: int, paid: int}
     */
    public function split(int $requestedQuantity, int $freeQuota, int $alreadyConsumed): array
    {
        $freeRemaining = max(0, $freeQuota - $alreadyConsumed);
        $free = min($requestedQuantity, $freeRemaining);

        return [
            'free' => $free,
            'paid' => $requestedQuantity - $free,
        ];
    }
}
