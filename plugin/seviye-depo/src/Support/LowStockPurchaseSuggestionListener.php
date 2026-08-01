<?php

declare(strict_types=1);

namespace Seviye\Depo\Support;

use Seviye\Core\Events\Event;
use Seviye\Depo\Repository\PurchaseSuggestionRepositoryInterface;

/**
 * Listens for `commerce.product_low_stock`, dispatched by
 * Seviye\Commerce\Http\LowStockNotificationHooks - this module never
 * depends on Seviye Commerce's classes or Contracts, only on that
 * documented event name/payload shape (`product_id`, `variation_id`,
 * `product_name`, `stock_quantity`), exactly mirroring Notifications'
 * LowStockNotificationListener's relationship to Commerce.
 *
 * "Düşük stok uyarısının otomatik satın alma önerisine bağlanması" (plan
 * dokümanı, faz 2) - bu yeni bir bildirim kanalı DEĞİL, var olan event'in
 * Depo tarafındaki İKİNCİ dinleyicisi (Notifications'ın panel/e-posta
 * bildirimi ayrıca ve bağımsız çalışmaya devam eder).
 *
 * `hasPending()` ile dedup edilir: WC aynı ürün için stok her satışta
 * eşiğin altında kaldığı sürece bu hook'u tekrar tekrar ateşleyebilir -
 * zaten bekleyen (henüz reddedilmemiş/siparişe çevrilmemiş) bir öneri
 * varsa ikinci bir tane açılmaz. `suggestedQuantity` sabit bir varsayılan
 * (bkz. DEFAULT_SUGGESTED_QUANTITY) - kesin bir sipariş değil, yalnızca
 * bir başlangıç noktası; Depo görevlisi Satın Alma Önerileri panelinde
 * siparişe çevirirken miktarı serbestçe değiştirebilir.
 */
final class LowStockPurchaseSuggestionListener
{
    private const DEFAULT_SUGGESTED_QUANTITY = 20;

    public function __construct(private readonly PurchaseSuggestionRepositoryInterface $suggestions)
    {
    }

    public function onLowStock(Event $event): void
    {
        $variationId = $event->get('variation_id');
        $productId = $variationId !== null ? (int) $variationId : (int) $event->get('product_id');

        if ($productId <= 0 || $this->suggestions->hasPending($productId)) {
            return;
        }

        $this->suggestions->create($productId, self::DEFAULT_SUGGESTED_QUANTITY, $this->reasonFor($event));
    }

    private function reasonFor(Event $event): string
    {
        $productName = (string) $event->get('product_name');
        $stockQuantity = (int) $event->get('stock_quantity');

        return function_exists('__')
            ? sprintf(
                /* translators: 1: product name, 2: remaining stock quantity */
                __('"%1$s" düşük stok eşiğinin altına düştü - kalan adet: %2$d.', 'seviye-depo'),
                $productName,
                $stockQuantity
            )
            : sprintf('"%s" düşük stok eşiğinin altına düştü - kalan adet: %d.', $productName, $stockQuantity);
    }
}
