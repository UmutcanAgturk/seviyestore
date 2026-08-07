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
 * `product_name`, `stock_quantity`, `low_stock_amount`), exactly mirroring
 * Notifications' LowStockNotificationListener's relationship to Commerce.
 *
 * "Düşük stok uyarısının otomatik satın alma önerisine bağlanması" (plan
 * dokümanı, faz 2) - bu yeni bir bildirim kanalı DEĞİL, var olan event'in
 * Depo tarafındaki İKİNCİ dinleyicisi (Notifications'ın panel/e-posta
 * bildirimi ayrıca ve bağımsız çalışmaya devam eder).
 *
 * `hasPending()` ile dedup edilir: WC aynı ürün için stok her satışta
 * eşiğin altında kaldığı sürece bu hook'u tekrar tekrar ateşleyebilir -
 * zaten bekleyen (henüz reddedilmemiş/siparişe çevrilmemiş) bir öneri
 * varsa ikinci bir tane açılmaz. `suggestedQuantity`, ürünün kendi
 * `low_stock_amount` eşiğine göre HESAPLANIYOR - bkz. suggestedQuantityFor():
 * kesin bir sipariş değil, yalnızca bir başlangıç noktası; Depo görevlisi
 * Satın Alma Önerileri panelinde siparişe çevirirken miktarı serbestçe
 * değiştirebilir.
 *
 * Faz 4: hangi deponun önerisi olduğu Commerce'in ProductOwnership'inden
 * (`scp_commerce_product_owner_branch_id` filter köprüsü - bkz.
 * DepoModule'ün kendi docblock'u) çözümlenip create()'e YAZMA anında
 * dondurularak geçiriliyor; convert() bu değeri aynen yeni açılan
 * PurchaseOrder'a taşır (bkz. PurchaseSuggestionsRestController::convert()).
 */
final class LowStockPurchaseSuggestionListener
{
    /**
     * Yalnızca event'te low_stock_amount YOKSA kullanılır (eski payload
     * şekli/WC < 5.4, wc_get_low_stock_amount()'tan önce - bkz.
     * LowStockNotificationHooks::onLowStock()'un docblock'u).
     */
    private const FALLBACK_THRESHOLD = 20;

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

        // Sahiplik meta'sı yalnızca ÜST üründe tutuluyor (bkz.
        // ProductOwnership'in kendi docblock'u) - varyasyonlarda değil, bu
        // yüzden burada $productId (varyasyonsa varyasyon id'si) DEĞİL,
        // event'in her zaman üst ürünü taşıyan `product_id` alanı kullanılıyor
        // (bkz. LowStockNotificationHooks::onLowStock()'un `$parentId` mantığı).
        $branchId = function_exists('apply_filters')
            ? apply_filters('scp_commerce_product_owner_branch_id', null, (int) $event->get('product_id'))
            : null;
        $branchId = $branchId !== null ? (int) $branchId : null;

        $this->suggestions->create($productId, $this->suggestedQuantityFor($event), $this->reasonFor($event), $branchId);
    }

    /**
     * Eşiğin (reorder point) kabaca iki katına stoklanacak şekilde
     * hesaplanıyor: `threshold * 2 - stockQuantity` - eşiğin daha da
     * altına düşmüş bir ürün, eşiği yeni geçmiş bir üründen daha büyük bir
     * miktar öneriyor. `max(..., $threshold, 1)` iki uç durumu koruyor:
     * threshold=0 olan bir üründe negatif/sıfır bir miktar önerilmesin,
     * ve stok zaten eşiğin çok altındaysa öneri en azından eşik kadar olsun.
     */
    private function suggestedQuantityFor(Event $event): int
    {
        $rawThreshold = $event->get('low_stock_amount');
        $threshold = $rawThreshold !== null ? (int) $rawThreshold : self::FALLBACK_THRESHOLD;
        $stockQuantity = (int) $event->get('stock_quantity');

        return max($threshold * 2 - $stockQuantity, $threshold, 1);
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
