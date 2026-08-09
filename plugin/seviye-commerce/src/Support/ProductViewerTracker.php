<?php

declare(strict_types=1);

namespace Seviye\Commerce\Support;

/**
 * "Bu ürünü şu an X kişi görüntülüyor" sosyal kanıt sayacı - GERÇEK bir
 * sayı, uydurma/rastgele bir rakam DEĞİL. Yeni bir presence/websocket
 * altyapısı İCAT ETMİYOR - WordPress'in KENDİ geçici (transient) önbelleğini
 * kullanıyor: ürün sayfasını görüntüleyen her giriş yapmış kullanıcı kendi
 * `user_id`'sini bir "şu an bakıyor" listesine ekliyor, liste
 * `WINDOW_SECONDS` (3 dakika) sonra otomatik süresi doluyor - ayrı bir
 * "görüntülemeyi bitir" isteği/temizlik cron'u GEREKMİYOR. Yalnızca giriş
 * yapmış kullanıcılar sayılıyor (bu platformda anonim ziyaretçi zaten yok -
 * her rol giriş yapmak zorunda), bu yüzden anonim ziyaretçi kimliklendirme
 * (çerez/IP bazlı) gibi bir gizlilik sorunu da yok.
 */
final class ProductViewerTracker
{
    public const WINDOW_SECONDS = 180;

    public function recordView(int $productId, int $userId): int
    {
        $key = self::transientKey($productId);
        $viewers = get_transient($key);
        $viewers = is_array($viewers) ? $viewers : [];

        $cutoff = time() - self::WINDOW_SECONDS;
        $viewers = array_filter($viewers, static fn (int $seenAt): bool => $seenAt >= $cutoff);

        $viewers[$userId] = time();

        set_transient($key, $viewers, self::WINDOW_SECONDS);

        return count($viewers);
    }

    private static function transientKey(int $productId): string
    {
        return 'scp_product_viewers_' . $productId;
    }
}
