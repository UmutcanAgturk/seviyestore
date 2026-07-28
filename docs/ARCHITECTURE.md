# Mimari

## Katman modeli

Ürün spesifikasyonundaki `Core → Modules → WooCommerce Integration → Theme →
REST API → Database` akışı, bir istek/veri akışı sırasını tarif eder; bağımlılık
yönü bunun tersidir ve merkezde **Core** durur (Hexagonal / Ports & Adapters):

```
                ┌───────────────────────────┐
                │           Theme            │
                │  (giriş ekranı, veli/şube/  │
                │   admin panelleri)          │
                └──────────────┬─────────────┘
                               │ tüketir
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
┌───────▼──────┐      ┌────────▼────────┐     ┌───────▼───────┐
│ Seviye Students│      │ Seviye Commerce │     │  Seviye API    │
│ Seviye Parents │      │ (WooCommerce     │     │ (seviye/v1 REST│
│ Seviye Branches│      │  entegrasyonu)   │     │  namespace)    │
│ Seviye Pricing │      │ Seviye Finance   │     │                │
│ ...            │      │ Seviye Reports   │     │                │
└───────┬────────┘      └────────┬─────────┘     └───────┬────────┘
        │                        │                       │
        └────────────┬───────────┴───────────────────────┘
                      │  yalnızca Core sözleşmeleri üzerinden
              ┌───────▼────────┐
              │  Seviye Core    │
              │  Container      │
              │  EventBus       │
              │  RBAC           │
              │  MigrationRunner│
              │  Logging        │
              │  RestApiRegistrar│
              └───────┬────────┘
                      │
              ┌───────▼────────┐
              │    Database     │
              │  (scp_* tablolar)│
              └────────────────┘
```

**Kural:** Hiçbir modül başka bir modülün sınıfını doğrudan import edemez.
Modüller yalnızca Core'un container'ından çözümlenen servislerle konuşur
(`EventBusInterface`, `RbacManager`, `MigrationRunner`, PSR-3 `LoggerInterface`).

## Neden bu tasarım

### 1. Ports & Adapters ile WordPress'ten ayrıştırma

Core, WordPress'e (`$wpdb`, transients, rol API'si) doğrudan bağımlı değildir.
Bunun yerine port arayüzleri tanımlar ve WP-özel implementasyonları adapter
olarak container'a bağlar:

| Port | Adapter (WordPress) | Neden |
|---|---|---|
| `Database\ConnectionInterface` | `Database\WpdbConnection` | Migration/log mantığı WP olmadan unit test edilebilir |
| `Cache\CacheInterface` | `Cache\TransientCache` | Rate limiter mantığı WP olmadan test edilebilir |
| `Rbac\RoleGatewayInterface` | `Rbac\WpRoleGateway` | Rol kayıt mantığı WP olmadan test edilebilir |
| `Psr\Log\LoggerInterface` | `Logging\DatabaseLogger` | PSR-3 uyumlu, değiştirilebilir (ör. ileride Sentry/ELK adapter'ı eklenebilir) |

Bu, "her modül test edilebilir ve genişletilebilir olsun" gereksinimini
gerçek anlamda karşılar: iş mantığı WordPress bootstrap'ı gerektirmeden
`phpunit` ile doğrulanır.

### 2. Saf PHP Event Bus (WP hook'larının yerine değil, üzerine)

`Events\EventBus`, WordPress'in `do_action`/`apply_filters` mekanizmasını
sarmalamak yerine kendi başına, tipli bir in-process pub/sub sistemidir.
Sebep: modüller arası sözleşmeleri (`Event` nesnesi, isim, payload) açık ve
statik analiz edilebilir tutmak, WP'nin serbest string tabanlı hook isimlerine
bağımlı kalmamak. Yine de her `dispatch()`, `seviye/core/event/{isim}` WP
action'ını da tetikler; böylece tema, mu-plugin veya Elementor entegrasyonları
alışılmış `add_action()` ile de dinleyebilir. **Bu, spesifikasyonun ötesinde
bilinçli bir mimari tercihtir** — WooCommerce ve WP ekosistemiyle uyumluluğu
kaybetmeden modüller arası sözleşmeyi güçlendirir.

### 3. Modül kayıt akışı

Her modül ayrı bir WordPress eklentisidir. Core, `plugins_loaded` önceliği 0'da
container'ını kurar; modüller varsayılan öncelik olan 10'da kendilerini
`Plugin::instance()->modules()->register(new XModule())` ile kaydeder; Core
öncelik 20'de tüm modüllerin `boot()` metodunu çağırır ve REST API'yi açar.
Bkz. `plugin/seviye-core/src/Module/ModuleInterface.php`.

### 4. RBAC

9 rol (`Rbac\Role` enum'u) Core'a aittir ve kapalıdır — yeni rol eklemek
Core'un sorumluluğundadır. Yetkiler (capabilities) ise açıktır: her modül
kendi capability sabitlerini/enum'unu tanımlar ve `RbacManager::grantCapability()`
ile ilgili role ekler. Böylece Students modülü "öğrenci yönetebilir" yetkisini
Şube Müdürü rolüne eklerken Core'un bunu önceden bilmesi gerekmez.

### 5. Migration sistemi

`MigrationRunner`, versiyon numarasına göre sıralı, idempotent migration'ları
`dbDelta()` üzerinden çalıştırır ve `scp_migrations` tablosunda hangi
versiyonların uygulandığını tutar. Her modül kendi migration'larını, kendi
aktivasyon hook'unda, container'dan aldığı `MigrationRunner`'a register eder.
Rollback (`down()`) arayüzde tanımlıdır ancak CLI destekli rollback
orkestrasyonu henüz yazılmadı — bu, ilk gerçek rollback ihtiyacı doğduğunda
(YAGNI) eklenecek.

### 6. Güvenlik

- `Security\RateLimiter`: sabit pencereli deneme sayacı; TC Kimlik No + şifre
  giriş formunu brute-force'a karşı korumak için tasarlandı.
- `Security\ClientIp`: `X-Forwarded-For` varsayılan olarak **güvenilmez**
  (spoofable); yalnızca `SCP_TRUST_PROXY` sabiti `true` tanımlıysa dikkate
  alınır.
- `DatabaseLogger`: her audit kaydı `user_id`, `ip_address`, `created_at` ile
  `scp_logs` tablosuna yazılır (KVKK/denetim gereksinimi).
- Uninstall akışı bilinçli olarak veri silmez (bkz. `uninstall.php`).

## Tablo adlandırma kuralı

`{$wpdb->prefix}scp_{entity}` — bkz. `database/README.md`. Bu, tek bir yerde
(`ConnectionInterface::table()`) merkezileştirilmiştir; hiçbir modül tablo
adını elle birleştirmemelidir.

## Test stratejisi

- **Birim testleri** (`plugin/*/tests/Unit`): WordPress'e bağımlı olmayan iş
  mantığı (Container, EventBus, RateLimiter, MigrationRunner, RoleDefinitions)
  saf PHPUnit ile test edilir; WP fonksiyonlarına ihtiyaç duyan adapter'lar
  (`WpdbConnection`, `WpRoleGateway`, `TransientCache`) sahte (fake) port
  implementasyonlarıyla dolaylı olarak doğrulanır.
- **Entegrasyon testleri** (gelecek faz): `wp-env` + `WP_UnitTestCase`
  tabanlı, gerçek WordPress/MySQL üzerinde çalışan testler. Bu milestone'da
  kapsam dışı bırakıldı; bkz. `tests/README.md`.

## Değerlendirilen ama seçilmeyen alternatifler

- **WooCommerce fiyat/kupon sistemini genişletmek yerine tamamen özel
  fiyatlandırma motoru** (spesifikasyonda zaten belirtilmiş): doğru tercih,
  çünkü şube/öğrenci/kardeş/burs önceliklendirmesi WC'nin fiyat modeline
  temiz şekilde oturmuyor. Seviye Pricing modülü bunu WooCommerce'in
  `woocommerce_product_get_price` filtrelerine son katmanda entegre edecek.
- **Doğrudan WP hook'ları yerine EventBus**: yukarıda 2. maddede açıklandı.
- **FK kısıtlamaları**: WordPress çekirdek tabloları geleneksel olarak FK
  kullanmaz, ama `scp_*` tabloları InnoDB üzerinde gerçek FK kısıtlamalarıyla
  kurulmalıdır (spesifikasyonun kendisi de bunu istiyor). Bu, modül-özel
  migration'lar yazılırken uygulanacak.
