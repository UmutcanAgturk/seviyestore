# Kod Standartları

## Genel

- PHP 8.2+ hedeflenir; her dosyanın başında `declare(strict_types=1);`.
- Tüm sınıf/metot imzalarında tam tür bildirimleri (`string`, `int|null`,
  `readonly`, dönüş tipleri dahil) zorunludur.
- Tüm kod (sınıf/metot/değişken isimleri, yorumlar, commit mesajları)
  İngilizce yazılır. Kullanıcıya gösterilen metinler `__()` / `_e()` /
  `esc_html__()` ile `seviye-core` (veya ilgili modülün) text domain'i
  üzerinden yönetilir — asla hard-coded Türkçe/İngilizce arayüz metni.
- Magic number/string kullanılmaz; sabitler (`const`) veya `enum` kullanılır.
  Örnek: rol isimleri `Rbac\Role` enum'unda, yetkiler `Rbac\Capability`
  enum'unda tanımlıdır.
- PHPDoc: generic olmayan her public API (`array` şekli belirsizse
  `@param array<string, mixed>`, `@return list<string>` gibi) eksiksiz
  belgelenir. Kendini açıklayan kod için ekstra yorum eklenmez; yorumlar
  yalnızca "neden" böyle yapıldığını açıklamak için kullanılır.

## PSR uyumluluğu

- **PSR-4** otomatik yükleme: her plugin kendi `Seviye\{Modül}\` namespace
  kökünü `src/` altına eşler (`composer.json` → `autoload.psr-4`).
- **PSR-11**: `Container\ServiceContainer`, `Psr\Container\ContainerInterface`
  uygular.
- **PSR-3**: loglama `Psr\Log\LoggerInterface` üzerinden yapılır
  (`Logging\DatabaseLogger`).
- **PSR-12** temel kod stili; WordPress'e özgü güvenlik kuralları (esc_*,
  prepared statements) WordPress Coding Standards (WPCS) ile birlikte
  `phpcs.xml.dist` üzerinden denetlenir.

## SOLID / mimari kurallar

- **Tek sorumluluk**: her sınıf tek bir işten sorumludur (`RoleRegistrar`
  yalnızca rol kaydı yapar, `RbacManager` yalnızca çalışma zamanı yetki
  kontrolü yapar — ikisi ayrı sınıflardır).
- **Bağımlılık tersine çevirme**: WordPress'e bağımlı kod (`$wpdb`,
  `add_role`, transients) her zaman bir arayüz (`ConnectionInterface`,
  `RoleGatewayInterface`, `CacheInterface`) arkasına gizlenir; iş mantığı bu
  arayüzlere bağımlıdır, somut WP implementasyonuna değil.
- **Modüller arası izolasyon**: bir modül başka bir modülün namespace'ini
  asla import etmez. Yalnızca `Seviye\Core\*` sözleşmelerine bağımlı olunur.
- **Yarım kod / placeholder yasak**: her PR, çalışan ve test edilmiş kod
  içerir. Eksik bırakılan bir özellik varsa, kapsam dışı olduğu
  `docs/ROADMAP.md`'de belirtilir; kodda `TODO`/sahte implementasyon
  bırakılmaz.

## Güvenlik kontrol listesi (her PR için)

- [ ] Kullanıcı girdisi doğrudan SQL'e enjekte edilmiyor (`$wpdb->prepare()`
      veya parametreli sorgu kullanılıyor).
- [ ] Çıktılar `esc_html()`/`esc_attr()`/`esc_url()` ile kaçışlanıyor.
- [ ] State değiştiren istekler nonce ile korunuyor.
- [ ] Yetki kontrolü `current_user_can()` / `RbacManager::currentUserCan()`
      ile yapılıyor, rol adı string karşılaştırmasıyla değil.
- [ ] Brute-force'a açık uç noktalar (`login`, `forgot-password`)
      `RateLimiter` ile korunuyor.
- [ ] Dış kaynaklı veriler (IP, header) doğrulanmadan güvenilmiyor
      (bkz. `Security\ClientIp`).

## Test

- Yeni bir port/adapter eklerken önce port arayüzünü, sonra saf PHP iş
  mantığını (adapter'sız) test eden birim testini yaz.
- `composer test` her plugin dizininde bağımsız çalışır ve CI'da zorunludur.
