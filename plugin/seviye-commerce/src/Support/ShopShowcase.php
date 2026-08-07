<?php

declare(strict_types=1);

namespace Seviye\Commerce\Support;

/**
 * "Mağaza Vitrini" - mağaza ana sayfasının (is_shop(), kategori arşivleri
 * DEĞİL - onların zaten kendi görsel/açıklaması var, bkz.
 * inc/woocommerce.php'deki scp_render_category_banner()) en üstünde
 * gösterilen isteğe bağlı hero bölümü: başlık, alt başlık, arka plan
 * görseli (WordPress medya kütüphanesinden bir attachment id - Core'un
 * BrandingRestController'ıyla AYNI "yeni bir yükleme uç noktası icat
 * etme, /wp/v2/media'yı yeniden kullan" ilkesi). Tek bir JSON-encoded
 * {@see \Seviye\Core\Settings\SettingsRepositoryInterface} değeri olarak
 * saklanıyor - Support\SizeGuide'la aynı "yapı çağıranın kendi
 * encode/decode'una ait" ilkesi, farkı bir LİSTE değil TEK bir nesne
 * olması.
 */
final class ShopShowcase
{
    public const SETTING_KEY = 'commerce.shop_showcase';

    public function __construct(
        public readonly string $heading = '',
        public readonly string $subheading = '',
        public readonly ?int $imageAttachmentId = null
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->heading === '' && $this->subheading === '' && $this->imageAttachmentId === null;
    }

    /**
     * @return array{heading: string, subheading: string, image_attachment_id: ?int}
     */
    public function toArray(): array
    {
        return [
            'heading' => $this->heading,
            'subheading' => $this->subheading,
            'image_attachment_id' => $this->imageAttachmentId,
        ];
    }

    public static function fromArray(array $data): self
    {
        $imageAttachmentId = $data['image_attachment_id'] ?? null;

        return new self(
            trim((string) ($data['heading'] ?? '')),
            trim((string) ($data['subheading'] ?? '')),
            $imageAttachmentId !== null && (int) $imageAttachmentId > 0 ? (int) $imageAttachmentId : null
        );
    }

    public static function parse(?string $raw): self
    {
        if ($raw === null || trim($raw) === '') {
            return new self();
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return new self();
        }

        return self::fromArray($decoded);
    }

    public static function serialize(self $showcase): string
    {
        $encoded = function_exists('wp_json_encode')
            ? wp_json_encode($showcase->toArray())
            : json_encode($showcase->toArray());

        return $encoded !== false ? $encoded : '{}';
    }
}
