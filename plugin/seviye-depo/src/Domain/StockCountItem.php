<?php

declare(strict_types=1);

namespace Seviye\Depo\Domain;

/**
 * `expectedQuantity` sayım AÇILDIĞI anda WC'den alınan anlık görüntü
 * (bkz. WpdbStockCountRepository::open()) - sayım sürerken satışların
 * bu değeri etkilemesi bilinçli olarak önlenmiyor (plan dokümanının kabul
 * ettiği risk). `countedQuantity` fiziksel sayım girilene kadar null.
 */
final class StockCountItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $stockCountId,
        public readonly int $productId,
        public readonly int $expectedQuantity,
        public readonly ?int $countedQuantity
    ) {
    }

    /** counted - expected, ya da henüz sayılmadıysa null. */
    public function variance(): ?int
    {
        return $this->countedQuantity === null ? null : $this->countedQuantity - $this->expectedQuantity;
    }
}
