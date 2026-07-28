# Seviye Commerce Platform (SCP)

Türkiye genelindeki Seviye Eğitim Kurumları için şube yönetimi, öğrenci/veli
yönetimi, WooCommerce tabanlı e-ticaret, sipariş, finans, hakediş ve genel
merkez takibini tek panelden yöneten bir WordPress platformu.

Bu bir ERP + CRM + E-Commerce + Franchise Management platformudur —
sıradan bir WooCommerce sitesi değildir.

## Mimari

Katmanlı, modüler bir mimari kullanılır. Her modül bağımsız bir WordPress
eklentisidir, başka hiçbir modüle doğrudan bağımlı değildir; tüm modüller
yalnızca **Seviye Core** üzerinden (DI container, event bus, RBAC, migration
runner) haberleşir.

Ayrıntılı mimari kararlar ve gerekçeleri için: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

## Klasör yapısı

```
/plugin     Her biri bağımsız bir WordPress eklentisi olan Seviye modülleri
/theme      Seviye Storefront teması (giriş ekranı, veli/şube/admin panelleri)
/docs       Mimari, kod standartları, yol haritası
/database   Tablo adlandırma kuralları, migration rehberi, referans şema
/assets     Modüller/tema arası paylaşılan tasarım kaynakları
/tests      Modüller-arası entegrasyon testleri (gelecek faz)
/scripts    Geliştirme/CI yardımcı script'leri
/.github    CI iş akışları
```

## Modüller

| Plugin | Durum |
|---|---|
| Seviye Core | ✅ Kuruldu |
| Seviye Students, Parents, Branches, Pricing, Commerce, Finance, Reports, Notifications, API, Security | Planlandı |

Tam yol haritası: [`docs/ROADMAP.md`](docs/ROADMAP.md).

## Geliştirme ortamı kurulumu

Gereksinimler: PHP 8.2+, Composer, WordPress 6.5+, WooCommerce (aktif).

```bash
# Kod standardı araçları (kök seviye)
composer install

# Seviye Core eklentisi
cd plugin/seviye-core
composer install
```

`plugin/seviye-core` klasörünü WordPress'in `wp-content/plugins/` altına
sembolik link ile bağlayın, ardından WooCommerce'i ve Seviye Core'u aktive
edin. Aktivasyon sırasında Core; PHP sürümünü ve WooCommerce'in aktif
olduğunu doğrular, 9 platform rolünü kaydeder ve kendi migration'larını
(`scp_logs`, `scp_settings`, `scp_migrations`) çalıştırır.

## Test

```bash
cd plugin/seviye-core
composer test   # PHPUnit birim testleri
```

Kök dizinde kod standardı denetimi:

```bash
composer install
vendor/bin/phpcs
```

## Kod standartları

Bkz. [`docs/CODING_STANDARDS.md`](docs/CODING_STANDARDS.md) — PHP 8.2+ tür
bildirimleri, PSR-4/PSR-11/PSR-3 uyumluluğu, SOLID, güvenlik kontrol listesi.
