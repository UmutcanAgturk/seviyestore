<?php

declare(strict_types=1);

namespace Seviye\Notifications\Support;

/**
 * Builds the "terk edilmiş sepet hatırlatma" e-postasının konu/gövde
 * metnini - {@see WeeklyDigestBuilder}'ın AYNI "Support classes stay pure,
 * Http classes touch the platform" ayrımı: WordPress/WooCommerce'e hiç
 * dokunmuyor, Http\AbandonedCartReminderHooks zaten toplanmış ürün
 * listesini ve sepet linkini buraya veriyor.
 *
 * __()'nin $text argümanı literal string kalmalı (WordPress'in kendi i18n
 * araçları kaynağı harfiyen tarıyor) - bkz. WeeklyDigestBuilder'ın aynı
 * docblock notu.
 */
final class AbandonedCartReminderBuilder
{
    /**
     * @param list<array{name: string, quantity: int}> $items
     */
    public function build(array $items, string $cartUrl): AbandonedCartReminderMessage
    {
        $subject = function_exists('__')
            ? __('Sepetinizde ürünler sizi bekliyor - Seviye Commerce Platform', 'seviye-notifications')
            : 'Sepetinizde ürünler sizi bekliyor - Seviye Commerce Platform';

        $intro = function_exists('__')
            ? __('Sepetinize eklediğiniz aşağıdaki ürünler henüz satın alınmadı:', 'seviye-notifications')
            : 'Sepetinize eklediğiniz aşağıdaki ürünler henüz satın alınmadı:';

        $itemLineTemplate = function_exists('__')
            /* translators: 1: product name, 2: quantity in cart */
            ? __('- %1$s (adet: %2$d)', 'seviye-notifications')
            : '- %1$s (adet: %2$d)';

        $outro = function_exists('__')
            /* translators: %s: URL to the shopping cart page */
            ? __('Sepetinizi tamamlamak için: %s', 'seviye-notifications')
            : 'Sepetinizi tamamlamak için: %s';

        $lines = [$intro, ''];

        foreach ($items as $item) {
            $lines[] = sprintf($itemLineTemplate, $item['name'], $item['quantity']);
        }

        $lines[] = '';
        $lines[] = sprintf($outro, $cartUrl);

        return new AbandonedCartReminderMessage($subject, implode("\n", $lines));
    }
}
