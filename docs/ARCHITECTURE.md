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
│                │      │ Seviye Notifications│  │                │
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

**Kural:** Hiçbir modül başka bir modülün *internal* sınıflarını (Repository
implementasyonu, migration, entity içi mantık) doğrudan import edemez.
Modüller birbirleriyle iki şekilde konuşur:

1. **Core'un servisleri üzerinden** (`EventBusInterface`, `RbacManager`,
   `MigrationRunner`, PSR-3 `LoggerInterface`) — bildirim/olay ve genel
   altyapı için.
2. **Bir modülün açıkça yayınladığı, kararlı arayüz (interface) sözleşmesi
   üzerinden** — gerçek bir alan-modeli ilişkisi olduğunda (ör. bir Öğrenci
   gerçekten bir Şubeye bağlıdır; bu yapay bir bağımlılık değil, spesifikasyonun
   kendi veri modelidir). Bu durumda tüketici modül, üretici modülün
   **yalnızca arayüzünü** `composer.json`'da bir `path` bağımlılığı olarak
   ekler (Security'nin Core'a bağlandığı desenin aynısı) ve o arayüzü
   Core'un container'ından çözümler; üretici modülün Repository/Entity gibi
   somut sınıflarını asla import etmez. İlk örneği: `Seviye Branches`'ın
   `Contracts\BranchMembershipInterface` ve `Contracts\BranchLookupInterface`'i
   — `Seviye Students`, bir Şube Müdürü'nün hangi şubeye ait olduğunu bu
   arayüzler üzerinden öğrenir, Branches'ın `WpdbBranchRepository`'sini veya
   `Domain\Branch`'ini asla import etmez.

Fiziksel veritabanı şeması bu kuralın dışındadır: iki modülün kendi
tabloları arasında gerçek bir InnoDB FK kısıtlaması olması PHP sınıf
bağımlılığı yaratmaz (bkz. `scp_branch_users.branch_id → scp_branches.id`)
ve spesifikasyonun kendisi FK ilişkilerini açıkça istiyor.

**İkinci kural (boot sırası):** `ModuleRegistry::bootAll()` modülleri
*kayıt sırasına* göre boot eder, bu da her eklentinin sitede hangi sırayla
etkinleştirildiğine bağlıdır — deklare edilmiş bir bağımlılık grafiğine
göre değil. Bir modülün `boot()` metodu, başka bir modülün yayınladığı
Contract'ı **doğrudan `$container->get(...)` ile çözümlerse** (bir
`RestApiRegistrar::register()` closure'ı içinde DEĞİL), o modül henüz boot
olmamışsa `NotFoundException` fırlatır — ve bu, hangi eklentinin önce
etkinleştirildiğine bağlı olarak *bazı* isteklerde patlayan, bazılarında
patlamayan kırılgan bir hata sınıfı üretir (ör. `admin-ajax.php`'nin her
çağrısı, Heartbeat dahil). `RestApiRegistrar`'ın kendi closure'ları güvenli
çünkü yalnızca `rest_api_init`'te (tüm modüller boot olduktan sonra)
çalışır; aynı ilke başka bir WordPress hook'una (`add_action('init', ...)`)
erteleme için de geçerli — bkz. `CommerceModule::boot()`'taki WooCommerce
kanca kaydı ve `NotificationsModule::boot()`'taki şifre sıfırlama
dinleyicisi, ikisi de bu yüzden `init`'e ertelenmiştir.

**Üçüncü kural (migration'lar sadece aktivasyonda çalışmaz):** Her
modülün `Support\Activator::activate()`'ı kendi `MigrationRunner::run()`'ını
çağırır, ama bu yalnızca WordPress'in `register_activation_hook`'u
tetiklendiğinde çalışır - yani bir eklenti PASİF'ten AKTİF'e geçtiğinde.
Zaten AKTİF bir eklentinin zip'ini daha yeni bir sürümle DEĞİŞTİRMEK (bir
site kendi wp-admin'inden "Yükle → Mevcut olanla değiştir" yapsın ya da
temanın kurulum sihirbazı "zaten etkin, adımı atla" desin, ikisi de aynı
şekilde) bu hook'u BİR DAHA tetiklemez - yeni bir sürümde eklenen bir
migration hiçbir zaman çalışmadan kalabilir. `Plugin::boot()`, tüm
modüller `bootAll()` ile kendi migration'larını kaydettikten SONRA, her
wp-admin sayfa yüklemesinde (`is_admin()`, storefront'ta değil)
`MigrationRunner::run()`'ı da çağırır; sonuç, hangi yoldan güncellenirse
güncellensin (fresh aktivasyon veya zip değiştirme), şema bir sonraki
wp-admin ziyaretinde kendiliğinden güncel hale gelir.

**`MigrationRunner::run()` artık "zaten uygulandı" kaydını bir GATE olarak
kullanmıyor** - bu satırlar canlıda gerçekten yaşandı: `dbDelta()` (bu
kod tabanındaki HER migration'ın `up()`'ının tek mekanizması) başarısız
olduğunda asla exception fırlatmaz; eski `run()` bir migration'ı
`scp_migrations`'a "uygulandı" olarak kaydettikten sonra bir daha ASLA
tekrar denemiyordu - `dbDelta()` ilk seferinde sessizce hiçbir şey
yaratmamış olsa bile. Sonuç: `scp_user_identities` tablosu hiç
oluşmamışken kayıtlarda "uygulandı" görünüyordu, ve her yeni
`MigrationRunner::run()` çağrısı (aktivasyonda da, yeni eklenen
wp-admin-sayfa-başı otomatik çalıştırmada da) bunu sessizce atlıyordu -
tablo asla kendiliğinden iyileşemiyordu. Düzeltme: `run()` artık HER
kayıtlı migration'ın `up()`'ını HER çağrıda çalıştırır (dbDelta zaten
idempotent - şemayı canlı durumla kıyaslar, yalnızca eksik olanı
uygular); `scp_migrations` yalnızca "ilk ne zaman uygulandı" bilgisini
tutan bir denetim kaydı olarak kalıyor, bir daha çalıştırmayı engelleyen
bir kapı değil. Bu sayede kaybolmuş ya da hiç oluşmamış bir tablo, bir
sonraki `run()` çağrısında kendiliğinden onarılıyor.

**Dördüncü kural (`version()` string'leri sadece MODÜL İÇİNDE benzersizdir):**
Her modül kendi migration'larını bağımsız numaralandırır - platform genelinde
koordine edilmiş tek bir sayaç yok. Sonuç: Branches, Core, Security, Students
ve Parents'ın İLK migration'larının hepsi `"2026_07_28_000001"` etiketini
taşıyor (tesadüfen aynı gün yazıldıkları için). `MigrationRunner`'ın eski
implementasyonu iç kayıt dizisini SADECE `version()`'a göre anahtarlıyordu -
`register()` çağrıları PHP dizi anahtarı olarak çakışınca, aynı tarihli bir
migration'ı kaydeden HER modül bir öncekini sessizce diziden düşürüyordu. Hangi
eklentinin `boot()` sırasında en son kaydolduğu (bu da eklenti aktivasyon
sırasına bağlı, deklare edilmiş bir bağımlılık grafiğine göre değil - bkz.
"İkinci kural") o 5 migration'dan yalnızca BİRİNİN gerçekten çalışıp
çalışmadığını belirliyordu; diğer dördünün `up()`'ı hiç çağrılmıyordu, hiçbir
hata da fırlatılmıyordu. Canlıda gerçekten yaşandı: `scp_branches` tablosu bu
yüzden kalıcı olarak hiç oluşmamıştı, "Üçüncü kural"daki kendiliğinden onarma
mekanizması bile bunu kurtaramıyordu çünkü migration `run()`'ın iç listesine
girmeden ÖNCE eviction oluyordu. Düzeltme: kayıt dizisi artık
`version() . '@' . get_class($migration)` ile anahtarlanıyor - aynı tarihli
farklı modül migration'ları artık birbirini silmiyor; `scp_migrations` audit
tablosundaki `version` sütunu (ve "ilk kez uygulandı" izleme mantığı)
değişmeden `version()`'ı kullanmaya devam ediyor, yani aynı etiketi paylaşan
migration'lar audit kaydında tek bir satırda toplanıyor (kabul edilen bir
sınırlama - audit log zaten yetkili kaynak değil, bkz. "Üçüncü kural"). Sınıf
adı yalnızca çakışan `version()`'lar için bir sıralama belirleyicisidir;
`ksort()` önce `version()`'a göre sıralar, bu yüzden Students'ın
`scp_branches`'a gerçek bir FK bağımlılığı olan migration'ı (`CreateStudentsTable`)
Branches'ın migration'ından SONRA çalışacağı garantisi yalnızca alfabetik sınıf
adı sıralamasına dayanır (`Seviye\Branches...` < `Seviye\Students...`) -
kesin bir bağımlılık grafiği değil. Bu yüzden `ForeignKeyInstaller::ensure()`
referans tablo henüz yoksa exception fırlatmak yerine sessizce atlar (fail
soft) - yanlış sırada çalışırsa FK eklenmez ama migration akışı durmaz.

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

### 7. Kimlik doğrulama (Seviye Security)

`Seviye Security`, Core'a bağımlı olan ilk gerçek modüldür ve giriş ekranının
backend'ini sağlar:

- `Auth\TcNumber`: TC Kimlik No format + checksum doğrulaması (saf PHP,
  WordPress'ten bağımsız, herkese açık algoritma).
- `Identity\IdentityGatewayInterface` / `WpdbIdentityGateway`: TC Kimlik
  No → WP kullanıcı eşlemesi, ayrı ve indeksli bir tabloda (`scp_user_identities`)
  tutulur — `wp_usermeta` üzerinde `meta_value` ile arama yapmak indekslenmediği
  için büyük ölçekte yavaştır.
- `Auth\AuthService`: giriş denemesini Core'un `RateLimiter`'ı ile korur.
  **Kritik güvenlik kararı**: "TC Kimlik No kayıtlı değil", "TC Kimlik No
  formatı geçersiz" ve "şifre yanlış" durumlarının hepsi aynı
  `AuthFailureReason::INVALID_CREDENTIALS` sonucunu döner ve aynı rate-limit
  darbesini alır — aksi halde bir saldırgan bu iki durumu ayırt ederek geçerli
  TC Kimlik No'ları numaralandırabilirdi (enumeration attack).
- `Token\PasswordTokenService`: "Şifremi Unuttum" ve "İlk Şifre Oluştur" için
  tek kullanımlık token üretir. Token, WordPress çekirdeğinin kendi şifre
  sıfırlama anahtarlarını sakladığı yöntemle aynı şekilde **yalnızca SHA-256
  hash'i olarak** saklanır (`scp_password_tokens`); ham token yalnızca bir kez,
  kullanıcıya gönderilen bağlantıda var olur. Süresi dolmuş bir token bile
  `redeem()` çağrıldığında **tüketilir** (silinir), böylece tekrar oynatma
  (replay) mümkün olmaz.
- `Http\AuthRestController`: `seviye/v1/auth/login`, `/forgot-password`,
  `/first-password`, `/set-password` uç noktaları. Bilinçli olarak nonce
  zorunlu tutulmaz çünkü bunlar oturum açılmadan önce çağrılan uç
  noktalardır (henüz bir auth cookie/nonce bağlamı yoktur); asıl koruma
  `RateLimiter`'dır. `/forgot-password` ve `/first-password` aynı
  `requestPasswordToken()` mantığını farklı `PasswordTokenPurpose` ile
  çağırır (DRY); ayrı REST kaynakları olarak tutulmaları API'yi
  öz-açıklayıcı kılar.
- `Routing\RoleRouter`: bir kullanıcının WordPress rollerinden, ait olduğu
  bölgeyi (`/admin`, `/sube`, `/`) ve bir isteğin o bölgeye ait olup
  olmadığını hesaplayan saf, test edilebilir politika. Login yanıtındaki
  `redirect_url` alanı buradan gelir; Tema da aynı sınıfı kullanarak
  bölge-dışı erişimi engeller (bkz. aşağıda).
- Şifre sıfırlama linkinin gerçekten e-posta/SMS ile **gönderilmesi** bu
  modülün kapsamı dışındadır — `PasswordTokenService::issue()` sonrası
  `EventBus` üzerinden `security.password_reset_requested` olayı yayınlanır;
  bunu dinleyip iletecek olan **Seviye Notifications**'dır (henüz kurulmadı).
  Bu, modüller arası sınırın kasıtlı olarak nerede çizildiğinin bir örneğidir.

`Seviye Security`, Core'a `composer.json`'da bir `path` repository ile
bağımlıdır (`plugin/seviye-security/composer.json` → `../seviye-core`); bu,
monorepo içinde her modülün Core'un aynı anda geliştirilen sürümüne karşı
çalışmasını sağlar ve gelecekteki tüm modüller aynı deseni izleyecektir.

### 8. Tema (Seviye Storefront)

Tema, `plugin/*` modüllerinin aksine PSR-4/Composer değil, **WordPress'in
kendi tema konvansiyonuyla** (önekli global fonksiyonlar, `functions.php` +
`inc/*.php`) yazılır — bu bilinçli bir tutarsızlık değil, doğru aracı doğru
yerde kullanmaktır: tema, sunum + bağlama (glue) katmanıdır, iş mantığı
barındırmaz; gerçek karar mantığı (kimlik doğrulama, rol→bölge eşlemesi)
zaten test edilebilir PHP olarak `Seviye Security`'de yaşıyor, tema onu
yalnızca çağırır.

- **Giriş kilidi** (`inc/access-gate.php`, `template_redirect` önceliği 5):
  oturum açılmamışsa, normal WordPress şablon hiyerarşisi tamamen atlanır ve
  `templates/login.php` bağımsız bir HTML dokümanı olarak render edilip
  `exit` edilir. Bu, "giriş yapılmadan ürün görüntülenmeyecek" kuralını tek
  bir merkezi noktadan, her front-end isteği için garanti eder.
- **Bölge yönlendirmesi** (`inc/zones.php`): `/admin` ve `/sube`,
  `add_rewrite_rule()` ile sanal WordPress rotaları olarak tanımlanır (henüz
  gerçek Şube/Öğrenci verisi yokken bile çalışır — bir yöneticinin elle
  WP sayfası oluşturmasına bağlı değildir). `access-gate.php`, giriş yapmış
  kullanıcının rolü isteği bölgeyle uyuşmuyorsa `RoleRouter::landingPathFor()`
  ile kendi bölgesine yönlendirir (`template_redirect` önceliği 5); bölge
  içeriği ancak bu kontrolden geçtikten sonra, önceliği 10 olan
  `zones.php`'nin şablonuyla render edilir. Önceliklerin bu sırası kasıtlıdır
  — tersi olsaydı bir Veli, `/admin` içeriğini yönlendirilmeden önce bir an
  için görebilirdi.
- **`/sube` ve `/admin` içeriği**: `templates/zone.php`, Students kurulduktan
  sonra artık gerçek bir öğrenci yönetim panelidir (bkz. aşağıda) —
  `scp_manage_students` yetkisi olmayan şube rolleri (Muhasebe, Depo, Satış
  Danışmanı, Rehberlik) için hâlâ dürüst, asgari bir "bu panelin içeriği
  ilgili modüller geliştirildikçe burada yer alacak" mesajı gösterilir; asla
  kırık/sahte bir form değildir.
- **Giriş ekranı JS'i** (`assets/js/auth.js`): build adımı olmayan, saf
  `fetch()` tabanlı bir dosya; `Seviye Security`'nin `seviye/v1/auth/*`
  uçlarını çağırır. Arayüz metinleri `wp_localize_script()` ile PHP'den
  `__()` üzerinden geçirilir (JS içinde hiçbir hard-coded Türkçe/İngilizce
  metin yoktur).

### 9. Şube yönetimi (Seviye Branches)

İlk gerçek "domain entity" modülü — spesifikasyondaki `Repository Pattern`
gereksinimini ilk kez somut olarak uygular:

- `Domain\Branch`: değişmez (immutable) entity; `Domain\Iban` (ISO 13616
  mod-97 checksum — TC Kimlik No'daki gibi genel/herkese açık bir algoritma,
  ülkeye özel değil), `Domain\CommissionRate` (0-100 aralığı doğrulamalı) ve
  `Domain\Slug` (WordPress'ten bağımsız, Türkçe karakterleri çeviren saf PHP
  slugifier) kendi kendini doğrulayan değer nesneleridir (TC Kimlik No'nun
  kurduğu desenin devamı).
- `Repository\BranchRepositoryInterface` / `WpdbBranchRepository`: CRUD,
  `ConnectionInterface::prepare()` ile parametreli sorgular üzerinden.
  Otomatik artan `id`'yi okumak için ayrı bir `lastInsertId()` portu
  eklemek yerine, `slug` alanının UNIQUE kısıtlamasından yararlanılarak
  ekleme sonrası `findBySlug()` ile geri okunur — Core'un port'unu
  gereksiz yere genişletmemek için bilinçli bir tercih (YAGNI).
- `Contracts\BranchMembershipInterface` / `WpdbBranchMembershipRepository`:
  "Yetkililer" — hangi WP kullanıcısının (şube personeli) hangi şubeye
  atandığı. Bu, yalnızca Branches'ın kendi ihtiyacı değildir: **Seviye
  Students bu arayüzü tam olarak bunun için tüketen ilk modüldür** ("bu
  Şube Müdürü hangi şubenin öğrencilerini görebilir" sorusu). `Contracts\BranchLookupInterface`
  + `Contracts\BranchSummary`, benzer şekilde diğer modüllerin şube adını
  kendi Domain katmanlarını (`Iban`, `CommissionRate`) bilmeden gösterebilmesi
  için yayınlanan hafif bir okuma sözleşmesidir (`WpdbBranchLookup` —
  `WpdbBranchRepository`'nin tam `find()` metoduyla dönüş tipi çakışmaması
  için ayrı, minimal bir adapter).
- `scp_branch_users.branch_id → scp_branches.id`: gerçek bir InnoDB FK
  kısıtlaması. `dbDelta()` `FOREIGN KEY` cümlelerini güvenilir şekilde
  ayrıştırmadığı için (bilinen bir WordPress kısıtı), kısıtlama `dbDelta()`
  sonrası ayrı, idempotent bir `information_schema` kontrolüyle korunan
  `ALTER TABLE` adımında eklenir — bkz.
  `Seviye\Core\Database\ForeignKeyInstaller::ensure()` (Core'a taşınan
  paylaşılan yardımcı, bkz. bölüm 10).
- RBAC: `scp_manage_branches` (Genel Merkez, Bölge Müdürü — tüm şubeleri
  görür/yönetir) ve `scp_view_own_branch` (şube-kapsamlı roller — yalnızca
  kendi şubesini görür), `RbacManager::grantCapability()` ile.
- REST: `seviye/v1/branches` (liste/oluştur, yalnızca `scp_manage_branches`),
  `seviye/v1/branches/{id}` (görüntüle/güncelle; görüntüleme, ya
  `scp_manage_branches` ya da kendi şubesi için `scp_view_own_branch`
  gerektirir), `seviye/v1/branches/me` (personelin kendi şubesi).

### 10. Öğrenci yönetimi (Seviye Students) — ilk gerçek modüller-arası tüketici

Students, Core'un yanı sıra **başka bir modülün Contracts'ına da bağımlı
olan ilk modül**: `seviye/students` composer paketi `seviye/core` ve
`seviye/branches`'a `path` bağımlılığıdır.

- `Domain\EducationYear`: "YYYY-YYYY" formatını ve ardışık yıl kuralını
  doğrulayan, TC Kimlik No/IBAN'ın kurduğu desenin devamı olan saf bir değer
  nesnesi.
- `scp_students.branch_id → scp_branches.id`: gerçek InnoDB FK, ama
  **`scp_branch_users`'ın aksine `ON DELETE CASCADE` kullanmaz** —
  varsayılan `RESTRICT` uygulanır. Bir şube silindiğinde tüm öğrencilerinin
  sessizce silinmesi, bu platformun kaçındığı türden geri döndürülemez bir
  veri kaybıdır; `scp_student_parents.student_id` ise (bir öğrencinin kendi
  veli-bağlantılarının temizlenmesi beklenen, güvenli bir işlem olduğu için)
  `ON DELETE CASCADE` kullanır.
- **Modül-boot sırası sorunu ve çözümü**: `StudentsModule::boot()`,
  Branches'ın `Contracts\BranchMembershipInterface`'ine ihtiyaç duyar, ama
  hangi modülün `plugins_loaded` önceliği-10 kaydının önce çalışacağı
  (dolayısıyla `ModuleRegistry::bootAll()`'un hangi sırada `boot()`
  çağıracağı) WordPress'in eklenti yükleme sırasına bağlıdır — garanti
  edilmez. Bunun için `Core\Http\RestApiRegistrar::register()` artık hazır
  bir controller nesnesi değil, bir **factory closure** kabul eder; closure
  yalnızca `rest_api_init` anında (tüm modüllerin `boot()`'u kesinlikle
  bittikten çok sonra) çalışır. Bu, sıralamaya bağımlı olmayan, genel bir
  düzeltmedir — Security ve Branches'ın kendi REST controller kayıtları da
  aynı deseni kullanacak şekilde güncellendi (onlar için kritik değildi,
  çünkü yalnızca Core'un — her zaman önce hazır olan — bağlarına
  ihtiyaçları vardı, ama tutarlılık için aynı desen uygulandı).
- `Core\Database\ForeignKeyInstaller`: `CreateBranchUsersTable`'da tekrar
  eden idempotent-FK-ekleme mantığı, ikinci kullanım (`CreateStudentsTable`,
  `CreateStudentParentsTable`) ile birlikte Core'a çıkarıldı — üç modülde de
  tekrar edeceği baştan belliydi.
- `Core\Database\ConnectionInterface::lastInsertId()`: Branches'ta
  otomatik-artan `id`'yi `slug`'ın UNIQUE kısıtlamasından yararlanarak geri
  okumuştuk (bkz. bölüm 9); Students'ta böyle doğal bir benzersiz alan
  olmadığından (isim+şube+yıl+sınıf kombinasyonu DB'de kısıtlanmamıştır ve
  aynı isimde öğrenciler gerçekte olur), bu kez port'u gerçekten genişletmek
  gerekti — YAGNI'nin "gerektiğinde ekle" tarafının uygulanışı.
- RBAC: `scp_manage_students` — Genel Merkez, Bölge Müdürü VE Şube Müdürü
  aynı capability'yi taşır (ayrı bir "yalnızca kendi şubesi" capability'si
  yoktur); kapsam farkı **çalışma zamanında**, `StudentsRestController`'ın
  Branches'ın `BranchMembershipInterface::branchIdForUser()`'ını sorup
  sonucun null olup olmadığına göre karar verilir (null → HQ, tüm şubeler;
  değilse → yalnızca o şube). `scp_view_own_children` — Veli.
- REST: `seviye/v1/students` (liste/oluştur, şube-kapsamlı), `/students/{id}`
  (görüntüle/güncelle, erişim `canAccessStudent()` ile denetlenir),
  `/students/mine` (Veli'nin kendi çocukları), `/students/{id}/parents`
  (veli bağla/kaldır).

### 11. Veli profili (Seviye Parents)

"VELİ PANELİ" listesindeki "Kendi öğrencileri" zaten Students'ın
`/students/mine`'ı üzerinden çözülüyor; Parents bu yüzden **yalnızca Core'a
bağımlı, kasıtlı olarak küçük ve bağımsız bir modül** — Branches/Students
gibi bir Contracts ilişkisine ihtiyacı yok, ki bu da her modülün otomatik
olarak bir cross-module bağımlılık biriktirmeyeceğinin bir kanıtı.

- `scp_parent_profiles`: `wp_users`'ın kapsamadığı alanlar (telefon,
  bildirim tercihi, KVKK onay zaman damgası). Diğer platform tabloları
  gibi `wp_users`'a FK içermez.
- **KVKK onayının değişmezliği**: `WpdbParentProfileRepository::upsert()`,
  bir kez verilen `kvkk_consent_at` zaman damgasını asla temizlemez veya
  üzerine yazmaz — sonraki bir `PUT /parents/me` çağrısı onay bayrağını
  `false` gönderse bile önceki onay kaydı korunur. Bu, saf, WordPress'ten
  bağımsız bir kural olarak `resolveConsentTimestamp()`'te izole edilmiş ve
  ayrıca test edilmiştir (`WpdbParentProfileRepositoryTest`) — KVKK
  denetlenebilirliği için kasıtlı bir tasarım kararı, tesadüfi bir
  davranış değil.
- RBAC: `scp_manage_own_profile` — yalnızca Veli. Uç nokta her zaman
  *geçerli* kullanıcının kendi profilidir (`get_current_user_id()`); başka
  bir velinin profilini görüntüleme diye bir şey olmadığından, capability
  kontrolünün ötesinde ayrıca bir sahiplik kontrolüne gerek yoktur.
- REST: `GET/PUT seviye/v1/parents/me`.

### 12. Panel entegrasyonu (Students + Parents + Branches + Pricing + Finance REST'ine bağlanma)

`templates/zone.php` (öğrenci + şube + fiyat kuralları + cari bakiye
yönetimi, `/admin` + `/sube`) ve `templates/parent-dashboard.php` (Veli ana
sayfası, `/`) sırasıyla `assets/js/students-panel.js`,
`assets/js/branches-panel.js`, `assets/js/pricing-panel.js`,
`assets/js/hakedis-panel.js` ve `assets/js/parent-dashboard.js` ile ilgili
REST uçlarını çağırır.

- **Nonce farkı**: Security'nin `seviye/v1/auth/*` uçları oturum açılmadan
  ÖNCE çağrıldığı için nonce gerektirmiyordu (bkz. bölüm 6, Security). Bu
  panel script'leri ise **zaten oturum açmış** bir kullanıcıdan çağrılır —
  tarayıcıda bir auth cookie zaten vardır. WordPress'in kendi
  `rest_cookie_check_errors()`'ı (çekirdek davranış, her REST isteğinde
  otomatik devrede) cookie ile kimliği doğrulanmış her istekte geçerli bir
  `X-WP-Nonce` header'ı ister; yoksa 403 döner. Bu yüzden
  `inc/assets.php`'deki `scp_enqueue_panel_assets()`, bu script'lere
  `wp_create_nonce('wp_rest')` ile üretilen bir nonce'u da localize eder —
  Core/Security/Branches/Students/Parents controller'larının hiçbirinde
  ayrıca nonce doğrulaması **yazılmaz**; bu tamamen WordPress çekirdeğinin
  işidir. `branches-panel.js` da aynı `scpPanel.nonce`/`apiFetch()` desenini
  tekrar kullanır — bu panel için ayrıca hiçbir yeni nonce kodu yazılmadı.
- **Tek panel, iki bölge**: `/admin` ve `/sube` aynı `students-panel.js`'i ve
  aynı `zone.php` işaretlemesini paylaşır, çünkü `seviye/v1/students`
  zaten sunucu tarafında kapsamı belirliyor (Genel Merkez/Bölge Müdürü tüm
  şubeleri, Şube Müdürü yalnızca kendisini görür — bkz. bölüm 10). JS
  yalnızca `scpPanel.canManageAllBranches` bayrağına göre bir şube seçici
  gösterip göstermeyeceğine karar verir; kapsam mantığını asla
  tekrarlamaz. Şube yönetim paneli ise bilinçli olarak **yalnızca
  `/admin`'de** render edilir — `templates/zone.php`,
  `inc/zones.php`'e eklenen `scp_current_zone()` yardımcı fonksiyonuyla
  (`get_query_var('scp_zone')`'un ince bir sarmalayıcısı) hangi bölgede
  olduğunu sorar ve şube bölümünü `scp_current_zone() === 'admin' &&
  current_user_can('scp_manage_branches')` ile kapatır. `scp_manage_branches`
  zaten yalnızca Genel Merkez/Bölge Müdürü'ne verilir ve bu roller yalnızca
  `/admin`'e iner, ama açık bölge kontrolü, o rol→bölge eşlemesi ileride
  değişse bile sayfayı doğru tutar — kapasiteye değil, kapasite+bölgeye
  güvenmek kasıtlı bir tercih.
- **Dürüst yetki kontrolü**: `/sube`'a inen her rol `scp_manage_students`
  taşımaz (Muhasebe, Depo, Satış Danışmanı, Rehberlik taşımaz). `zone.php`
  paneli render etmeden önce `current_user_can('scp_manage_students')`'i
  PHP'de kontrol eder — aksi halde bu roller, gönderildiğinde 403 dönecek
  işlevsiz bir form görürdü. Şube paneli için de aynı disiplin uygulanır:
  `current_user_can('scp_manage_branches')` kontrolünden geçmeyen hiçbir
  rol, gönderimi 403 ile sonuçlanacak bir form görmez.
- **Eksik uç nokta bulundu ve eklendi**: Veli bağlama arayüzü inşa
  edilirken, bir öğrencinin bağlı velilerini listeleyen bir REST uç
  noktasının hiç yazılmadığı ortaya çıktı (yalnızca bağla/kaldır vardı).
  `StudentsRestController::listParents()` (`GET /students/{id}/parents`) bu
  yüzden bu milestone'da eklendi — "yarım kod üretme" ilkesinin somut bir
  uygulanışı.
- **`/` (Veli)**: `index.php`, kullanıcı `scp_view_own_children` veya
  `scp_manage_own_profile` taşıyorsa `templates/parent-dashboard.php`'yi
  dahil eder (WooCommerce/Seviye Commerce henüz yok); aksi halde normal WP
  Loop'a/placeholder mesajına düşer. Öğrenci listesi salt okunurdur —
  Students verinin tek sahibi olmaya devam eder, tema onu asla
  kopyalamaz/önbelleklemez.
- **Şube yönetim paneli** (`/admin`): `branches-panel.js`,
  `seviye/v1/branches` üzerinde tam CRUD yapar (liste, oluştur, düzenle) —
  `BranchesRestController`'ın var olan sözleşmesine (`writableArgs()`:
  `name`, `slug`, `iban`, `commission_rate`, `phone`, `address`, `status`)
  hiçbir değişiklik gerekmedi. `status` alanı yalnızca düzenleme
  formunda gösterilir (yeni şube her zaman `active` olarak oluşturulur,
  Branches modülünün kendi varsayılanıyla) — `students-panel.js`'in "yeni
  öğrenci" akışının aynı deseni.
- **Fiyat kuralları paneli** (`/admin` VE `/sube`): Şube yönetiminin aksine
  `pricing-panel.js` **her iki bölgede de** render edilir, çünkü
  `scp_manage_branches`'ın tersine `scp_manage_pricing` Şube Müdürü'ne de
  verilir (bkz. bölüm 13). Henüz bir ürün kataloğu olmadığından (Seviye
  Commerce/WooCommerce entegrasyonu planlı), panel bir ürün seçici değil,
  düz sayısal bir "Ürün ID" alanıyla kuralları arar — `students-panel.js`'in
  "Veli Kullanıcı ID" girişiyle aynı dürüst desen: gerçek bir arama/seçim
  arayüzü olmadığında ham ID istemek, sahte bir seçici kurmaktan daha
  doğrudur. `scpPanel.canManageAllBranches` (Branches panelinin
  `students-panel.js`'te kullandığı bayrağın aynısı) formdan "Genel" kapsam
  seçeneğini tamamen kaldırır ve BRANCH kapsamı için hedef alanını
  gizler — sunucu zaten `PricingRestController::resolveScopeForWrite()` ile
  şube-kapsamlı yazarların BRANCH hedefini kendi şubelerine sabitliyor
  (aşağıdaki madde), JS bunu tekrarlamaz, yalnızca gereksiz bir girişi
  gizler.
- **Panel inşa edilirken bulunan ve düzeltilen sunucu tarafı sorun**:
  `pricing-panel.js` yazılırken, bir Şube Müdürü'nün BRANCH kapsamlı bir
  kural oluştururken kendi şube ID'sini ezbere girmesi gerektiği ortaya
  çıktı — REST `store()` yalnızca gönderilen `target_id`'yi doğruluyordu,
  Students'ın `resolveBranchIdForWrite()`'ının aksine otomatik
  ikame etmiyordu. Bu, `PricingRestController::resolveScopeForWrite()`
  eklenerek düzeltildi: şube-kapsamlı bir yazar için BRANCH kapsamı her
  zaman `request`'te ne gönderilirse gönderilsin kendi şubesine sabitlenir
  (`canWriteScope()`'un zaten kabul edeceği tek değer). Bu, panel arayüzünü
  inşa etmenin sunucu tarafında gerçek, önceden fark edilmemiş bir kullanım
  kusuru ortaya çıkardığı bir başka örnek — bkz. yukarıdaki "Eksik uç nokta"
  maddesi, Students'taki aynı desen.
- **Cari bakiye paneli** (`/admin` VE `/sube`): `hakedis-panel.js`, fiyat
  kuralları paneliyle aynı iki-bölge desenini izler, ama iki bölge FARKLI
  yetkilerle (`scp_view_hakedis` HQ için, `scp_view_own_hakedis` yalnızca
  Şube Müdürü + Muhasebe için — bkz. bölüm 15, Finance) girildiğinden JS iki
  ayrı görünüm render eder: `scpPanel.canViewAllBranches` (yalnızca
  `scp_view_hakedis` taşıyanlarda true) tüm şubelerin bakiyesini gösteren
  bir tablo mu, yoksa yalnızca kullanıcının kendi şubesinin bakiyesini
  gösteren tek bir kart mı çizileceğine karar verir. "Tüm şubelerin
  bakiyesini listele" diye ayrı bir REST uç noktası **yoktur** — fiyat
  kuralları panelinin ham "Ürün ID" deseni gibi burada da "gereksiz
  spekülatif REST yüzeyi ekleme" ilkesi uygulanır: HQ görünümü zaten genel
  `GET /branches` uç noktasını her şube için bir kez
  `GET /finance/hakedis/balance/{id}` ile birleştirerek tabloyu istemci
  tarafında oluşturur. Bu, `HakedisRestController`'ın `/finance/hakedis/balance/me`
  (kendi şubesi) ve `/finance/hakedis/balance/{branch_id}` (yetkiye göre
  herhangi bir şube) uçlarının `BranchesRestController::me()`/`canViewBranch()`
  deseninin doğrudan bir aynası olmasıyla da tutarlıdır (bkz. bölüm 15).
  Panel ayrıca bir "Tahsilat" alt bölümü render eder: şube seçici + tahsilat
  geçmişi `canViewAllBranches` altında herkese açık, tahsilat kaydetme
  formu ise yalnızca `canRecordSettlement` (`scp_record_hakedis_settlement`
  — Genel Merkez/Muhasebe) altında görünür (bkz. bölüm 15).

### 13. Fiyatlandırma motoru (Seviye Pricing)

WooCommerce'in kendi fiyat/kupon sistemini genişletmek yerine tamamen özel
bir motor — spesifikasyonun kendisinin istediği tercih, çünkü
şube/öğrenci/genel önceliklendirmesi WC'nin fiyat modeline temiz şekilde
oturmuyor. Aynı anda hem Branches'ın hem Students'ın Contracts'ına bağımlı
olan ilk modül — `seviye/pricing` composer paketi `seviye/core`,
`seviye/branches` ve `seviye/students`'a `path` bağımlılığıdır.

- `Domain\PriceScope`: bir kuralın hedefi — self-validating, Branches'ın
  Iban/CommissionRate'inin kurduğu desenin devamı. Yalnızca üç adlandırılmış
  kurucu (`general()`, `forBranch()`, `forStudent()`) mevcuttur, bu yüzden
  `type` ile `branchId`/`studentId` arasında tutarsız bir kombinasyon asla
  oluşamaz. `Domain\Money`: negatif olmayan, 2 ondalıklı TRY tutarı —
  Finance'ın kuruş bazlı defter mantığı burada henüz gerekmediğinden
  kasıtlı olarak asgari tutulmuştur (YAGNI).
- **"Bölge" katmanı kasıtlı olarak eksik**: spesifikasyondaki öncelik
  zinciri öğrenci→şube→**bölge**→genel→WC varsayılanı sayıyor, ama
  platformda hiçbir yerde bir Region varlığı yok — Branches'ın RBAC'ı zaten
  Bölge Müdürü'nü tam-HQ kapsamında ele alıyor (`scp_manage_branches`,
  şube bölünmesi olmadan Genel Merkez ile paylaşılıyor, bkz. bölüm 9). Bu
  yüzden ayrı, çözümlenebilir bir "bölge" katmanı için bağlanacak bir şube
  grubu yok. Bu grupliği hiçbir modülün ihtiyaç duymadığı bir anda inşa
  etmek spekülatif olurdu; `Contracts\PriceResolverInterface`'in imzası
  (aşağıda) bu katmanı ileride, ihtiyaç doğduğunda, arayüzü değiştirmeden
  eklemeye izin verecek şekilde tasarlandı — bkz. arayüzün kendi docblock'u.
- `scp_price_rules.student_id` ve `.branch_id` her ikisi de nullable, gerçek
  InnoDB FK'lı (`ON DELETE CASCADE` — silinen bir öğrenciye/şubeye bağlı bir
  fiyat kuralının kalması anlamsız; bu, `scp_students.branch_id`'nin
  kasıtlı `RESTRICT`'inden farklı bir risk sınıfı: tek bir kural satırının
  kaybı, bir şubenin tüm öğrencilerinin sessizce silinmesiyle aynı
  büyüklükte bir veri kaybı değil). `product_id`'nin FK'sı yok — bir
  WooCommerce ürününe (`wp_posts.ID`) işaret eder, ve bu platform hiçbir
  zaman WordPress çekirdek tablolarına FK koymaz.
  **Aktif kural çakışması DB kısıtlamasıyla değil repository katmanında
  önlenir**: MySQL'in unique index'leri NULL sütunları farklı kabul ettiği
  için "(ürün, kapsam) başına en fazla bir aktif kural" kuralı temiz bir
  composite UNIQUE ile ifade edilemiyor; bunun yerine
  `PriceRuleRepositoryInterface::activeRuleExists()` yazma yolunda
  (REST controller'ın `store()`'u) kontrol edilir ve çakışma 409 ile
  reddedilir — Branches'ın `slugExists()` ön-kontrolüyle aynı disiplin.
- `Contracts\PriceResolverInterface::resolve(productId, ?studentId,
  ?branchId, fallbackPrice): ResolvedPrice`: motorun asıl teslimatı.
  Öncelik: öğrenci kuralı > şube kuralı > genel kural > `$fallbackPrice`.
  `$fallbackPrice` çağıran tarafından verilir (WooCommerce'in kendi ürün
  fiyatı) — Pricing'i WooCommerce'in kurulu/aktif olmasından tamamen
  ayrıştırır ve WordPress'siz birim testini mümkün kılar. `branchId`
  verilmezse ama `studentId` verilmişse, şube Students'ın
  `StudentLookupInterface`'inden türetilir — çağıranın aynı bilgiyi iki kez
  vermesini gerektirmez. `ResolvedPrice.source` (`PriceSource` enum'u)
  hangi katmanın kazandığını da döndürür — Commerce'in bir Veli'ye "neden bu
  fiyatı görüyor" diye açıklayabilmesi için ucuz ve doğrudan faydalı bir
  şeffaflık, spekülatif bir ekleme değil.
- **WooCommerce filtre entegrasyonu bilinçli olarak bu milestone'da değil**:
  `woocommerce_product_get_price` gibi filtrelere kancalanmak, hangi
  öğrenci için fiyatlandığını bilmeyi gerektirir — bu bağlam yalnızca
  sepete-ekleme anında, bir Veli'nin hangi çocuğu seçtiğine göre belli olur,
  ki bu tamamen Seviye Commerce'in (henüz kurulmamış) sorumluluğudur.
  Pricing bunu şimdiden varsaymak yerine yalnızca `PriceResolverInterface`'i
  yayınlar; Commerce kurulduğunda bu Contract'ı doğrudan, REST üzerinden
  değil in-process olarak (Students'ın Branches'ı tükettiği gibi) çağıracak.
  Bu, önceki bir mimari notunun ("Seviye Pricing modülü bunu WooCommerce'in
  filtrelerine entegre edecek") düzeltilmiş hâlidir — asıl kısıtın ne
  olduğu bu modül inşa edilirken netleşti.
- **REST'te `resolve` uç noktası yok**: `/pricing/resolve` gibi bir REST
  uç noktasının bugün gerçek bir çağıranı yok (Commerce, kurulduğunda,
  `PriceResolverInterface`'i in-process çağıracak, REST'e ihtiyaç duymadan).
  Hiçbir çağıranı olmayan REST yüzeyi eklemek spekülatif olurdu; yalnızca
  kural CRUD'u (`GET/POST /pricing/rules`, `PUT/DELETE /pricing/rules/{id}`)
  REST'e açıktır — bunun gerçek bir çağıranı var: bu modülün yönetim ekranı
  (tema paneli, ileride).
- RBAC: `scp_manage_pricing` — Genel Merkez, Bölge Müdürü VE Şube Müdürü
  aynı capability'yi taşır (Students'ın `scp_manage_students`'ıyla aynı
  desen); kapsam farkı çalışma zamanında, `PricingRestController`'ın
  Branches'ın `BranchMembershipInterface::branchIdForUser()`'ını sorup
  sonuca göre karar vermesiyle uygulanır: HQ (null) her kapsamı
  (GENERAL dahil) yazabilir; şube-kapsamlı roller yalnızca kendi
  şubelerine ait BRANCH kurallarını ve kendi şubelerinin öğrencilerine ait
  STUDENT kurallarını yazabilir, GENERAL asla yazamaz (platform geneli fiyat
  yalnızca HQ'nun kararıdır). Listeleme (`index()`) ise GENERAL kuralları
  şeffaflık için şube-kapsamlı rollere de gösterir — yalnızca *yazma*
  GENERAL için engellidir.

### 14. WooCommerce entegrasyonu — sepet fiyatlandırma + tema + sipariş kalıcılığı (Seviye Commerce, 1. bölüm)

Seviye Commerce, spesifikasyondaki "sipariş akışı + split payment +
hakediş tetikleme" sorumluluğunun tamamını tek bir milestone'da değil,
Branches/Students/Pricing'te olduğu gibi katman katman inşa ediyor. Bu ilk
bölüm yalnızca **sepet fiyatlandırmasını** kurar: bir Veli sepete bir ürün
eklerken hangi çocuğu için aldığını seçer, fiyat bu öğrenciye göre
`Seviye Pricing`'in motoruyla çözülür ve bu seçim siparişe kadar hayatta
kalır. Sipariş kalıcılığı, split payment ve hakediş tetikleme sonraki
bölümlerdir (bkz. `docs/ROADMAP.md`).

- **İlk kez hem Students'ın hem Pricing'in Contracts'ına bağımlı, kendi
  Domain/Repository/REST katmanı olmayan bir modül**: `seviye/commerce` bu
  milestone'da yalnızca `Students\Contracts\StudentGuardianCheckInterface`,
  `Students\Contracts\StudentLookupInterface` ve
  `Pricing\Contracts\PriceResolverInterface`'i WooCommerce'in hook'larına
  bağlıyor — kendi veritabanı tablosu, kendi REST'i, kendi RBAC
  capability'si yok.
- **Yeni Contract: `Students\Contracts\StudentGuardianCheckInterface::isGuardianOf()`**:
  Commerce'in "bu sepet öğesi gerçekten bu Veli'nin çocuğu için mi"
  sorusuna cevap vermesi gerekiyordu; Students'ın bunun için var olan
  `Repository\StudentParentRepositoryInterface`'i internal bir sınıf
  olduğundan doğrudan tüketilemezdi (kural: yalnızca `Contracts`
  namespace'i modüller-arası tüketilebilir). `WpdbBranchLookup`/
  `WpdbStudentLookup`'ın izlediği desenle ayrı, minimal bir
  `WpdbStudentGuardianCheck` adaptörü eklendi.
- **Ports & Adapters, WooCommerce'e uygulanmış**: `Support\CartPricingService`
  saf PHP'dir (WordPress'e/WooCommerce'e bağımlı değildir, tam birim test
  kapsamı vardır) — asıl kararları (misafirlik doğrulaması, fiyat çözümü)
  verir. `Http\WooCommerceCartHooks` ince bir adaptördür: yalnızca WC
  hook'larını kaydeder ve `CartPricingService`'e/`StudentLookupInterface`'e
  devreder; kendisi test edilmez — `WpdbConnection`, tema'nın `inc/*.php`
  dosyaları gibi, bu kod tabanındaki her WordPress/WooCommerce'e dokunan
  adaptörle aynı ilke (bkz. "Test stratejisi").
- **Sepet öğesi → öğrenci eşlemesi yeni bir `scp_*` tablosu gerektirmedi**:
  WooCommerce zaten sepet/sipariş verisinin sahibi; `student_id` sepette
  WC'nin kendi `cart_item_data` dizisinde (`scp_student_id` anahtarı),
  siparişte ise sipariş kalemi meta'sında (`_scp_student_id`) taşınır.
  Yeni bir tablo eklemek, WooCommerce'in zaten sağladığı bir mekanizmayı
  gereksiz yere tekrar etmek olurdu.
- **Beş WC hook'u, her biri tek bir sorumluluk**:
  `woocommerce_add_to_cart_validation` (misafirlik doğrulaması — geçersiz
  bir öğrenci seçimi sepete hiç girmez), `woocommerce_add_cart_item_data`
  (seçilen `student_id`'yi sepet öğesine iliştirir),
  `woocommerce_before_calculate_totals` (her sepet öğesinin fiyatını
  `PriceResolverInterface` üzerinden yeniden hesaplar — WooCommerce'in
  sepet-bazlı dinamik fiyatlandırma için önerdiği standart hook, ürün
  bazlı `woocommerce_product_get_price` filtresi değil, çünkü o filtre
  hangi sepet öğesinden çağrıldığı bağlamını taşımaz),
  `woocommerce_get_item_data` (sepet/checkout görünümünde "Öğrenci: ..."
  satırı gösterir), `woocommerce_checkout_create_order_line_item`
  (`student_id`'yi kalıcı sipariş kalemi meta'sına kopyalar).
- **Misafirlik doğrulaması yalnızca sepete-ekleme anında yapılır, her
  toplam yeniden hesaplamasında tekrar edilmez**: `CartPricingService::resolvePriceForCartItem()`
  sepette zaten saklanan `student_id`'ye güvenir. Bir veli-öğrenci bağının
  bir Veli sepette ürün varken kaldırılması gerçekçi olmayan, düşük riskli
  bir kenar durumdur (bu bir güvenlik sınırı değildir — temanın rol/bölge
  kapısı zaten Veli olmayan hiçbir rolü mağazaya sokmaz); en kötü ihtimalle
  sepette bayat bir fiyat kalır, başka bir velinin çocuğunun verisi asla
  sızmaz. Bu bilinçli bir performans/basitlik tercihidir, gözden kaçmış bir
  kontrol değildir.
- **Yeni bir RBAC capability'sine gerek yok**: "sepete kim erişebilir"
  sorusu zaten `Seviye\Security\Routing\RoleRouter` + temanın
  `inc/access-gate.php`'i tarafından çözülmüş durumda —
  `RoleRouter::zoneForPath()` `/admin` ve `/sube` dışındaki her yolu
  "parent" bölgesi sayar, yani WooCommerce ürün/sepet/checkout sayfalarına
  yalnızca Veli-bölgesine ait roller iniyor (HQ/Şube rolleri kendi
  bölgelerine geri yönlendiriliyor). Commerce'in kendi misafirlik kontrolü
  bunun *üstüne* eklenen, farklı bir soruya (bu Veli'nin BU çocuğu mu)
  cevap veren ayrı bir iş kuralı — rol/bölge kapısının yerini almaz, onu
  tekrarlamaz da.
- **Aktivasyon sırası**: Commerce'in aktivasyonu Core'u, WooCommerce'in
  aktif olduğunu (`Environment::isWooCommerceActive()` — Core'un kendi
  aktivasyon kontrolüyle aynı paylaşılan yardımcı), Students'ı ve
  Pricing'i doğrular. `CommerceModule::boot()` da ayrıca
  `Environment::isWooCommerceActive()` ile korunur (yalnızca aktivasyon
  anında değil, her `plugins_loaded`'da) — WooCommerce etkinleştirildikten
  sonra devre dışı bırakılırsa WC hook'larının hiçbir işlevi kalmayan bir
  şekilde kayıtlı kalması yerine sessizce atlanır.
- **Tema tarafı: özel bir WC şablonu gerekmedi**: `inc/setup.php`
  `add_theme_support('woocommerce')`'i zaten kuruyordu (Milestone 3'ten
  beri); WooCommerce kendi paketlenmiş `archive-product.php`/
  `single-product.php` şablonlarını, temanın `header.php`/`footer.php`'i
  (`wp_head()`/`wp_body_open()`/`wp_footer()` zaten mevcut) üzerinden
  otomatik render eder. Bu yüzden ürün sayfasına öğrenci seçici eklemek
  yeni bir şablon dosyası değil, tek bir hook
  (`woocommerce_before_add_to_cart_button`) gerektirdi —
  `inc/woocommerce.php`.
- **Vitrin (arşiv/mağaza) sayfasındaki "hızlı sepete ekle" bağlantısı
  değiştirildi**: WooCommerce'in vitrin şablonundaki anlık AJAX
  "sepete ekle" düğmesinin, öğrenci seçimini taşıyacak bir `<form>`'u
  yoktur (yalnızca `product_id`/miktar gönderir) — bu platformda HER ürün
  bir öğrenci seçimi gerektirdiğinden, bu düğme sessizce yanlış/eksik bir
  sepet girdisi oluşturmak yerine (sunucu tarafı doğrulama zaten reddeder,
  ama kullanıcıya belirsiz bir hata gösterir) `woocommerce_loop_add_to_cart_link`
  filtresiyle ürün sayfasına giden düz bir bağlantıya ("Öğrenci Seç")
  dönüştürüldü — seçicinin gerçekten yaşadığı tek yer.
- **Öğrenci seçici, `students-panel.js`'in kurduğu REST/nonce desenini
  tekrar eder**: `assets/js/product-student-picker.js`,
  `seviye/v1/students/mine`'ı çağırıp `<select name="scp_student_id">`'i
  doldurur — bu `<select>` WooCommerce'in kendi `form.cart`'ının İÇİNDE
  render edildiğinden (`woocommerce_before_add_to_cart_button`), seçilen
  değer WC'nin standart sepete-ekleme POST'una otomatik dahil olur; ayrıca
  bir fetch/submit kodu yazmaya gerek yoktur. Boş seçenekler için
  `noChildren` metni (Veli dashboard'ın zaten kullandığı anahtar) tekrar
  kullanılır.
- **Veli ana sayfasına gerçek bir mağaza girişi**: `parent-dashboard.php`'ye
  eklenen "Mağaza" bölümü `wc_get_page_permalink('shop')`'a bağlanır —
  bu olmadan bir Veli'nin mağazayı keşfedecek hiçbir navigasyonu
  olmayacaktı. "Veli ana sayfasına mağaza içeriğini kazandırır" hedefinin
  bu bölümdeki karşılığı budur; mağaza içeriğinin `/`'e doğrudan
  gömülmesi değil (WooCommerce zaten kendi Mağaza sayfasını/ürün
  arşivini yönetiyor, bunu tekrar etmek gereksiz olurdu).
- **Sipariş kalıcılığı: `scp_order_line_items`, Commerce'in ilk gerçek
  tablosu**: WooCommerce zaten sipariş verisinin sahibi olmaya devam
  ediyor — bu tablo onun yerini almaz, yalnızca hakediş hesaplaması için
  gereken bilgiyi **sipariş anında anlık görüntü (snapshot) olarak**
  saklar: `student_id`, `branch_id`, o anki `commission_rate` ve gerçekte
  tahsil edilen `price`. Bu bilinçli bir tekrar (denormalizasyon) —
  Branches'ın komisyon oranı ileride değişirse, geçmiş bir siparişin
  hakediş hesabı o siparişin **o anki** oranını kullanmalı, Branches'ın
  şu anki oranını değil. `Contracts\BranchSummary`'ye bu yüzden
  `commissionRate` eklendi (Domain'in kendi `CommissionRate` değer
  nesnesini sızdırmadan, ham bir `float` olarak — `BranchSummary`'nin
  "minimal, stabil okuma modeli" ilkesinin bilinçli bir istisnası, çünkü
  bu artık başka bir modülün meşru olarak ihtiyaç duyduğu bir veri).
- **`price`, `PriceResolverInterface` yeniden çağrılarak değil, sipariş
  kaleminin gerçek toplamından (`$item->get_total()`) okunur**: sepete
  eklenirken zaten doğru fiyat uygulanmıştı (bkz. yukarıdaki
  `woocommerce_before_calculate_totals` maddesi); ödeme anına kadar bir
  fiyat kuralı değişmiş olabilir, bu yüzden motoru yeniden çağırmak
  gerçekte tahsil edilenle tutarsız bir "denetim" değeri üretebilirdi.
  Gerçekte ne tahsil edildiğini WC'nin kendisinden okumak tek doğru
  kaynak.
- **`vatAmount`, aynı ilkeyle `$item->get_total_tax()`'tan okunur**:
  WooCommerce'in kendi vergi motoru zaten hesaplamışken Seviye KDV'yi asla
  yeniden hesaplamaz. Yalnızca tutar saklanır, oran değil (oran, `vatAmount
  / price`'tan türetilebilir bir değer olurdu, anlık görüntülenen bağımsız
  bir gerçek değil). Bu alan `hakedisPayload()`'a `vat_amount` olarak
  eklenip Seviye Finance'ın ledger'ına kadar taşınır (bkz. bölüm 15) —
  henüz hiçbir REST yanıtında/panelde gösterilmiyor, Seviye Reports için
  yakalanan ham muhasebe verisi.
- **`status` kasıtlı olarak düz bir string, kapalı bir PHP enum değil**:
  `wc_get_order_statuses()` açık uçlu bir sözlüktür — üçüncü taraf ödeme/
  abonelik eklentileri kendi durumlarını ekleyebilir. Kapalı bir enum,
  böyle bir eklenti kurulduğu an bayatlardı.
  `woocommerce_order_status_changed` hook'u satırların `status`'unu
  senkron tutar.
  `scp_order_line_items.student_id`/`.branch_id` **kasıtlı olarak
  `RESTRICT`** kullanır (varsayılan, `scp_price_rules`'ın `CASCADE`'inin
  aksine): bu tablo bir yönetim kuralı değil, sipariş/finansal geçmiş —
  bir öğrenci/şube daha sonra silindiğinde hakediş kayıtlarının sessizce
  kaybolması, `scp_students.branch_id`'nin `RESTRICT`'iyle aynı
  gerekçeyle kabul edilemez bir veri kaybı olurdu.
- **`Http\OrderPersistenceHooks`, `WooCommerceCartHooks`'un yanına
  eklenen ikinci, ayrı bir ince adaptör**: sepet/fiyat yaşam döngüsü ile
  sipariş kalıcılığı farklı sorumluluklar — tek bir büyüyen sınıf yerine
  iki odaklı sınıf. `woocommerce_checkout_order_processed`'da (gerçek
  `order_id`/`order_item_id`'lerin kesinleştiği an) her `_scp_student_id`
  taşıyan kalem için bir satır yazar; kendisi `WooCommerceCartHooks` gibi
  test edilmez (yalnızca WC'den okuma + Contract sorguları + repository
  çağrıları), ama artık gerçek bir karar mantığını da çağırıyor — bkz.
  aşağıdaki split payment maddesi.
- **Split payment: `Support\SplitPaymentCalculator`, saf ve test edilen
  aritmetik**: `Domain\CommissionRate`'in kendi docblock'unun zaten
  belirlediği kural — "bir şubenin bir siparişteki payını hesaplamak için
  kullanılır" — burada uygulanır: `branchShare = price × commissionRate /
  100`, `hqShare = price - branchShare`. Bu tek gerçek karar/hesap
  mantığı olduğundan `CartPricingService`'in izlediği aynı ayrıştırma
  ilkesiyle `OrderPersistenceHooks`'tan çıkarılıp ayrı, birim test edilen
  bir sınıfa alındı.
- **"Hakediş tetikleme": `commerce.order_line_item_completed` event'i,
  gerçek hakediş kaydı değil**: `OrderPersistenceHooks::syncOrderStatus()`,
  bir sipariş `completed` durumuna geçtiğinde (`processing` değil — hâlâ
  iade/iptal edilebilir bir siparişte hakediş tetiklemek yanlış olurdu),
  o siparişin her kalemi için `EventBusInterface::dispatch()` ile bir
  `Event('commerce.order_line_item_completed', [...])` yayınlar —
  Security'nin `security.password_reset_requested`'ı yayınlamasıyla
  birebir aynı desen. Payload, Finance'ın (henüz kurulmamış) ihtiyaç
  duyacağı her şeyi taşır: `order_id`, `order_item_id`, `student_id`,
  `branch_id`, `commission_rate`, `price`, `branch_share`, `hq_share`.
  **Commerce'in sorumluluğu burada biter** — asıl hakediş/cari kaydını
  oluşturmak, spesifikasyonun plugin tablosunda zaten Seviye Finance'a ait
  ("Cari, hakediş, komisyon, KDV, iade, tahsilat"), Commerce'e değil; bu
  event'i şimdi kimse dinlemiyor olması (Finance henüz yok) kasıtlı ve
  zararsız — `EventBus::dispatch()` dinleyicisiz bir event'te sessizce no-op'tur.
- **Ters çevirme (reversal) event'i, `completed` sonrası bir iade/iptali
  hesaba katar**: `completed`'e ulaşmış bir sipariş daha sonra `refunded`
  veya `cancelled`'a geçerse, `commerce.order_line_item_reversed` aynı
  payload'la yayınlanır. Bu olmadan tasarım eksik kalırdı — bir siparişin
  parası iade edildikten sonra ilgili şubenin kazanılmamış bir komisyonda
  alacaklı görünmeye devam etmesi gerçek bir hata olurdu, "yarım kod"
  disiplininin izin vermeyeceği türden bir boşluk.

### 15. Hakediş defteri + cari bakiye + tahsilat + KDV (Seviye Finance)

Spesifikasyonun Finance sorumluluğu ("Cari, hakediş, komisyon, KDV, iade,
tahsilat") geniş; ilk bölüm **hakediş defterini** (Commerce'in event'lerini
kalıcı, değişmez bir muhasebe kaydına dönüştürme) ve **cari bakiye
görüntülemeyi** (salt okunur REST) kurdu. Bu bölüm onun üzerine **tahsilat
(settlement/payout) defterini**, bir tema panelini ve **KDV tutarının
Commerce'ten Finance'a kadar uçtan uca taşınmasını** ekler. İade akışı bu
bölümün kapsamı dışında kalır (spesifikasyonun "iade" maddesi).

- **Defterin kendisi (`Support\HakedisEventListener`) hâlâ başka hiçbir
  modülün Contracts'ına bağımlı değil**: yalnızca Core'un
  `EventBusInterface`'i üzerinden Commerce'in
  `commerce.order_line_item_completed`/`_reversed` event'lerini dinler —
  bir PHP arayüzü değil, **belgelenmiş bir event adı + payload
  sözleşmesi**. Bu, bölüm 2'de tarif edilen EventBus'ın var oluş amacının
  tam karşılığı. Bu parça, Commerce kurulu olmasa bile hatasız çalışır —
  yalnızca event hiç gelmediği için defter boş kalır.
- **Bu bölümde eklenen bakiye görüntüleme REST'i ise farklı bir hikâye**:
  "hangi şubenin bakiyesini kim görebilir" gerçek bir yetkilendirme
  sorusu, bu yüzden Branches'ın Contracts'ına (`BranchMembershipInterface`,
  `BranchLookupInterface`) Students/Pricing/Commerce'le aynı şekilde
  bağımlıdır. Sonuç: `seviye/finance`'ın `composer.json`'ı artık
  `seviye/branches`'a da bağımlı, ve `Activator`'ı Branches'ın aktif
  olduğunu doğruluyor — yalnızca defter/event-dinleme parçası Core-only
  kalmaya devam ediyor, modülün tamamı değil.
- **Bir düzeltme, bu bölüm inşa edilirken bulundu**: Finance'ın
  migration'ı (`CreateHakedisEntriesTable`) 1. bölümden beri
  `scp_students`'a gerçek bir FK kuruyordu, ama `Activator` yalnızca
  Core'u doğruluyordu — Students aktif değilse bu FK kurulumu
  başarısız olurdu. `Activator` artık Students'ın da aktif olduğunu
  doğruluyor (yalnızca migration'ın FK'sı için — Finance'ın PHP kodu
  hiçbir Students sınıfına dokunmadığından `composer.json`'a
  `seviye/students` eklenmedi, yalnızca WP eklenti başlığındaki
  `Requires Plugins`'e eklendi). Aynı sınıfta gözden kaçmış bir
  eksiklik olması, "yarım kod" disiplininin neden her bölümde
  yeniden gözden geçirmeyi gerektirdiğinin somut bir örneği.
- **`Domain\HakedisEntry`: değişmez, yalnızca-ekleme (append-only) bir
  muhasebe kaydı**: `updated_at` sütunu **yoktur** — bir kayıt asla
  düzenlenmez, yalnızca ters işaretli yeni bir kayıtla düzeltilir (Parents'ın
  KVKK onay zaman damgasının değişmezlik ilkesinin, parayla ilgili
  uygulanışı). `amount` işaretlidir (EARNED için pozitif, REVERSED için
  negatif), böylece bir şubenin bakiyesi her zaman kayıtları üzerinde düz
  bir `SUM()` — ayrıca bakımı gereken, senkronizasyondan çıkabilecek bir
  "toplam bakiye" sütunu yok (YAGNI).
- **`UNIQUE (order_id, order_item_id, type)`: bir defter için asla
  varsayıma dayanmayın**: Commerce'in aynı event'i iki kez yayınlamaması
  beklenir, ama bir muhasebe defterinde bunu sadece varsaymak yanlış yer —
  `HakedisRepositoryInterface::entryExists()` ile birlikte bu kısıtlama,
  bir yinelenen event'in şubeyi iki kez alacaklandırmasını DB seviyesinde
  imkansız kılar.
- **`Support\HakedisEventListener`, Commerce'in adaptörlerinden farklı
  olarak tamamen test edilebilir**: yalnızca Core'un `Event` nesnesine ve
  bu modülün kendi repository'sine dokunur, hiçbir WordPress/WooCommerce
  fonksiyonu çağırmaz — çünkü `EventBus`'ın kendisi zaten WP'den bağımsız
  (bkz. bölüm 2). `WooCommerceCartHooks`/`OrderPersistenceHooks`'un
  aksine, bu sınıf tam birim test kapsamına sahiptir.
- **RBAC: `scp_view_hakedis`/`scp_view_own_hakedis`, "manage" değil
  "view"**: bu modülün REST yüzeyi bu bölümde de salt okunur —
  defterdeki kayıtlar yalnızca `HakedisEventListener` tarafından yazılır,
  hiçbir REST endpoint'i yazma yapmaz. `VIEW_OWN_HAKEDIS`,
  Branches'ın `VIEW_OWN_BRANCH`'ından bilinçli olarak daha dar verilir
  (yalnızca Şube Müdürü + Muhasebe, şube-kapsamlı 5 rolün tamamı değil) —
  bir şubenin finansal bakiyesi, iletişim bilgilerinden daha hassas,
  bu yüzden en-az-yetki kümesi daha küçük.
- **REST: `GET /finance/hakedis/balance/me` ve
  `GET /finance/hakedis/balance/{branch_id}`**, `BranchesRestController`'ın
  `/branches/me` + `canViewBranch()` deseninin birebir aynısı: HQ herhangi
  bir şubeyi sorgulayabilir, şube-kapsamlı roller yalnızca kendi
  şubelerini. `/me`, HQ için de kasıtlı olarak 403 döner (Branches'ta
  olduğu gibi) — HQ'nun "kendi şubesi" diye bir kavramı yok.
- **KDV tutarı, Commerce'ten Finance'a kadar bir "anlık görüntü zinciri"
  olarak taşınır**: WooCommerce'in kendi vergi motoru zaten her sipariş
  kalemi için bir vergi tutarı hesaplıyor
  (`WC_Order_Item_Product::get_total_tax()`); Seviye KDV'yi asla yeniden
  hesaplamaz, yalnızca `price`'ın zaten izlediği "gerçekte neyin tahsil
  edildiğini yeniden türetme" ilkesiyle bu değeri anlık görüntüler.
  `Seviye\Commerce\Domain\OrderLineItem::$vatAmount` →
  `commerce.order_line_item_completed`/`_reversed` event payload'ının
  `vat_amount` alanı → `Seviye\Finance\Domain\HakedisEntry::$vatAmount`.
  Yalnızca tutar saklanır, oran saklanmaz — oran türetilmiş bir değer
  olurdu (`vatAmount / price`), anlık görüntülenen bağımsız bir gerçek
  değil. Bu veri şu an hiçbir REST yanıtında/panelde gösterilmiyor —
  henüz kurulmamış Seviye Reports'un tüketeceği ham muhasebe verisi olarak
  yakalanıyor, tıpkı Commerce'in Finance kurulmadan önce event yayınlamaya
  başlaması gibi (bkz. bölüm 14).
- **Tahsilat (settlement/payout), hakediş defterinden AYRI ikinci bir
  değişmez defter (`scp_hakedis_settlements`), tek bir "ödendi" bayrağı
  DEĞİL**: `scp_hakedis_entries`'e bir `settled_at`/`paid` sütunu eklemek,
  o tablonun "asla düzenlenmeyen, yalnızca-ekleme" ilkesini kırardı (bkz.
  yukarıdaki `Domain\HakedisEntry` maddesi). Bunun yerine bir şubenin
  **alacağı** (`SUM(hakedis_entries.amount)`) ve **ödeneni**
  (`SUM(hakedis_settlements.amount)`) iki ayrı toplama sorgusu, **bakiye**
  ise ikisinin farkı (`HakedisRestController::serializeBalance()`) —
  ikisi de her zaman hesaplanır, hiçbiri ayrıca önbelleklenmez, cari
  bakiyenin kendisiyle aynı "asla senkronizasyondan çıkamayan toplam"
  ilkesi. `balance` alanı geriye dönük uyumlu kalır (mevcut
  `assets/js/hakedis-panel.js`'in zaten okuduğu alan), ama artık brüt
  hakediş toplamı değil, **net/ödenmemiş** tutarı taşır — "cari bakiye"nin
  gerçek anlamı zaten budur; `accrued`/`settled` yalnızca şeffaflık için
  eklenen ek alanlardır.
- **`Domain\HakedisSettlement`, platformdaki tek istisna olarak bir
  `createdAt` alanı taşır**: her Domain sınıfı (bkz. `HakedisEntry`,
  `OrderLineItem`, vb.) bugüne kadar ham bir zaman damgasını asla domain
  nesnesine taşımadı, çünkü hiçbir tüketici buna ihtiyaç duymamıştı.
  "Tahsilat işaretleme"nin can alıcı noktası tam olarak *ne zaman*
  ödendiğini görebilmek olduğundan, burada gerçek bir tüketici (tahsilat
  geçmişi listesi) bu alana ihtiyaç duyuyor — kural kırılmıyor, yalnızca
  ilk kez gerçekten gerekli olduğu için uygulanıyor.
- **`recorded_by`, Security'nin `scp_user_identities.user_id`'siyle aynı
  gerekçeyle `wp_users`'a FK DEĞİLDİR**: WordPress çekirdek tabloları
  için depolama motoru/karakter kümesi garantisi yok, bu yüzden
  `CreateHakedisSettlementsTable` yalnızca `get_current_user_id()`'nin
  zaten doğruladığı bir değeri saklar, DB seviyesinde kısıtlamaz.
- **RBAC: `scp_record_hakedis_settlement`, bu modülün ilk gerçek yazma
  yetkisi**: `VIEW_HAKEDIS`/`VIEW_OWN_HAKEDIS` salt okunur kalmaya devam
  ediyor (defterin kendisi hâlâ yalnızca `HakedisEventListener` tarafından
  yazılıyor), ama bir şubeye fiilen ödeme yapmayı işaretlemek gerçek bir
  yazma eylemi. Bu yetki `VIEW_HAKEDIS`'ten daha dar verilir: Bölge
  Müdürü her şubenin bakiyesini *görebilir* ama hiçbirine *ödeme
  yapmaz* — bu HQ muhasebe işi (Genel Merkez / Muhasebe), Branches'ın
  `MANAGE_BRANCHES` vs. `VIEW_OWN_BRANCH` ayrımıyla aynı gerekçe.
- **REST: `POST /finance/hakedis/settlements` ve
  `GET /finance/hakedis/settlements/{branch_id}`**, `balance()`'ın
  `canViewBalance()` iznini `canAccessBranchFinance()` adıyla genelleştirip
  hem bakiye hem tahsilat-listeleme uçları arasında paylaşır — "kim hangi
  şubenin finansını görebilir" tek bir yerde tanımlı kalır. `POST` ayrıca
  `RECORD_SETTLEMENT`'ı da ister; tutar/yöntem doğrulaması REST katmanında
  yapılır (`amount <= 0` veya geçersiz `method` → 422), tıpkı Pricing'in
  `store()`'unun kendi doğrulamasını REST'te yapması gibi — burada da ayrı
  bir Money/Settlement değer nesnesi kurulmadı, platformun geri kalanının
  hakediş tutarları için zaten kullandığı ham `float` + REST-katmanı
  doğrulaması deseni tekrarlandı.
- **Tema paneli (`hakedis-panel.js`), "görüntüleme" ile "kaydetme"yi ayrı
  yetkilerle render eder**: şube seçici + tahsilat geçmişi
  `canViewAllBranches` altında herkese (Bölge Müdürü dahil) açık, tahsilat
  kaydetme formu yalnızca `canRecordSettlement` altında (Genel Merkez /
  Muhasebe) görünür — REST katmanının izin verdiğinin ötesinde hiçbir şey
  UI'da gösterilmez. Şube-kapsamlı görünümde (Şube Müdürü/Muhasebe) şube
  seçici yoktur; kendi şubesi `GET /finance/hakedis/balance/me`'nin
  döndürdüğü `branch_id`'den örtük olarak bilinir.

`{$wpdb->prefix}scp_{entity}` — bkz. `database/README.md`. Bu, tek bir yerde
(`ConnectionInterface::table()`) merkezileştirilmiştir; hiçbir modül tablo
adını elle birleştirmemelidir.

### 16. İki adımlı doğrulama (2FA) + IP kısıtlaması (Seviye Security)

Spesifikasyonun Security satırındaki son iki madde: "2FA" ve "IP
kısıtlama". İkisi de kasıtlı olarak birbirinden bağımsız, farklı tehdit
modellerine cevap veriyor — 2FA bir hesabın çalınmış şifreyle ele
geçirilmesini zorlaştırır, IP kısıtlaması /admin'e nereden erişilebileceğini
sınırlar.

- **Core'a yeni bir genel amaçlı parça eklendi: `Settings\SettingsRepositoryInterface`**,
  `scp_settings` tablosunun (Core'un ilk milestone'undan beri var olan ama
  hiç gerçek tüketicisi olmayan bir anahtar/değer deposu) üzerine ince bir
  repository. İlk gerçek tüketicisi IP allowlist ayarı — "hiç kullanılmayan
  bir soyutlama kurma" yerine, gerçek bir ihtiyaç doğduğunda platform-geneli
  bir parçayı Core'a eklemenin canlı bir örneği.
- **TOTP (RFC 6238) sıfırdan, üçüncü taraf paket olmadan yazıldı**:
  `TwoFactor\Base32` (RFC 4648 kodlama/çözme) ve `TwoFactor\Totp`
  (HMAC-SHA1, 30 saniyelik adım, 6 hane) tamamen saf PHP — bu iki sınıfın
  doğruluğu RFC 6238 Ek B'nin resmi test vektörlerine karşı doğrulandı
  (bkz. `TotpTest`). Sonuç, Google Authenticator/Authy gibi her standart
  authenticator uygulamasıyla uyumlu, tek bir yeni Composer bağımlılığı
  gerektirmeyen bir 2FA.
- **`TwoFactor\Encryptor`, TOTP sırrını (secret) veritabanında düz metin
  saklamaz**: libsodium'un `secretbox`'ı (XSalsa20-Poly1305) ile
  şifrelenir. Anahtar constructor'a düz bir argüman olarak verilir —
  `wp_salt()` çağrısı sınıfın içine gömülmez — böylece
  `WpCredentialGateway`'in `AuthService`'ten ayrı tutulmasıyla aynı
  gerekçeyle tamamen birim test edilebilir kalır; gerçek WordPress sırrını
  kullanan tek çağıran `SecurityModule::boot()`'taki
  `Encryptor::fromSecret(wp_salt('secure_auth'))` satırı.
- **`scp_two_factor_secrets`, hakediş defterinin aksine değişmez bir defter
  DEĞİL**: bu, canlı ve değişebilir kimlik bilgisi durumu — tıpkı bir şifre
  hash'i gibi — bu yüzden yerinde güncelleme (confirm/disable) burada
  ilkeyi çiğnemiyor. `confirmed_at` NULL iken (kurulum başlatıldı ama henüz
  bir kodla doğrulanmadı) `AuthService`'in giriş akışı bu satırı asla
  "2FA açık" saymaz — yarım bırakılmış bir kurulum kimseyi kilitlemez.
- **Girişte iki adımlı akış, `AuthRestController::login()`'ı bozmadan
  eklendi**: şifre doğru ama 2FA açıksa cookie HENÜZ set edilmez —
  `TwoFactor\PendingTwoFactorLoginService` (Core'un `CacheInterface`'i
  üzerinde, 5 dakikalık bir transient — `RateLimiter`'ın deneme
  sayaçlarıyla aynı "bu durum kalıcı değil" gerekçesi) kısa ömürlü,
  tek-kullanımlık bir `pending_token` üretir. Gerçek girişi tamamlayan
  kod (`wp_set_current_user`/`wp_set_auth_cookie` + rol/redirect yanıtı)
  `finishLogin()`'e çıkarıldı, hem şifre-yeten hem 2FA-kod-yeten yoldan
  çağrılır — kod tekrarı yok.
- **`pending_token` her denemede tüketilir, kod doğru olsa da olmasa da**:
  bir 6 haneli kodu kaba kuvvetle denemenin tek yolu, her denemede
  `AuthService`'in kendi throttle'ından geçen taze bir şifre girişi
  gerektirir. Buna ek olarak `login2fa()` kullanıcı başına ayrı bir
  `RateLimiter` anahtarı (`'2fa:' . userId`) tutar — `AuthService`'in
  MAX_ATTEMPTS/DECAY_SECONDS deseninin birebir aynısı — çünkü doğru
  şifreyi zaten bilen bir saldırgan, başarılı her girişte rate limiter'ı
  sıfırlayan (`clear()`) mevcut şifre-throttle'ından geçip sınırsız taze
  `pending_token` üretebilirdi; bu ikinci, bağımsız throttle o boşluğu
  kapatıyor.
- **`Http\TwoFactorRestController`, bu platformdaki "kapasiteye değil,
  kimliğe bağlı" ilk REST yüzeyi**: `seviye/v1/security/2fa/*` hiçbir RBAC
  yeteneği istemez, yalnızca `is_user_logged_in()` — çünkü her rol kendi
  hesabının 2FA'sını yönetir, bu paylaşılan bir kaynak değil. Devre dışı
  bırakma (`disable()`) yine de şifrenin yeniden girilmesini ister —
  yalnızca aktif bir oturumun yeterli olmadığı, güvenliği düşüren tek
  eylem.
- **IP allowlist saf bir eşleştirici (`Routing\IpAllowlist`) + Core'un
  Settings'i üzerine ince bir REST/enforcement katmanı**: `isAllowed()`
  IPv4/IPv6 ve CIDR'ı `inet_pton()`'un ikili biçimiyle karşılaştırır (düz
  metin karşılaştırma "::1" ile "0:0:0:0:0:0:0:1"'in aynı adres olduğunu
  kaçırırdı). Boş bir liste = özellik kapalı — IP kısıtlaması varsayılan
  değil, açıkça yapılandırılan bir opt-in, tıpkı komisyon oranları gibi
  platformun her yerinde tekrarlanan "gerçek yapılandırma olmadan hiçbir
  kısıtlama uygulanmaz" ilkesi.
- **Uygulama sınırı, RoleRouter'ın zone-gate'iyle BİREBİR aynı, kasıtlı
  olarak**: `theme/inc/ip-restriction.php`, `inc/access-gate.php`'nin
  rol→bölge kontrolüyle aynı `template_redirect` mekanizmasını kullanır
  (öncelik 6 — 5'ten sonra, 10'dan önce) ve yalnızca sayfa render'ını
  kapatır, REST çağrılarını değil. Bu, IP kısıtlamasının açtığı yeni bir
  boşluk değil — platformun REST uç noktaları zaten yalnızca kendi RBAC
  yetenek kontrollerine güveniyor, zone/IP kapılarına değil; çalınmış bir
  oturum çerezi bugün de her iki kapıyı eşit şekilde atlar. Bu sınır
  kararı, bir gözden kaçırma gibi okunmasın diye kod içinde açıkça
  belgelendi.
- **`SecurityCapability::MANAGE_SECURITY_SETTINGS`, Security'nin ilk RBAC
  yeteneği**: önceki her uç nokta ya oturum-öncesi/herkese açık
  (`seviye/v1/auth/*`) ya da kimliğe bağlı self-servisti
  (`seviye/v1/security/2fa/*`). IP allowlist'i yapılandırmak platform
  genelinde bir güvenlik politikası, kişisel bir ayar değil — bu yüzden
  yalnızca Genel Merkez'e verildi, `MANAGE_BRANCHES`'ın tersine Bölge
  Müdürü'ne bile değil.
- **Tema: "Hesap Güvenliği" kartı `templates/partials/account-security.php`
  olarak paylaşılan tek bir partial** — `templates/zone.php` (`/admin`,
  `/sube`) VE `templates/parent-dashboard.php` (`/`) tarafından include
  edilir, çünkü her rol kendi 2FA'sını aynı şekilde yönetir. Bu, temanın
  şimdiye kadar hiç kullanmadığı bir `include` deseni — üç template'e
  aynı ~50 satırlık işaretlemeyi kopyalamak yerine, gerçekten aynı
  markup'ın birden fazla yerde ihtiyaç duyduğu ilk durum. `assets/js/
  account-security.js` de `getElementById` ile hangi sayfada olursa olsun
  aynı elemente bağlanır — panelin kendisi gibi tek bir script iki yerde
  render edilebilir.
- **QR kodu görsel olarak üretilmez**: `TwoFactorSetup::$otpauthUri`
  standart bir `otpauth://` URI'si — telefonda bir authenticator
  uygulaması bu şemayı kayıtlıysa bağlantı doğrudan uygulamada açılır; aksi
  halde ham `secret` elle girilebilir. Yeni bir QR-üretme bağımlılığı
  eklemeden tam işlevsel bir kurulum akışı.

### 17. Şube/ürün/kategori/dönem bazlı satış raporları (Seviye Reports)

Spesifikasyondaki "Raporlar" modülü — kendi `scp_*` tablosu ve migration'ı
YOK; tamamen diğer modüllerin yayınladığı Contracts üzerine kurulu, salt
okunur bir katman.

- **Commerce'ten yeni bir Contract yayınlandı, sadece Reports için**:
  `Seviye\Commerce\Contracts\OrderLineItemQueryInterface` +
  `OrderLineItemRecord` + `OrderLineItemFilter`. Bu, mevcut
  `OrderLineItemRepositoryInterface`'in aynısı değil — o, Commerce'in kendi
  iç `Domain\OrderLineItem`'ini döndürür ve başka bir modülün bağımlı
  olması için tasarlanmadı. `WpdbStudentLookup`/`WpdbBranchLookup`
  deseninin birebir tekrarı: ayrı, minimal bir `WpdbOrderLineItemQuery`
  adaptörü, dinamik `WHERE` cümlesini yalnızca dolu filtre alanlarından
  kurar.
- **`OrderLineItemRecord`, "Domain sınıfı gerçek bir tüketici olmadan ham
  zaman damgası taşımaz" ilkesinin `HakedisSettlement`'tan sonraki ikinci
  belgeli istisnası**: `createdAt` taşır, çünkü Reports'un dönem bazlı
  filtreleme için "ne zaman" bilgisine gerçekten ihtiyacı var; Commerce'in
  kendi iç `Domain\OrderLineItem`'i hâlâ `createdAt` taşımıyor.
- **Kategori, sipariş anında ASLA snapshot'lanmaz**: `productId` (bir fiyat
  gerçeği gibi sipariş-anı bilgisi) snapshot'lanırken, ürünün kategorisi
  rapor ANINDA WooCommerce'in kendi canlı taksonomisinden çözülür
  (`has_term($categoryId, 'product_cat', $productId)`) —
  `Domain\OrderLineItem`'ın docblock'unda "bir raporun canlı çözebileceği
  güncel-durum bilgisi" olarak, `commission_rate`/`price` gibi
  sipariş-anı-gerçeklerinden ayrı belgelendi.
- **Satış raporu yalnızca `completed` sipariş kalemlerini sayar**:
  `ReportsRestController::REPORT_STATUS`, Finance'in kendi hakediş-tetikleme
  tanımıyla ("gerçekten gerçekleşmiş, hâlâ iade edilebilir değil" —
  `processing` değil) birebir aynı, kasıtlı olarak sabit kodlanmış — hâlâ
  iade edilebilecek parayı gösteren bir rapor, eksik değil, yanıltıcı
  olurdu.
- **RBAC, Finance'in `HakedisCapability`'sinin birebir aynası**:
  `ReportCapability::VIEW_REPORTS` (Genel Merkez/Bölge Müdürü, her şube)
  ve `VIEW_OWN_REPORTS` (Şube Müdürü, yalnızca kendi şubesi).
  `ReportsRestController::effectiveBranchId()`, "sunucu kapsamı çözer,
  istemciye asla güvenmez" kuralının Students/Pricing'in yazma
  uçlarından sonra bir OKUMA uç noktasına uygulandığı ilk yer: şube-scoped
  bir rol için, isteğin gönderdiği `branch_id` ne olursa olsun kendi şubesi
  her zaman kazanır.
- **Excel export, PhpSpreadsheet gibi ağır bir Composer bağımlılığı
  eklemeden sıfırdan yazıldı**: `Support\XlsxExporter`, `ZipArchive` ile
  minimal ama gerçekten geçerli 5 parçalık bir OOXML üretir
  (`[Content_Types].xml`, `_rels/.rels`, `xl/workbook.xml`,
  `xl/_rels/workbook.xml.rels`, `xl/worksheets/sheet1.xml`) — 2FA'nın
  TOTP'si için verilen "dar, iyi anlaşılmış bir format için ağır bağımlılıktan
  kaçın" kararının aynısı. Üretilen dosya gerçekten `openpyxl` ile
  okutularak doğrulandı (sayfa adı, başlık satırı, Türkçe karakterler,
  sayısal tipler).
- **CSV export, `fputcsv()`'nin kendisinden fazlasını gerektirmedi** — UTF-8
  BOM önekli, RFC 4180 uyumlu, Excel'in Türkçe karakterleri doğru
  göstermesi için.
- **Dosya indirme, bu kod tabanındaki ilk gerçek dosya-indirme REST
  uç noktası**: `format=csv|xlsx`, `WP_REST_Server`'ın normal JSON
  zarfını tamamen atlar — `header()` + `echo` + `exit`, callback döneden
  ÖNCE, sınıfın docblock'unda belgelenen standart WP REST dosya-servis
  deseni. Bir `<a href>` indirme tıklaması `fetch()`'in aksine özel başlık
  taşıyamadığından, nonce `X-WP-Nonce` başlığı yerine bir `_wpnonce` sorgu
  parametresi olarak taşınır (`rest_cookie_check_errors()` ikisini de
  kabul eder) — tema tarafında `assets/js/reports-panel.js`'nin CSV/Excel
  butonları bu deseni kullanır.
- **Tema: "Raporlar" bölümü, "Cari Bakiye"nin HQ/kendi-şube ayrımını
  birebir tekrarlar** — `scp_view_reports` (HQ) bir şube filtresi görür
  (boş = her şube), `scp_view_own_reports` (Şube Müdürü) sessizce kendi
  şubesine kilitlenir. "Getir" JSON görünümünü sayfa içinde yükler; CSV/Excel
  butonları ise tarayıcıyı doğrudan indirme URL'sine yönlendirir.

### 18. E-posta/SMS/panel-içi bildirim gönderimi (Seviye Notifications)

Spesifikasyondaki "Bildirimler" modülü — Reports gibi kendi `scp_*`
tablosu var (`scp_notifications`, tek bir tablo) ama HİÇBİR modülün
Contracts'ını gerektirmez (Parents hariç, aşağıda) ve tamamen Core'un
EventBus'ı üzerinden tetiklenir.

- **`record → resolve recipient → send → mark-sent/failed`, üç kanal
  (EMAIL/SMS/PANEL) için de birebir aynı akış** —
  `Dispatch\NotificationDispatcher`, PANEL'i özel durum olarak ele almaz:
  `Channel\PanelChannel::send()` her zaman başarı bildirir (satırın kendisi
  zaten teslimattır), ama yine de aynı akıştan geçer. Bu, Domain\Notification
  ve Domain\NotificationStatus'ün docblock'unda açıkça gerekçelendirildi.
- **`scp_notifications`, platformun finansal defterlerinin (hakediş
  kayıtları/tahsilatları) aksine yerinde GÜNCELLENİR** (`status`, `sent_at`,
  `read_at`) — bu, "defter, asla UPDATE değil" ilkesinin bilinçli bir
  istisnası: bir bildirimin teslimat durumu canlı, değişebilir durumdur
  (tıpkı Security'nin `scp_two_factor_secrets.confirmed_at`'ı gibi), finansal
  geçmiş değil.
- **E-posta kanalı (`Channel\EmailChannel`) WordPress'in kendi `wp_mail()`'i
  üzerine ince bir sarmalayıcı** — yeni bir Composer bağımlılığı veya
  üçüncü taraf kimlik bilgisi gerektirmez, sitenin zaten yapılandırılmış
  posta taşıyıcısını (PHP mail(), bir SMTP eklentisi, ...) kullanır. TOTP/
  Reports'un XLSX yazıcısıyla aynı "ağır bağımlılıktan kaçın" ilkesi.
- **SMS kanalı (`Channel\NetgsmSmsChannel`), NetGSM'in genel belgelenmiş
  REST API'sine karşı sıfırdan yazıldı** (SDK yok) — kimlik bilgileri
  (usercode/password/msgheader) Core'un
  `Settings\SettingsRepositoryInterface`'i üzerinde,
  `seviye/v1/notifications/sms-settings` (yalnızca Genel Merkez) ile
  yapılandırılır; IP allowlist'in "boş = devre dışı" deseninin aynısı —
  yapılandırılmamışsa kanal sessizce başarısız olur (dispatcher bunu
  dürüstçe FAILED olarak kaydeder), asla sahte bir başarı döndürmez.
  Telefon numarası biçimi (`normalizePhone()`) ve yanıt kodu ayrıştırması
  (`isSuccessCode()`) saf, birim test edilebilir metotlar olarak ayrıldı;
  gerçek `wp_remote_post()` çağrısı (EmailChannel'ın `wp_mail()`'i gibi)
  test edilmedi.
- **SMS alıcısı, Seviye Parents'ın yeni yayınladığı
  `Contracts\ParentContactLookupInterface` üzerinden çözülür** — platformun
  wp_users dışında tek telefon numarası kaynağı `scp_parent_profiles.phone`
  (nullable) olduğundan, SMS bugün yalnızca telefon numarası kayıtlı veli
  hesapları için gerçekten çalışır; her başka rol (personel, HQ) dürüstçe
  "alıcı yok" alır, sessizce başarılı sayılmaz. `WpdbParentContactLookup`,
  `WpdbStudentLookup`/`WpdbBranchLookup` deseninin bir tekrarı: ayrı, minimal
  bir adaptör, Parents'ın iç Repository'sini değil.
- **İlk gerçek EventBus tüketicisi:
  `security.password_reset_requested`** — Security'nin şifre/ilk-kurulum
  token sistemi bu event'i milestone 13'ten beri dispatch ediyordu, ama
  hiçbir dinleyicisi yoktu (root README.md/theme README.md'de
  "Planlandı (Seviye Notifications'ın sorumluluğu)" olarak işaretliydi).
  `Support\PasswordResetNotificationListener`, Finance'in
  `HakedisEventListener`'ı gibi yalnızca event adı/payload şekline bağımlı
  (`user_id`, `token`, `purpose`) — Security'nin sınıflarını veya
  Contracts'ını asla import etmez.
- **`__()`'ün `$text` argümanı bir string literal kalmalı**
  (WordPress'in kendi i18n aracı çağrı noktalarını statik olarak ayrıştırıp
  `.pot` dosyası üretir) - `PasswordResetNotificationListener`, bu kısıtı
  `function_exists('__')` koruması altında bile bir DEĞİŞKENİ `__()`'e
  argüman olarak geçirerek çiğneyen ilk denemeden sonra, her dalın kendi
  `function_exists('__')` korumasını tekrarladığı (paylaşılan bir
  `translate($text)` yardımcısı yerine) bir desene düzeltildi - PHPCS'in
  `WordPress.WP.I18n.NonSingularStringLiteralText` kuralı tarafından
  yakalandı.
- **Panel-içi bildirim çanı (`assets/js/notifications-bell.js`),
  `templates/zone.php`'de DEĞİL `header.php`'de yaşıyor** — temanın
  şimdiye kadarki her paylaşılan/koşulsuz bileşeninden (Hesap Güvenliği
  partial'ı dahil) farklı olarak, bu bileşenin oturum açmış HER sayfada
  (WooCommerce mağaza/ürün sayfaları dahil) görünmesi gerekir, yalnızca
  panel sayfalarında değil - `header.php` zaten her kimliği doğrulanmış
  görünümde render edildiğinden doğal yer burasıdır.
- **SMS ayarları formu, şifre alanını asla geri döndürmez** (GET yalnızca
  `usercode`/`msgheader`/`configured` döner) ve boş bırakılan bir şifre
  PUT'ta mevcut şifreyi DEĞİŞTİRMEZ — bu platformda üçüncü taraf bir
  kimlik bilgisi saklayan ilk form, "değiştirmeye çalışmadığın bir sırrı
  boşaltma" UX'i ilk kez burada uygulandı.

### 19. Programatik erişim: API anahtarı kimlik doğrulaması (Seviye API)

**Kapsam kararı, en başta açıkça belgelendi**: "Seviye API" yeni bir iş
mantığı REST yüzeyi DEĞİL — her modül zaten kendi `seviye/v1/*`
uçlarının sahibi. Bu modülün tek işi, o AYNI uçları bir tarayıcı
cookie+nonce oturumu OLMADAN erişilebilir kılmak — ERP/muhasebe/mobil
entegrasyonlarının ihtiyaç duyduğu şey tam olarak bu. `ApiModule`'ün
docblock'unda bu kapsam kararı kasıtlı olarak açıkça yazılı.

- **Anahtar, düz metin olarak asla saklanmaz** — yalnızca SHA-256 özeti
  (`Support\ApiKeyGenerator::hash()`). Bu, Security'nin parola/TC Kimlik No
  için kullandığı YAVAŞ, tuzlu (bcrypt tarzı) hash'ten kasıtlı olarak
  farklı: bir parola düşük entropili, insan seçimlidir ve çevrimdışı
  tahmine karşı direnç gerektirir (yavaş hash'in tüm amacı budur); bir API
  anahtarı ise zaten `random_bytes(24)`'ün 192 bit'i — tahmin edilemez —
  bu yüzden hash'lemenin tek amacı sırrı düz metin saklamamaktır, ve hızlı,
  deterministik bir özet `WHERE key_hash = ?` sorgusunu O(1) bir aramaya
  çevirir (tuzlu bir bcrypt/Argon2 hash'i deterministik olmadığından bu
  şekilde aranamaz). GitHub/Stripe tarzı platform API anahtarlarının
  kullandığı aynı gerekçe.
- **`Auth\ApiKeyAuthenticator` tamamen saf ve birim test edilebilir** —
  yalnızca bu modülün kendi repository'sine ve Core'un `RateLimiter`'ına
  dokunur, `wp_set_current_user()`'ı asla doğrudan çağırmaz.
  `Http\ApiKeyAuthHook` (WP'nin `rest_authentication_errors` filtresini
  bağlayan ince adaptör) tek gerçek WordPress bağımlılığını taşır ve test
  edilmedi — bu koddaki her doğrudan WP hook adaptöründeki aynı desen.
  IP başına throttle edilir (anahtarın kendisine göre değil — bir
  saldırgan anahtarı serbestçe değiştirebilir), Security'nin giriş
  throttle'ıyla aynı MAX_ATTEMPTS/DECAY_SECONDS şekli.
- **`rest_authentication_errors`, bu platformdaki ilk kullanımı** —
  `rest_cookie_check_errors()`'ın da kullandığı aynı WP çekirdek uzantı
  noktası. WP çekirdeğinin kendi deyimini birebir izler:
  `if (!empty($result)) return $result;` en üstte — bu, hangi filtre
  callback'inin önce çalıştığından BAĞIMSIZ olarak, bu filtrenin başka bir
  yöntemin ürettiği bir kimlik doğrulama sonucunu (başarı veya hata) asla
  ezmemesini garanti eder; iki taraf da aynı deyimi kullandığından sıralama
  önemsizleşir. `Authorization: Bearer` başlığı yoksa `$result` dokunulmadan
  döner — düz bir tarayıcı isteği her zamanki gibi cookie+nonce'a düşer,
  bu uç nokta mevcut panelleri asla bozmaz.
- **RBAC, kişisel değil paylaşılan bir kaynak modeli** —
  `ApiCapability::MANAGE_API_KEYS` yalnızca Genel Merkez'e verilir
  (`MANAGE_SECURITY_SETTINGS`/`MANAGE_NOTIFICATION_SETTINGS`'in aynı
  "platform genelinde en yetkili tek rol" deseni). `ApiKeysRestController`,
  2FA/Notifications'ın "yalnızca kendi kaynağın" self-servis desenini
  İZLEMEZ — Genel Merkez platform genelindeki HER anahtarı görür/yönetir,
  çünkü bir anahtarın sahibi (`user_id`) genellikle anahtarı oluşturan
  Genel Merkez kullanıcısı değil, belirli bir dış entegrasyon için
  wp-admin'de oluşturulmuş bir `Sistem` rolü hesabıdır (spesifikasyonun 9
  rolünden biri, bu modülden önce hiç kullanılmamıştı) — `POST /api-keys`
  isteğe bağlı bir `user_id` kabul eder, verilmezse çağıran kullanıcıya
  düşer.
- **Bir anahtarın düz değeri yalnızca oluşturma anında, bir kez
  gösterilir** (`Domain\GeneratedApiKey`) — sunucu bunu hiçbir zaman
  saklamaz, bu yüzden ondan sonra hiçbir REST çağrısı onu geri
  döndüremez. Tema tarafı bunu `POST` yanıtından doğrudan gösterir,
  kaybolursa yeni bir anahtar oluşturmaktan başka çare yoktur — bu bir
  eksiklik değil, sırrın tek bir yerde var olmasını sağlayan kasıtlı bir
  tasarım.
- **`revoked_at`, iptal edilen bir anahtarın satırını SİLMEZ** —
  Notifications'ın `status` sütunlarıyla aynı denetim-izi gerekçesi:
  hangi entegrasyonun ne zaman bir anahtara sahip olduğu ve ne zaman iptal
  edildiği bilgisi korunur.

### 20. Native wp-admin'de "Seviye Kullanıcılar" menü ağacı (Seviye Security)

Şu ana kadar bir kullanıcıya Seviye rolü + T.C. Kimlik No eşleşmesi vermenin
tek yolu kurulum sihirbazının tek seferlik demo-admin adımıydı — sıradan bir
personel hesabı açmanın native bir yolu yoktu. Bu bölüm, wp-admin'de
kendi başına duran bir **"Seviye Kullanıcılar"** üst menüsü olarak dört
sayfa ekliyor: **Seviye Kullanıcılar**, **Seviye Yetkilendirme**, **Veli**,
**Öğrenci**.

- **Neden `Kullanıcılar` alt menüsü değil, kendi başına üst menü**:
  ilk sürüm `manage_options` ile kapılıydı ve WordPress'in native
  `Kullanıcılar` menüsü altına eklenmişti — ama Genel Merkez'in native
  `list_users`/`edit_users` capability'si hiç olmadı, yani o menüyü hiç
  GÖREMİYORDU bile. `Http\Admin\AdminAccess`, tek bir capability'ye
  (`SecurityCapability::MANAGE_SECURITY_SETTINGS`) geçirdi — bu hem
  Role::GENEL_MERKEZ'e (zaten vardı) HEM DE `SecurityModule::boot()`'ta
  artık native `administrator` rolüne de (`get_role('administrator')->add_cap(...)`,
  RbacManager'ın yalnızca Core'un 9 Role'ünü kabul etmesi yüzünden
  doğrudan WP rol API'siyle) veriliyor — tek capability, tek menü ağacı,
  hem Genel Merkez hem gerçek WordPress yöneticisi erişebiliyor.
- **Şifreler asla düz metin olarak "görülemez"** — bu platformdaki her
  şifre/T.C. No hash'i gibi (bkz. bölüm 6-7) tek yönlü. `Http\Admin\UserAuthorizationAdminPage`'in
  şifre alanı bunu ihlal etmiyor: Genel Merkez yeni bir şifre BELİRLER,
  kaydettiği anda o şifreyi bir kerelik düz metin olarak (30 saniyelik
  transient tabanlı bildirimde) görür ve ilgiliye iletir — daha önce
  belirlenmiş bir şifreyi sonradan görüntülemek DEĞİL.
- **Seviye Kullanıcılar / Veli — aynı sınıf, farklı rol filtresi**:
  `Http\Admin\UserListPage`, `administrator` OLMAYAN her kullanıcıyı (veya
  yalnızca `Role::VELI` olanları) listeler; her satırda ad/e-posta/rol/T.C.
  No/şifre düzenleme + hesap silme, hepsi TEK formda. İlk sürümde rol/T.C.
  No/şifre kasıtlı olarak ayrı bir sayfada (Yetkilendirme) tutulmuştu, ama
  gerçek kullanımda bu "iki sayfaya bölünmüş tek işlem" hissi kafa
  karıştırdı — bir kullanıcı oluşturuldu ama T.C. No hiç eklenmediği için
  giriş yapamadı, ve o kullanıcının satırında T.C. No alanı GÖRÜNMEDİĞİ
  için "veritabanına kaydedilmiyor" sanıldı (aslında sadece o sayfada
  gösterilmiyordu). `UserListPage::handleSave()` artık
  `UserAuthorizationAdminPage::handleSave()` ile aynı doğrula-sonra-uygula
  mantığını taşıyor; iki sınıf arasında bilinçli bir kod tekrarı var
  (Yetkilendirme sayfası hâlâ ayrı bir odaklı görünüm olarak duruyor, ama
  artık Kullanıcılar/Veli'nin kendisi de tamamen kendi kendine yeterli).
- **"Yeni Kullanıcı Ekle" formu da bu sayfada, `UserListPage::handleCreate()`** -
  bu, wp-admin'de brand-new bir WP kullanıcısı oluşturabilen TEK yer:
  Genel Merkez'in native `create_users` capability'si hiç olmadığı için,
  bu form olmadan yeni bir personel/veli hesabı açmanın hiçbir yolu yoktu
  (var olan kullanıcıları düzenlemek/rol atamak mümkündü, ama sıfırdan
  oluşturmak değil). Ad/e-posta/rol/şifre ZORUNLU (rolsüz veya şifresiz
  bir Seviye kullanıcısının hiçbir işe yaramayacağı için, düzenleme
  formundakinin aksine burada opsiyonel değiller); T.C. Kimlik No
  opsiyonel (girilmezse hesap oluşur ama Seviye giriş ekranından
  kullanılamaz - `wp_insert_user()`'ın kendi native ekranı gibi).
  `user_login`, e-postanın `@` öncesi kısmından türetilip
  `username_exists()` ile çakışma varsa `-2`, `-3`... eklenerek
  benzersizleştiriliyor (WordPress e-posta ile kullanıcı adının aynı
  olmasını zorunlu KILMIYOR, ama iki alanın birbirinden bağımsız benzersiz
  olması gerekiyor).
- **Hesap silme, Security'nin kendi tablosunu temizler, başka hiçbir
  modülünkini DEĞİL**: `handleDelete()` önce `IdentityGatewayInterface::unlink()`
  ile `scp_user_identities`'i temizler (bu tablonun `wp_users`'a FK'sı yok,
  bkz. `CreateUserIdentitiesTable`'ın docblock'u), sonra native
  `wp_delete_user()`'ı çağırır. `scp_student_parents.parent_user_id`,
  `scp_branch_users.user_id` gibi BAŞKA modüllerin user_id'ye referans
  veren satırları KASITLI OLARAK dokunulmadan bırakır — Security'nin o
  tablolara erişim yetkisi yok (bkz. "Kural"), bu bilinen ve belgelenmiş
  bir sınırlama.
- **Öğrenci sayfası: yeni bir Contract, Students'tan** — öğrenciler WP
  kullanıcısı değil (ayrı `scp_students` varlığı), bu yüzden bu sayfa
  Students'ın YENİ yayınladığı `Contracts\StudentDirectoryInterface`
  (+ zengin okuma modeli `StudentDirectoryEntry` - sınıf/eğitim
  yılı/durum dahil, `StudentSummary`'den daha zengin çünkü admin dizini
  farklı bir tüketici ihtiyacı) ve Branches'ın `BranchLookupInterface`'i
  üzerinden çalışıyor. Salt okunur: tam CRUD zaten temanın `/admin`,
  `/sube` panellerinde var, burası sadece wp-admin'den çıkmadan görme
  kolaylığı.
- **Security artık Branches'a VE Students'a bağımlı (`composer.json`)** —
  bu platformda ilk kez, Security kendi dışında bir modülün Contract'ına
  bağımlı oluyor. Ama bilinçli olarak plugin başlığının
  `Requires Plugins`'ine EKLENMEDİ: Security erken/temel bir modül (auth
  her şeyden önce çalışmalı), Students/Branches kurulu olmasa bile
  aktifleşebilmeli ve çalışabilmeli kalmalı. `Http\Admin\StudentDirectoryPage`
  bunu, resolve edilmiş bir instance değil `ServiceContainer`'ın kendisini
  enjekte ederek çözüyor — `$container->has(StudentDirectoryInterface::class)`
  kontrolü ve gerçek `get()` çağrısı yalnızca `render()` içinde,
  sayfa gerçekten yüklendiğinde çalışıyor; `SecurityModule::boot()` içinde
  ASLA — orada yapılsaydı, tam olarak Commerce'in ve Notifications'ın
  daha önce düzeltilen modül-boot-sırası fatal'ini tekrar ederdi (bkz. bu
  dosyanın başındaki "İkinci kural").
- **`IdentityGatewayInterface` iki yeni metotla genişledi**:
  `findTcNumberByUserId()` (ters arama - mevcut değeri formda göstermek
  için) ve `unlink()` (bir T.C. No'yu değiştirmek, `scp_user_identities`
  tablosunun `tc_no`/`user_id` üzerindeki UNIQUE kısıtlarıyla çakışmadan
  önce eskisini silmeyi gerektirir). Var olan tek çağıran (kurulum
  sihirbazının demo-admin adımı) `link()`'in imzasını hiç değiştirmediği
  için bozulmadı.
- **Doğrulama, uygulamadan önce tamamen biter**: `handleSave()` rolü, T.C.
  No'yu ve şifreyi önce doğrular (format + başka bir kullanıcıya zaten
  bağlı mı + minimum uzunluk), YALNIZCA hepsi geçerliyse uygular —
  geçersiz bir alan, diğerlerini yarım bırakmış halde uygulanmış
  bırakmaz.
- **`WpdbIdentityGateway::link()` artık INSERT başarısız olursa SESSİZCE
  başarı raporlamıyor** — önceki sürüm `$wpdb->insert()`'in dönüş değerini
  hiç kontrol etmiyordu, yani bir UNIQUE KEY çakışması (`tc_no` veya
  `user_id` üzerinde, ör. önceki yarım kalmış bir denemeden kalan satır)
  veya tablo/şema sorunu olsa bile arayüz her zaman "Kaydedildi" diyordu -
  gerçek durumu asla yansıtmayan bir bildirim. Artık `$wpdb->insert()`
  `false` dönerse `$wpdb->last_error`'ı taşıyan bir `RuntimeException`
  fırlatılıyor; `UserListPage`/`UserAuthorizationAdminPage`'in dört
  çağrı noktası da (`handleSave`, `handleCreate`, `saveProfileField`) bunu
  yakalayıp gerçek veritabanı hatasını kırmızı bildirimde gösteriyor.
- **Kendi şifrenizi bu sayfadan değiştirmek sizi ATMAZ** — `wp_set_password()`
  WordPress çekirdeğinin kendi davranışı gereği o kullanıcının TÜM oturum
  token'larını yok eder, işlemi yapan tarayıcı sekmesi dahil. Canlıda
  gerçekten yaşandı: bir operatör kendi hesabının şifresini bu panelden
  değiştirdi, sayfa yenilenmeden aynı sekmede başka bir işlem (şube
  ekleme) denedi ve WordPress'in kendi genel `rest_forbidden` / 401
  hatasını aldı - REST isteği o an gerçekten "giriş yapılmamış" görünüyordu,
  görünürde hâlâ oturum açıkken. `handleSave()`, hedef kullanıcı
  (`$userId`) o an giriş yapmış kullanıcının (`get_current_user_id()`)
  kendisiyse `wp_set_password()`'dan hemen sonra `wp_clear_auth_cookie()` +
  `wp_set_auth_cookie($userId)` çağırıyor - WordPress'in kendi
  "Kullanıcıyı Düzenle" ekranının (`wp-admin/user-edit.php`,
  `IS_PROFILE_PAGE` dalı) kendi şifresini değiştiren bir kullanıcı için
  yaptığı AYNI şey.
- Hiçbiri unit test edilmedi, bu koddaki her doğrudan WP-admin-dokunan
  adaptörle aynı gerekçeyle (bkz. "Test stratejisi").

### 21. Native wp-admin'de "Seviye Şubeler" sayfası + kurulum sihirbazı gerçek güncelleme (Seviye Branches, tema)

Bölüm 20'nin Branches karşılığı, aynı gerekçeyle: temanın kendi `/admin`
Şube Yönetimi paneli yalnızca Seviye rolü taşıyan bir WP kullanıcısına
açık, siteyi işleten gerçek WordPress `administrator` hesabına değil.
`Http\Admin\BranchAdminPage`, `BranchCapability::MANAGE_BRANCHES`
capability'siyle kapılı (artık `BranchesModule::boot()`'ta hem
`Role::GENEL_MERKEZ`/`Role::BOLGE_MUDURU`'ya HEM DE doğrudan native
`administrator` rolüne veriliyor, Security'nin `AdminAccess` deseninin
aynısı) — liste/oluştur/düzenle tek sayfada, `UserListPage`'in satır-içi
form desenini birebir izliyor.

**Kurulum sihirbazı artık zaten-aktif bir bundled eklentiyi gerçekten
GÜNCELLİYOR, atlamıyor** — canlıda gerçekten yaşandı: sihirbaz her
adımda "zaten etkin mi" diye bakıp öyleyse hiçbir şey yapmadan
geçiyordu, yani paketteki daha yeni bir eklenti zip'i asla devreye
girmiyordu; kullanıcı her güncellemede eklentiyi manuel SİLİP yeniden
kurmak zorunda kalıyordu (ki bu, aktivasyon sırasına bağlı modül-boot
hatalarını YENİDEN tetikleme riski taşıyor). `scp_run_setup_step()`
artık `bundled` tipteki her adım için `scp_install_bundled_plugin()`'i
HER ZAMAN çağırıyor (yalnızca hiç kurulu değilse değil),
`Plugin_Upgrader::install()`'a `'overwrite_package' => true` geçirerek —
WordPress'in kendi "Yükle → Mevcut olanla değiştir" onay ekranının
kullandığı AYNI bayrak (WP 5.5+). WordPress.org eklentileri
(WooCommerce) bu davranıştan muaf — onların sürüm yönetimi çekirdeğin
kendi güncelleyicisinin işi, bu kurulumcu asla üzerine yazmıyor. Sonuç:
paketi güncelleyip sihirbazı tekrar çalıştırmak artık gerçekten
güncelliyor; temanın kendisi zaten WordPress'in native "temayı yükle,
mevcut olanla değiştir" akışını kullanabiliyor (ek kod gerekmedi, sadece
silmeden üzerine yükleme).
- Hiçbiri unit test edilmedi, aynı gerekçeyle.

### 22. "Seviye Şubeler" sayfasında Şube Müdürü/Yetkili atama (Seviye Branches)

`BranchMembershipInterface::assign()`/`unassign()` (scp_branch_users -
"Yetkililer") daha önce hiçbir yerden - ne native wp-admin'den ne temanın
kendi `/admin` panelinden - çağrılmıyordu; bir kullanıcıyı bir şubeye
atamanın hiçbir yolu yoktu, yalnızca alt katman (repository + migration)
mevcuttu. `BranchAdminPage`'in her şube satırına "Şube Müdürü / Yetkili"
bölümü eklendi: o şubeye atanmış kullanıcılar (ve her biri için "Kaldır"
butonu) + WordPress'teki tüm kullanıcıları listeleyen bir seçim kutusuyla
"Ata" formu. `BranchMembershipInterface`'e yeni bir `usersForBranch(int
$branchId): list<int>` metodu eklendi (`branchIdForUser()`'ın tersi
yönü) - tek implementasyonu `WpdbBranchMembershipRepository`, başka hiçbir
modülde bu Contract'ı implemente eden bir sahte (fake) yok (REST
controller'ları zaten unit test edilmiyor), bu yüzden interface'i
genişletmek güvenliydi. Aynı `BranchCapability::MANAGE_BRANCHES` capability'si
kullanılıyor - branch CRUD'u yapabilen (Genel Merkez/Bölge Müdürü + native
`administrator`) yetkili atamasını da yapabiliyor, ayrı bir capability
eklenmedi.

### 23. Öğrenci eklerken veli otomatik oluşturma/bağlama, eğitim yılı menüsü, kurumsal tasarım geçişi

Öğrenci "Yeni Öğrenci" formuna isteğe bağlı bir "Veli Bilgileri" bölümü
eklendi (ad/soyad/e-posta/yakınlık/T.C. Kimlik No). Doldurulursa
`StudentsRestController::store()`, öğrenciyi oluşturduktan SONRA
`maybeCreateAndLinkParent()`'ı çalıştırır: e-postayla eşleşen bir WP
kullanıcısı zaten varsa onu kullanır (aynı velinin ikinci çocuğu durumu -
bu durumda yeni şifre ÜRETİLMEZ, mevcut hesap değişmez), yoksa native
`wp_insert_user()` ile `Role::VELI` rolünde, `wp_generate_password(16,
true)` ile üretilmiş bir şifreyle yeni bir kullanıcı açar, sonra
`StudentParentRepositoryInterface::link()` ile öğrenciye bağlar. Öğrenci
zaten oluştuktan sonra çalıştığı için burada bir hata öğrenci kaydını
geri almaz (bu kod tabanında hiçbir yerde DB transaction yok) - `store()`
201 döner ama yanıta `parent_error` alanı eklenir, JS bunu "Kaydedildi"
mesajının yanında gösterir. Yeni oluşturulan hesap için (mevcut hesap
yeniden kullanılmadıysa) yanıta ayrıca bir kerelik `parent_credentials`
(`user_id`/`name`/`email`/`password`) alanı eklenir - WordPress şifreleri
düz metin saklamadığı için bu, üretilen şifrenin görünebileceği TEK an.

**T.C. Kimlik No bağlama - döngüsel bağımlılık yerine REST kompozisyonu**:
Students, T.C. No'yu KENDİSİ bağlayamaz - Security zaten Students'ın
Contract'larına bağımlı (`StudentDirectoryInterface`, bkz.
`SecurityModule::boot()`), Students'ın da Security'ye bağımlı olması
PHP/composer seviyesinde DÖNGÜSEL bir bağımlılık yaratırdı. Bunun yerine
Security'de yeni bir REST uç noktası açıldı:
`POST seviye/v1/security/users/{id}/tc-no` (`IdentityRestController`,
`SecurityCapability::MANAGE_SECURITY_SETTINGS` ile kapılı) -
`IdentityGatewayInterface::link()`'i sarmalıyor, geçersiz biçim için 422,
gerçek DB hatası (ör. zaten başka bir kullanıcıya bağlı bir T.C. No) için
409 dönüyor. Temanın `students-panel.js`'i, `/students`'tan dönen
`parent_credentials.user_id` ile bu uç noktayı AYRI bir istekte çağırıyor
- tıpkı temanın öğrenciler/şubeler/fiyatlandırma REST API'lerini zaten
bağımsız uç noktalar olarak birleştirdiği gibi, sadece PHP seviyesinde
DEĞİL, JS seviyesinde bir kompozisyon. T.C. No bağlama başarısız olursa
(kötü biçim/çakışma) veli hesabı ve şifresi yine de geçerlidir - sadece
T.C. No ile giriş o ana kadar çalışmaz, `data-scp-registration-summary`
kartında bu durum ayrıca gösterilir.

Eğitim yılı artık `<input type="text" placeholder="2025-2026">` değil,
`current_time('Y')`'den -1..+3 aralığında üretilen bir `<select>` -
`EducationYear::isValid()` zaten yalnızca "YYYY-YYYY" biçimini kabul
ediyordu, serbest metin kullanıcıyı sessizce 422'ye götürebiliyordu.

**Kurumsal e-ticaret tasarım geçişi**: `theme.css`'in `:root` token seti
genişletildi (koyu lacivert birincil + zümrüt "ticaret" vurgu rengi,
`--scp-radius`, ayrı warning/info badge renkleri, zenginleştirilmiş
gölgeler) - `panel.css`/`auth.css` bu token'ları tüketiyor, bu yüzden tek
bir yerden değiştirmek tüm panelleri ve giriş ekranını tutarlı şekilde
güncelledi. Yeni `assets/css/woocommerce.css` - temanın hiç WooCommerce
şablon override'ı yok (bkz. `inc/woocommerce.php`), bu yüzden mağaza
WC'nin KENDİ ürettiği markup'ı hedefleyen CSS ile yeniden tasarlandı
(`.woocommerce ul.products`, `.single-product div.product`,
`.woocommerce-cart table.cart`, vb.) - şablon dosyası değişmediği için WC
sürüm güncellemelerine karşı daha güvenli. `class_exists('WooCommerce')`
doğruysa `inc/assets.php`'te koşullu enqueue edildi.

### 24. Kayıt özeti gizlenme hatası, Şube Müdürü T.C. No yetkisi, öğrencinin kendi T.C. No'su, mağaza sayfası kendiliğinden onarımı

**Gerçek bir hata**: bölüm 23'te eklenen "Kayıt Özeti" reveal kartı,
`<form data-scp-student-form>`'un İÇİNDE, form'un kapanış etiketinden
hemen önce render ediliyordu. `students-panel.js`'in `finish()`'i,
`showRegistrationSummary()`'i çağırıp kartı görünür yaptıktan HEMEN SONRA
`form.hidden = true;` çalıştırıyordu - kart `hidden=false` olsa bile,
HTML'de `[hidden]` bir ata elemente uygulanınca tüm alt elemanları da
görünmez kılar, bu yüzden kart aslında hiç görünmüyordu. Düzeltme: kart
artık `</form>`'dan SONRA, `<section>`'ın kendi içinde ama form'un
DIŞINDA bir kardeş eleman - `form.hidden` artık onu etkilemiyor.

**Şube Müdürü T.C. No bağlayamıyordu**: `IdentityRestController`'ın
izin kontrolü yalnızca `SecurityCapability::MANAGE_SECURITY_SETTINGS`
kabul ediyordu (yalnızca Genel Merkez/native administrator) - ama
"öğrenci + veli ekle" akışını asıl KULLANAN kişi genelde Şube Müdürü,
ki o yalnızca `scp_manage_students` taşıyor. Sonuç: Şube Müdürü T.C. No
girse bile arkaplandaki `POST security/users/{id}/tc-no` çağrısı sessizce
403 dönüyordu, T.C. No hiç kaydolmuyordu. Düzeltme: izin kontrolü artık
`MANAGE_SECURITY_SETTINGS` VEYA ham `'scp_manage_students'` capability
string'ini kabul ediyor (enum değil - `StudentCapability` import etmek
döngüsel bağımlılığı geri getirirdi). `WpdbIdentityGateway::link()`'in
`user_id` üzerindeki UNIQUE KEY'i zaten var olan bir kimliğin ÜZERİNE
yazılmasını engelliyor, bu yüzden genişletilmiş yetki yalnızca henüz
bağlanmamış bir hesaba T.C. No eklemeye izin veriyor, mevcut birini ele
geçirmeye değil.

**Öğrencinin kendi T.C. Kimlik No'su**: veli girişi için kullanılan T.C.
No'dan (Security'nin `scp_user_identities`, checksum doğrulamalı) TAMAMEN
AYRI, öğrencinin kendisi WP kullanıcısı olmadığı için `scp_students`
tablosuna eklenen, yalnızca bilgi amaçlı yeni bir `tc_no CHAR(11) NULL`
sütunu. `CreateStudentsTable::up()`'a eklendi - dbDelta zaten var olan
tabloya eksik sütunu kendiliğinden ekler (bkz. "Üçüncü kural"), ayrı bir
ALTER migration'a gerek yok. `Student` domain nesnesine, repository'ye ve
REST controller'a eklendi; biçim doğrulaması yalnızca 11 hane (tam
checksum değil - Security'nin `TcNumber`'ına bağımlı olamaz, bu alan zaten
kimlik doğrulaması için kullanılmıyor).

**Mağaza sayfası kendiliğinden onarımı**: WooCommerce'in kendi kurulumcusu
(`WC_Install::create_pages()`) "Mağaza" sayfasını yalnızca WooCommerce
PASİF'ten AKTİF'e geçtiğinde oluşturur - eklenti yeniden kurulduğunda
(bu platformda tekrar tekrar yaşanan bir senaryo) bu adım bir daha
çalışmaz. Sayfa silinmiş/kaybolmuşsa mağazanın render edecek hiçbir
şeyi kalmaz - hiçbir CSS düzeltmesi bunu çözemez. `inc/woocommerce.php`
artık her wp-admin yüklemesinde `wc_get_page_id('shop')`'u kontrol
ediyor, geçersizse WooCommerce'in KENDİ `wc_create_page()` yardımcısıyla
sayfayı yeniden oluşturuyor - MigrationRunner'ın "Üçüncü kural"ıyla
birebir aynı gerekçe.

### 25. Veli bağlantısı sessiz INSERT hatası, mağaza sayfasının gerçek entegrasyon hooks eksikliği

**`WpdbStudentParentRepository::link()`**, `WpdbIdentityGateway::link()`/
`WpdbBranchRepository::create()`/`update()`'te (bu oturumda daha önce)
düzeltilen AYNI hata sınıfını taşıyordu: `insert()`'in dönüş değeri hiç
kontrol edilmiyordu. `scp_student_parents`'a INSERT sessizce başarısız
olursa (eksik tablo, bozuk bir yinelenen satır, ...) hem manuel "Bağla"
akışı hem de öğrenci-oluştururken-otomatik-veli-bağlama akışı hiçbir hata
göstermeden "başarılı" görünüyordu - veli hesabı oluşuyordu ama
öğrenciyle bağlantısı hiç kaydolmuyordu. Düzeltme: `insert()`'in dönüşü
kontrol ediliyor, başarısızsa gerçek `$wpdb->last_error`'la
`RuntimeException` fırlatılıyor; `StudentsRestController::linkParent()`
(manuel bağlama uç noktası) de artık bunu yakalayıp mesajı JSON yanıtında
döndürüyor - önceden yakalanmayan bir exception, WordPress'in genel fatal
ekranına düşerdi.

**Mağaza sayfası hâlâ bozuk görünüyordu** (bölüm 23'ün `woocommerce.css`'i
tek başına yeterli değildi): ekran görüntüsü, ürün kartının üstüne binen
bir "Sepete Ekle" butonu, konteynırsız (tam genişlik) bir sayfa gövdesi ve
sayfanın altında temayla hiç ilgisi olmayan çıplak bir "Sayfalar/
Arşivler/Kategoriler" widget listesi gösteriyordu. Kök neden: temanın
`add_theme_support('woocommerce')` DEKLARE ETMESİ, WooCommerce'in gerçek
tema ENTEGRASYON hook'larını KULLANMASI anlamına gelmiyor - ikisi ayrı
şeyler. Üç somut düzeltme, WooCommerce'in kendi resmi tema geliştirme
kılavuzunun önerdiği yöntemle (şablon override değil, hook'lar):
1. `woocommerce_before_main_content`/`woocommerce_after_main_content`'e
   `<div class="scp-panel scp-shop-panel">`/`</div>` bağlandı - önceden
   mağaza içeriğini saran HİÇBİR konteynır yoktu, bu yüzden
   `assets/css/woocommerce.css`'teki hiçbir grid/kart kuralı gerçek bir
   genişlik sınırlamasına oturmuyordu.
2. `remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10)` -
   bu platformda hiç sidebar/widget alanı kavramı yok (temada tek bir
   `register_sidebar()` çağrısı bile yok), ama WooCommerce'in varsayılan
   şablonları yine de `woocommerce_sidebar` action'ını tetikliyordu;
   kaldırılmadan önce bu, sitenin durgun/aktif olmayan widget alanına
   atanmış rastgele varsayılan WordPress widget'larının (Sayfalar,
   Arşivler, Kategoriler) çıplak bir liste olarak dökülmesine yol
   açıyordu.
3. `add_filter('woocommerce_enqueue_styles', '__return_empty_array')` -
   WooCommerce kendi varsayılan stylesheet'lerini enqueue ediyordu, bu da
   `scp-woocommerce`'in kurallarıyla enqueue SIRASINA bağlı olarak
   çakışabiliyordu (eşit özgüllükte, sonra yüklenen kazanır). Artık bu
   temanın CSS'i, WooCommerce markup'ı için TEK stil kaynağı - WC'nin
   kendi stylesheet'i hiç yüklenmiyor.

### 26. "Çerez denetlenemedi" hatası ve paylaşılan `scpApiFetch()`

Bir admin panel ekranında (ör. Öğrenciler) ilk REST çağrısı bile
"Çerez denetlenemedi" hatasıyla başarısız oluyordu - bu, `scpPanelText`
içindeki hiçbir metinle eşleşmiyordu çünkü hiç BENİM metnim değildi:
WordPress'in kendi `rest_cookie_invalid_nonce` hatasının Türkçe çevirisi.
`X-WP-Nonce` sayfa YÜKLENDIĞI anda `wp_localize_script`'le HTML'e
gömülüyor; bir önbellekleme eklentisi bu sayfayı BAŞKA/DAHA ESKİ bir
oturumla önbelleğe aldıysa, ya da sekme nonce'un geçerlilik penceresi
kadar uzun süre açık kaldıysa, gömülü nonce artık geçerli oturumla
eşleşmez - JS içinde tekrar denemek bunu çözmez, yalnızca sayfanın
YENİDEN yüklenmesi (taze bir nonce gömülmesi) çözer.

Bu araştırma sırasında ayrı bir sorun da ortaya çıktı: 11 panel script'i
(`students-panel.js`, `branches-panel.js`, ...) birbirinin BİREBİR AYNISI
13 satırlık bir `apiFetch()` fonksiyonunu kopyalayıp duruyordu - PHP
modülleri arasında (composer bağımlılığı olmadan paylaşılamadıkları için)
kabul edilen bir kopyalama deseni, ama BURADA hiçbir modül sınırı yok,
hepsi aynı temanın parçası. Yeni `assets/js/scp-api-fetch.js`, TEK bir
`scpApiFetch()` global fonksiyonu tanımlıyor - `inc/assets.php`'te bir kez
enqueue ediliyor, her panel script'inin bağımlılığı olarak ekleniyor
(`wp_enqueue_script($handle, ..., ['scp-api-fetch'], ...)`), her panel
kendi kopyasını `var apiFetch = scpApiFetch;` ile değiştiriyor. Bu TEK
yerde, `rest_cookie_invalid_nonce` kodunu tanıyıp WordPress'in genel
mesajı yerine "Oturum bilgisi güncel değil, sayfayı yenileyin" gibi
eyleme geçirilebilir bir mesaj gösteriyor - artık 11 kopyaya ayrı ayrı
eklemek gerekmiyor.

### 27. "Veli eklendi ama görünmüyor" - mevcut hesaba bağlanma sessizdi

Kullanıcı, öğrenci formunda veli bilgisi girip kaydettikten sonra wp-admin →
Seviye Kullanıcılar → Veli listesinde YENİ bir kayıt göremediğini bildirdi.
Kök neden bir DB hatası değildi: `maybeCreateAndLinkParent()` (bkz.
`StudentsRestController`), girilen e-posta ADRESİYLE eşleşen bir WP
kullanıcısı zaten varsa (aynı velinin ikinci çocuğu eklenirken bilerek
tasarlanmış bir davranış) YENİ bir hesap OLUŞTURMAZ, öğrenciyi mevcut hesaba
bağlar - bu doğru davranış, çünkü aksi halde her çocuk için ayrı bir veli
hesabı türetilirdi. Ama bu "mevcut hesaba bağlandı" yolu ne bir hata
(`parent_error`) ne de kimlik bilgisi (`parent_credentials`) üretiyordu, bu
yüzden "Kayıt Özeti" kartı hiç açılmıyordu - başarılı bir bağlanma, arayüzde
"hiçbir şey olmadı"dan AYIRT EDİLEMİYORDU.

Düzeltme, `maybeCreateAndLinkParent()`'ın dönüş değerine üçüncü bir alan
ekledi: `linked_existing` (`?array{name: string, email: string}`), yalnızca
e-posta MEVCUT bir kullanıcıyla eşleştiğinde doldurulur. `store()` bunu
`parent_linked_existing` olarak yanıta ekliyor; `students-panel.js` artık bu
alanı da okuyup (yeni `credentials` yoksa) Kayıt Özeti kartını YİNE açıyor -
şifre/T.C. No satırları olmadan, yerine "bu e-posta zaten kayıtlı bir veli
hesabına ait, öğrenci mevcut hesaba bağlandı" notuyla. Böylece her iki yol da
(yeni hesap / mevcut hesaba bağlanma) kullanıcıya görünür bir onay üretiyor.

### 28. Statik `SCP_THEME_VERSION` her güncellemeyi tarayıcı önbelleğine gömüyordu

"Yeni paketi yükledim, hâlâ eski davranış" tarzı raporların (bu bölümdeki
27. madde dahil, muhtemelen daha önceki turlardaki bazı "düzelttim ama
görünmüyor" tekrarlarının da gerçek kök nedeni) altında yatan platform
genelinde bir sorun bulundu: `functions.php`'deki `SCP_THEME_VERSION`
sabiti, temanın İLK commit'inden beri hep `'0.1.0'` - hiç değişmemiş. Her
`wp_enqueue_script()`/`wp_enqueue_style()` çağrısı bu SABİT değeri
versiyon parametresi olarak kullanıyordu, yani `students-panel.js?ver=0.1.0`
gibi bir URL, dosyanın içeriği KAÇ KEZ güncellenmiş olursa olsun HER
zaman AYNI kalıyordu - tarayıcı (ve önündeki herhangi bir sayfa
önbellekleme katmanı), dosyanın değiştiğini anlayabileceği HİÇBİR sinyal
almıyordu. Sunucudaki dosya gerçekten güncellenmiş olsa bile, kullanıcının
tarayıcısı eskisini önbellekten sunmaya devam edebiliyordu.

Düzeltme: `functions.php`'ye `scp_asset_version(string $relativePath): string`
eklendi - ilgili dosyanın `filemtime()`'ını (değişmemişse `SCP_THEME_VERSION`'a
düşerek) döndürüyor. `inc/assets.php` ve `inc/plugin-installer.php`'teki
YİRMİ enqueue çağrısının tamamı artık bare `SCP_THEME_VERSION` yerine
`scp_asset_version('/assets/js/...')` kullanıyor - versiyon artık dosya
her değiştiğinde OTOMATİK değişiyor, manuel bir sürüm numarası bakımı
gerekmiyor ve bir daha asla statik kalıp bayatlayamıyor.

### 29. Öğrenci silme, veli düzenleme, Sepetim sayfası

Üç eksik özellik tamamlandı:

**Öğrenci sil.** `StudentRepositoryInterface`'e `delete()` eklendi;
`WpdbStudentRepository::delete()` ham bir `DELETE` sorgusu çalıştırıyor.
`scp_student_parents` ve `scp_price_rules` tabloları `student_id` üzerinde
`ON DELETE CASCADE` tanımlıyor, o yüzden bağlı veli-bağlantıları ve fiyat
kuralları otomatik temizleniyor - ama Seviye Commerce'in
`scp_order_line_items` tablosu KASITLI OLARAK cascade yapmıyor (sipariş
geçmişi olan bir öğrencinin sessizce silinebilmesi istenmiyor); bu FK
ihlali `stripos($error, 'foreign key constraint')` ile yakalanıp
"Bu öğrenciye ait sipariş kayıtları olduğu için silinemiyor." gibi
anlaşılır bir mesaja çevriliyor. REST: `DELETE /students/{id}`,
`canAccessStudent` (mevcut GET/PUT ile aynı) permission_callback'i
kullanıyor - Şube Müdürü yalnızca kendi şubesindeki öğrencileri silebiliyor.
`students-panel.js`'te her satıra "Kaldır" butonu eklendi (window.confirm
ile onay isteniyor).

**Veli düzenle.** Daha önce bir Şube Müdürü/Genel Merkez, Öğrenciler
panelinden bağlı bir velinin ad/e-postasını göremiyor veya değiştiremiyordu
(tek yol wp-admin → Seviye Kullanıcılar, admin-only). `listParents()`
artık ham `parent_user_id` yerine `{id, name, email}` döndürüyor
(`get_userdata()` - çekirdek WordPress fonksiyonu, Security'ye bağımlılık
gerektirmiyor). Yeni `PUT /students/{id}/parents/{parent_user_id}`
`display_name`/`email` günceller (`wp_update_user()`). ÖNEMLİ güvenlik
notu: `canAccessStudent` yalnızca URL'deki öğrenci id'sinin çağıranın
kapsamında (şubesinde) olduğunu doğruluyor, `parent_user_id`'nin O
öğrenciyle GERÇEKTEN bağlı olduğunu DEĞİL - bu kontrol olmadan bir Şube
Müdürü, kendi şubesinden geçerli bir öğrenci id'si + kapsamı dışındaki
rastgele bir kullanıcı id'si vererek o kullanıcının hesabını
değiştirebilirdi (IDOR). Bu yüzden `updateParent()` içinde AYRICA
`parentUserIdsForStudent()` ile "bu veli gerçekten bu öğrenciyle bağlı mı"
kontrolü var. `students-panel.js`'teki veli listesi artık ad/e-posta
gösteriyor, "Düzenle" satırı inline bir ad/e-posta formuna dönüşüyor.

**Sepetim sayfası.** `scp_ensure_shop_page_exists()`'in aynısı `cart`
için: `scp_ensure_cart_page_exists()`, `wc_get_page_id('cart')` yoksa/
yayınlanmamışsa `wc_create_page(..., 'woocommerce_cart_page_id', 'Sepetim',
'[woocommerce_cart]')` ile oluşturuyor. Mağaza sayfasından farklı olarak
cart WC'nin özel "ürün arşivi" sayfa tipi değil, gerçek sayfa İÇERİĞİNE
(shortcode) ihtiyaç duyuyor - modern Cart bloğu yerine kasıtlı olarak
`[woocommerce_cart]` shortcode'u kullanıldı (WC sürümleri arasında en
geniş uyumluluk için). `header.php`'nin nav'ına "Mağaza" linkinin yanına
`wc_get_cart_url()` ile "Sepetim" linki eklendi (aynı `scp_view_own_children`
kapasitesiyle kapılı).

### 30. Sepetim sayfası "var" ama her zaman veli panosunu gösteriyordu

"Sepet sayfası olmasına rağmen sepet sayfasına geçiş yapmıyor, ana sayfaya
yönleniyor" raporu bir HTTP yönlendirmesi DEĞİLDİ (RoleRouter/access-gate
sağlamdı, veli için `/sepetim` gibi bir yol zaten `/admin`/`/sube` dışında
her şeyle aynı "parent" bölgesine giriyor, engellenmiyor) - asıl sorun
`index.php`'nin ÇALIŞMA ŞEKLİYDİ. Temada `page.php` yok, bu yüzden gerçek
bir WP Page (Sepetim sayfası dahil - `[woocommerce_cart]` shortcode'lu sıradan
bir Page) WordPress'in şablon hiyerarşisinde `index.php`'ye düşüyor -
`index.php` da `scp_view_own_children` yetkisi olan HERKES için (yani her
veli) HANGİ SAYFADA olunduğuna bakmaksızın koşulsuzca
`templates/parent-dashboard.php`'yi basıyordu. Mağaza/ürün sayfaları
etkilenmiyordu çünkü WooCommerce onlar için kendi (daha spesifik) şablon
öncelikli dosyalarını kullanıyor - ama sepet sadece düz bir Page olduğu
için bu özel yolu yoktu, hep dashboard'a düşüyordu. Sepetim sayfasının
kendisi gerçekten VARDI (`wc_get_page_id('cart')` doğru dönüyordu, link
URL'i doğruydu) ama İÇERİĞİ hiçbir zaman render edilmiyordu.

Düzeltme: `index.php`'deki dashboard dalına `!is_page()` şartı eklendi -
artık dashboard yalnızca statik bir front page tanımlı olmadığı için
köke ('/') düşen blog-index fallback'inde gösteriliyor, gerçek bir WP
Page'e (Sepetim dahil, ileride eklenecek her Page için de) gelindiğinde
`the_content()` kendi içeriğini basıyor.

### 31. Ürün kataloğu (şube bazlı aktif/pasif) + logo yükleme

İki yeni özellik: (1) şubelerin ürün oluşturup ortak bir katalogda
yönetebilmesi, her şubenin bir ürünü kendi öğrenci/velisi için ayrı ayrı
aktif/pasif yapabilmesi, ve (2) wp-admin'e girmeden yüklenebilen bir
platform logosu.

**Ürün kataloğu.** Ürünlerin kendisi tamamen WooCommerce'in
(`WC_Product`/`wp_posts`) - bu platform hiçbir zaman kendi paralel ürün
tablosunu tutmuyor (bkz. "order/cart storage stays WooCommerce's" kuralı,
CommerceModule'ün kendi docblock'u). Yeni `Seviye\Commerce\Http\
ProductsRestController` (`seviye/v1/commerce/products/*`), Şube
Müdürü/Genel Merkez'in WordPress'in doğal `edit_products`/`publish_products`
yetkilerine sahip OLMADAN (özel roller sıfır yetkiyle başlar) bu ortak
kataloğu yönetebilmesini sağlayan ince bir sarmalayıcı. Yetki modeli
Pricing'in GENEL/ŞUBE ayrımını birebir yansıtıyor: tam düzenleme/silme
yalnızca Genel Merkez/Bölge Müdürü'nde (`canManageProductFully()`,
`currentUserBranchId() === null` kontrolü); bir Şube Müdürü YALNIZCA ortak
kataloğa yeni ürün oluşturabilir ve KENDİ şubesi için aktif/pasif
değiştirebilir - başka bir şubenin ürününü düzenleyemez/silemez.

Şube bazlı görünürlük YENİ bir tablo: `scp_product_branches`
(`Seviye\Commerce\Database\Migrations\CreateProductBranchesTable`) -
`product_id` (WooCommerce'e işaret ediyor, FK yok - bu platform WordPress
çekirdek tablolarına asla FK koymuyor), `branch_id` (FK, `scp_branches`'e
`ON DELETE CASCADE`), `status` ('active'/'passive'). KASITLI OLARAK
"opt-out" modeli: bir satırın YOKLUĞU "aktif" anlamına gelir (bkz.
`WpdbProductBranchVisibilityRepository::isActiveForBranch()`) - yeni
oluşturulan bir ürün varsayılan olarak TÜM şubelerde aktiftir, bir şube
yalnızca onu GİZLEMEK istediğinde bir satır oluşturur. Bu, "her ürünü her
şube için tek tek açmak" gibi bir yönetim yükü yaratmıyor.

Mağaza tarafında yeni `Seviye\Commerce\Http\ProductVisibilityHooks`,
`woocommerce_product_is_visible` (mağaza/arama listelemesi) ve
`woocommerce_add_to_cart_validation` (URL'i doğrudan bilen birinin
sepete ekleyememesi için) filtrelerine kancalanıyor: bir veli, ürünü
GÖRDÜĞÜ/satın alabildiği her sayfada, kendi çocuklarının şubelerinden EN AZ
BİRİNDE aktifse görür - `CartPricingService`'in zaten kullandığı "herhangi
bir çocuğun şubesi" mantığıyla aynı. Bu kontrol için Students'tan YENİ bir
Contract yayınlandı: `ParentBranchLookupInterface::branchIdsForParent()`
(`WpdbParentBranchLookup` - `scp_student_parents` ⨝ `scp_students` üzerinden
tek sorgu). `scp_manage_products` yetkisi olan (yönetim panelini
kullanabilen) hiç kimse için bu filtre uygulanmıyor - kendi yönetmekle
sorumlu olduğu ürünü kendine gizlemek anlamsız olurdu.

**Logo yükleme.** Yeni `Seviye\Core\Http\BrandingRestController`
(`seviye/v1/core/branding`, Genel Merkez only - `Capability::
MANAGE_CORE_SETTINGS`), logoyu `scp_settings` üzerinde tek bir alan
(`branding_logo_attachment_id`, mevcut bir WordPress medya eki) olarak
saklıyor - dosyanın kendisi WordPress'in kendi `/wp/v2/media` REST uç
noktasından yükleniyor, burada yeniden icat edilmiyor. Bu, YENİ bir JS
yardımcı fonksiyonu gerektirdi: `scpUploadMedia()` (`assets/js/
scp-api-fetch.js`) - `scpApiFetch()`'in aksine Content-Type header'ını
ZORLA `application/json` yapmıyor (multipart/form-data yüklemesi
tarayıcının kendi sınır (boundary) değerini ayarlamasını gerektirir).
Genel Merkez'in (ve ürün fotoğrafı yükleyebilmesi için Şube Müdürü'nün de)
çekirdek WordPress `upload_files` yetkisine ihtiyacı var - özel roller
sıfır yetkiyle başladığı için `CoreServiceProvider`/`CommerceModule`
`RbacManager::grantCapability()` ile bunu açıkça veriyor.

Logo, `header.php` (her sayfanın üstü) ve `templates/login.php` (giriş
ekranı) içindeki "S" harf-rozeti yerine gösteriliyor - hiç logo
yüklenmemişse ikisi de eskisi gibi harf-rozetine düşüyor
(`scp_logo_url()`, `inc/branding.php`).

### 32. Veli ana sayfası → Mağaza, ayrı "Profilim" sayfası, sepet sayacı, ürün ID görünürlüğü, taban fiyat kuralı, header logo düzeltmesi

Altı ayrı iyileştirme:

**Veli ana sayfası artık doğrudan Mağaza'yı açıyor.** '/' kökü daha önce
`index.php` üzerinden "Öğrencilerim/Profilim/Hesap Güvenliği" panosunu
gösteriyordu; artık `wc_get_page_permalink('shop')`'a yönlendiriyor
(`get_header()`'dan ÖNCE - herhangi bir çıktı üretildikten sonra
`wp_safe_redirect()` başarısız olur). O pano içeriği yeni bir sayfaya
taşındı: `/profilim` (`inc/zones.php`'ye yeni bir zone eklendi -
`scp_zone=profilim`, admin/sube gibi ayrı bir rewrite kuralı, ama
`zone.php` yerine doğrudan `templates/parent-dashboard.php`'yi render
ediyor). Buraya ulaşmak zaten `RoleRouter`'ın "parent" bölgesine (her
niyet/amaçla '/admin' ve '/sube' DIŞINDAKİ her yol) girdiği için ayrı bir
yetki kontrolüne gerek yok - `inc/access-gate.php` zaten yalnızca veli
rolündeki kullanıcıların buraya ulaşmasını sağlıyor. `header.php`'ye yeni
bir "Profilim" bağlantısı eklendi.

**Sepet sayacı.** `header.php`'deki "Sepetim" linkine
`WC()->cart->get_cart_contents_count()` ile hesaplanan bir rozet
eklendi (`.scp-cart-count`) - sunucu tarafında her sayfa yüklemesinde
render ediliyor (bu platformda sepete ekleme zaten tam sayfa POST/reload,
AJAX değil - WC'nin fragment mekanizmasına gerek yok).

**Ürün ID görünürlüğü.** Yeni bir salt-okunur yetki:
`ProductCapability::VIEW_PRODUCTS` - Muhasebe, Depo ve Sistem'e verildi
(Genel Merkez/Bölge Müdürü/Şube Müdürü zaten MANAGE_PRODUCTS ile bunun
üst kümesine sahip). `ProductsRestController::index()`/`show()` artık
`canViewProducts()` (MANAGE_PRODUCTS VEYA VIEW_PRODUCTS) ile korunuyor.
Ürünler tablosuna bir "ID" sütunu eklendi; `zone.php`/`products-panel.js`
salt-okunur roller için oluşturma formunu, düzenle/sil/durum sütunlarını
DOM'a hiç BASMIYOR (yalnızca CSS ile gizlemek yerine) - yeni bir
`scpPanel.canManageProducts` bayrağı bu ayrımı sürüyor.

Bu, **Sistem** rolünün ilk kez gerçek bir yetki kazandığı an oldu - önceden
`RoleDefinitions::defaults()`'ta sıfır yetkiyle tanımlıydı VE
`RoleRouter::zoneFor()`'da hiçbir zon eşlemesi yoktu (varsayılan "parent"
bölgesine düşüyordu, yani `/admin`'e asla erişemiyordu). `RoleRouter`
artık Sistem'i Genel Merkez/Bölge Müdürü ile birlikte "admin" bölgesine
eşliyor - bu, Sistem'e Genel Merkez'in yetkilerini VERMİYOR (RBAC
capability grant'leri tamamen ayrı bir mekanizma), yalnızca Sistem'e VERİLEN
capability'lerin (VIEW_PRODUCTS, MANAGE_BASE_PRICING gibi) render edileceği
bir zona erişim sağlıyor - aksi halde bu yetkiler hiçbir zaman UI'da
kullanılamazdı.

**Taban fiyat kuralı.** "Genel merkezin belirlediği fiyatın aşağısına fiyat
verilemez" - yeni `PricingCapability::MANAGE_BASE_PRICING` (Genel Merkez +
Sistem only, Bölge Müdürü DAHİL DEĞİL - `MANAGE_PRICING`'den daha dar).
`PricingRestController::canWriteScope()` artık GENEL kapsamı özel olarak bu
capability'ye bağlıyor (önceden yalnızca "şube üyeliği yok" kontrolüydü, bu
da Bölge Müdürü'nü de kapsıyordu). Yeni `violatesBasePriceFloor()`: bir
BRANCH/STUDENT kuralı OLUŞTURULURKEN/GÜNCELLENIRKEN, o ürünün aktif GENEL
kuralı varsa VE yeni fiyat bunun altındaysa 422 döner - kontrol ÜRÜN
BAZINDA yapılıyor (platform genelinde tek bir taban değil), ve yalnızca bir
GENEL kural gerçekten VARSA uygulanıyor. `pricing-panel.js`'teki "Genel"
kapsam seçeneği artık `scpPanel.canManageBasePricing`'e göre gizleniyor
(önceden `canManageAllBranches` kullanıyordu, ki bu Bölge Müdürü'nü de
içeriyordu).

**Header logo düzeltmesi.** `.scp-site-header__logo` sabit bir `height: 34px`
İLE `max-width: 160px`'i birlikte kullanıyordu - geniş bir logo için
max-width devreye girip genişliği küçültürken yükseklik 34px'te sabit
kalıyordu, görseli bozuyordu ("yarım görünüyor"). `max-height` (sabit
`height` değil) kullanan `.scp-auth-logo` (giriş ekranı) zaten bu sorunu
yaşamıyordu - aynı düzeltme header'a da uygulandı, artık `width: auto` ile
görselin gerçek en-boy oranı hiçbir ikinci kısıtlamayla çakışmıyor. Boyut
artık `--scp-header-height` (yeni bir CSS custom property, hem
`.scp-site-header`'ın `min-height`'ı hem de logonun `max-height`'ı bunu
paylaşıyor) TEK bir kaynaktan türetiliyor - kullanıcının isteği doğrultusunda
"header'ın yükseklik oranına göre" boyutlanıyor.

### 33. "Siparişlerim" sayfası - velinin geçmiş sipariş geçmişi

Yeni `/siparislerim` bölgesi (`inc/zones.php`'ye `/profilim` ile aynı
desende bir rewrite kuralı + `scp_render_zone_template()`'e bir dal
eklendi - `zone.php`'yi değil doğrudan `templates/orders.php`'yi render
ediyor). Buraya ulaşmak zaten `/profilim` ile aynı gerekçeyle ayrı bir
yetki kontrolü gerektirmiyor: `RoleRouter`'da bu yol için özel bir kural
yok, dolayısıyla her veli için geçerli olan aynı "parent" bölgesine düşüyor
ve `inc/access-gate.php` zaten yalnızca giriş yapmış kullanıcıların buraya
ulaşmasını sağlıyor. `header.php`'ye "Profilim"den ÖNCE yeni bir
"Siparişlerim" bağlantısı eklendi.

**Yeni `OrdersRestController`** (`seviye/v1/commerce/orders/mine`),
`NotificationsRestController`'ın self-service `/mine` desenini birebir
izliyor: `is_user_logged_in()` dışında hiçbir RBAC capability'si yok, çünkü
bu bir yetki-kapsamlı kaynak değil - her veli yalnızca KENDİ geçmişini
okuyor. Kapsam sunucu tarafında `wc_get_orders(['customer_id' =>
get_current_user_id()])` ile zorlanıyor, hiçbir zaman istemcinin verdiği
bir id ile değil - platformdaki her `*/mine` endpoint'inin izlediği aynı
"sunucu kapsamı çözer" kuralı.

Sipariş/kalem verisi doğrudan WooCommerce'in kendi `WC_Order` API'sinden
okunuyor - Seviye Commerce'in kendi `scp_order_line_items` tablosundan
DEĞİL. O tablo hakediş/admin raporlaması için şube/tarih aralığına göre
kapsamlanmış durumda; "bir müşterinin kendi sipariş geçmişi" için
tasarlanmamış. WC'den yeniden türetmek bu endpoint'i kendi başına yeterli
(self-contained) tutuyor. Her sipariş kaleminin `_scp_student_id` meta'sı
(`WooCommerceCartHooks::persistStudentId()` tarafından sepete-ekleme
anında yazılıyor) `StudentLookupInterface::find()` ile bir isme çözülüyor,
böylece her satın alınan ürünün hangi öğrenci için alındığı gösteriliyor.

Tema tarafı salt-okunur: `orders-panel.js` hiçbir form içermiyor, yalnızca
sipariş kartlarını (durum rozeti, tarih, ödeme yöntemi, ara toplam/KDV/
genel toplam) ve her sipariş için bir kalem tablosunu render ediyor -
mevcut `.scp-card--nested`, `.scp-summary-list`, `.scp-table` bileşenleri
yeniden kullanıldı, yeni CSS eklenmedi.

### 34. Sipariş Yönetimi (admin) ve Aktivite Günlüğü

İki ayrı yeni menü:

**Sipariş Yönetimi.** "Genel merkez hesabından tüm siparişleri, şube ise
kendi velilerin siparişlerini görecek bir menü, tüm filtreleme sistemleri
olsun." Yeni `OrderCapability::VIEW_ORDERS` (Genel Merkez/Bölge Müdürü -
her şube) / `VIEW_OWN_BRANCH_ORDERS` (Şube Müdürü - yalnızca kendi şubesi),
`ReportCapability::VIEW_REPORTS`/`VIEW_OWN_REPORTS` ile birebir aynı
desende. Yeni `AdminOrdersRestController` (`GET /commerce/orders`),
"hangi siparişler eşleşiyor" sorusunu `wc_get_orders()` yerine ZATEN
yayınlanmış `OrderLineItemQueryInterface` (`scp_order_line_items` -
`OrderPersistenceHooks::persistOrderLineItems()` sayesinde ödeme durumu ne
olursa olsun HER sipariş ödeme adımında bu tabloya yazılıyor) üzerinden
çözüyor - bu tablo zaten branch_id/product_id/status alanlarını taşıyor,
dolayısıyla platformun branch/ürün/tarih/durum filtrelemesinin
gerçekleştirilebileceği tek yer. Şube Müdürü, eşleşen bir siparişin
İÇİNDE yalnızca KENDİ şubesine ait kalemleri görür (başka bir şubeyle
paylaşılan nadir bir sipariş olsa bile) - bu görünürlük HER ZAMAN yalnızca
şube'ye göre belirlenir, ürün/tarih/durum/öğrenci filtreleri yalnızca
HANGİ siparişlerin listede göründüğünü daraltır, eşleşen bir siparişin
İÇİNDEKİ görünürlüğü değiştirmez (bkz. `visibleItemIdsByOrder()`). Genel
Merkez her kalemi eksiksiz görür.

`OrdersRestController`'ın (velinin kendi `/mine` sayfası) sipariş/kalem
serileştirme mantığı yeni bir `Http\Support\OrderPresenter` sınıfına
taşındı - iki controller de aynı tarih/para biçimlendirmesine ve
`_scp_student_id` -> `StudentLookupInterface` çözümlemesine ihtiyaç
duyuyor; yalnızca görünür kalem kümesi (`$visibleItemIds`) ve alıcının
(veli) kimliğinin eklenip eklenmeyeceği (`$includeCustomer`) farklı.

**Aktivite Günlüğü.** "Genel merkez hesabından tüm yapılan aktiviteleri de
gösterecek başka bir menü." Platformun kuruluşundan beri var olan ama HİÇ
kullanılmamış `Capability::VIEW_AUDIT_LOGS` (yalnızca Genel Merkez'e
varsayılan olarak atanmış, bkz. `RoleDefinitions::defaults()`) ve
`scp_logs` tablosu (`DatabaseLogger`, "KVKK/güvenlik denetimi gereksinimi"
notuyla en baştan kurulmuştu) nihayet bir REST uç noktası ve panel
kazandı - ikisi de mevcuttu, aralarında yalnızca bağlantı eksikti.

Kapsamı platform genelinde GERÇEKTEN eksiksiz yapan asıl parça yeni
`Logging\RequestActivityLogger`: her modülün her REST controller'ına ayrı
ayrı bir logger çağrısı eklemek yerine, WordPress'in REST çekirdek
filtresi `rest_request_after_callbacks`'e (her route handler'ı
ÇALIŞTIKTAN SONRA, cevapla birlikte tetiklenir) TEK bir kancayla bağlanıyor
- `seviye/v1` altındaki her POST/PUT/PATCH/DELETE isteğini otomatik
kaydediyor. Bu, herhangi bir modülün gelecekte ekleyeceği YENİ bir uç
noktanın da otomatik olarak günlüğe girmesi anlamına geliyor - hiçbir
controller'ın bunu "hatırlaması" gerekmiyor. `GET` istekleri hiç
kaydedilmiyor (okuma "aktivite" sayılmıyor); `/auth/*` tamamen hariç
tutuluyor (ham şifre taşıyan, `AuthService`'in zaten kendi anlamlı
mesajlarıyla kaydettiği giriş akışı - burada da kaydetmek hem
tekrar hem de bir redaksiyon hatası riski olurdu). Diğer her rotanın
parametreleri `password`/`code`/`secret`/`token` gibi alanlar
`[redacted]` ile değiştirilerek context olarak saklanıyor.

Yeni `Logging\LogFilter`/`LogEntry`/`WpdbLogQuery` (Commerce'in
`OrderLineItemFilter`/`OrderLineItemRecord`/`WpdbOrderLineItemQuery`
üçlüsüyle birebir aynı desen) `scp_logs`'u kanal/seviye/kullanıcı/tarih/
serbest metin ile filtreliyor; yeni `Http\ActivityLogRestController`
(`GET /core/activity-log`) bunu `VIEW_AUDIT_LOGS` arkasında sunuyor. Tema
tarafında `activity-log-panel.js`, ham "METHOD /seviye/v1/rota" mesajlarını
(ve `AuthService`'in İngilizce "Login succeeded."/"Login failed." gibi
sabit mesajlarını) küçük bir eşleme tablosuyla kısa Türkçe açıklamalara
çeviriyor - eşleşmeyen bir rota gizlenmiyor, yalnızca ham haliyle
gösteriliyor, bu yüzden tablo hiçbir zaman güncel tutulmak ZORUNDA değil.

### 35. Sipariş Yönetimi bağımsız bir sayfaya taşındı

"Arama ile değil direkt siparişleri ayrı bir sayfa olarak göster" - bölüm
34'te Sipariş Yönetimi, `/admin` ve `/sube` panolarının içinde diğer
bölümlerle birlikte yüklenen, quicknav'daki bir sayfa-içi çapaya
(`#scp-admin-orders-panel`) tıklanarak ulaşılan bir SECTION olarak
tasarlanmıştı. Artık kendi URL'sine sahip bağımsız bir sayfa:
`/admin/siparisler` (Genel Merkez/Bölge Müdürü/Sistem) ve `/sube/siparisler`
(Şube Müdürü/Muhasebe/Depo/Satış Danışmanı/Rehberlik).

Bunun için yeni bir üst düzey rewrite kuralı EKLENMEDİ - `inc/zones.php`
`^admin/(.+)/?$` ve `^sube/(.+)/?$` kalıplarını (scp_zone_path'e yakalayan)
zaten en başından beri kayıtlı tutuyordu ama hiç kullanmıyordu; bu bölüm
onu ilk kez gerçek bir amaç için devreye sokuyor.
`/siparislerim`/`/profilim`'in aksine (bkz. bölüm 29/32 - bunlar üst düzey,
`/admin` veya `/sube` ÖNEKİ OLMAYAN yollardır) bilinçli bir tasarım kararı
gerekiyordu: `Seviye\Security\Routing\RoleRouter::isPathAllowedForRoles()`
bir Genel Merkez/Bölge Müdürü/Sistem kullanıcısının izinli bölgesini
YALNIZCA `/admin` ile başlayan yollara, bir Şube Müdürü/Muhasebe/Depo/Satış
Danışmanı/Rehberlik kullanıcısınınkini YALNIZCA `/sube` ile başlayan
yollara bağlıyor - üst düzey (`/siparisler` gibi) bir yol her iki grup için
de "parent" bölgesiyle eşleşmediği için `inc/access-gate.php` tarafından
geri yönlendirilirdi. Bu yüzden aynı sayfa iki farklı önekle (`/admin/...`,
`/sube/...`) sunuluyor; yeni `scp_admin_orders_path()` yardımcı fonksiyonu
(`inc/zones.php`) geçerli bölgeye göre doğru olanı üretiyor, quicknav'da ve
yeni `templates/orders-admin.php`'nin "Panele Dön" bağlantısında kullanılıyor.

`inc/zones.php`'nin `scp_render_zone_template()`'i artık `admin`/`sube`
bölgelerinde `scp_zone_path === 'siparisler'` olduğunda (trailing-slash
varyantı için `rtrim()` ile normalize edilmiş) `templates/zone.php` yerine
doğrudan `templates/orders-admin.php`'yi render ediyor - `zone.php`'nin
kendi bölüm/quicknav yapısını hiç görmüyor, tıpkı `/profilim`/`/siparislerim`
gibi. Yetki kontrolü burada AYRICA yapılıyor (`scp_view_orders`/
`scp_view_own_branch_orders`) çünkü `templates/zone.php`'nin aksine bu artık
o dosyanın PHP `if` bloklarından birine değil, kendi render dalına bağlı.

`inc/assets.php`'deki `admin-orders-panel.js` enqueue koşulu da aynı şekilde
değişti: artık `$zone` yalnızca admin/sube olması yetmiyor, `scp_zone_path`
da `'siparisler'` olmalı - aksi halde script hem eski (artık var olmayan)
hem de yeni sayfada gereksiz yere yüklenirdi. Panelin kendi DOM
kimlikleri/JS'i (`admin-orders-panel.js`) değişmedi - yalnızca nereye
render edildiği değişti, `templates/orders-admin.php` aynı
`id="scp-admin-orders-panel"`/`data-scp-admin-orders-*` yapısını koruyor.

Ayrıca quicknav'ın href'leri artık iki türlü olabildiği için (sayfa-içi
çapa VEYA gerçek bir URL - yalnızca "Siparişler" girdisi) `templates/zone.php`
`esc_attr()` yerine `esc_url()` kullanacak şekilde düzeltildi - `esc_attr()`
bir URL'yi doğru kaçırmak için doğru fonksiyon değildi, yalnızca `#anchor`
değerleriyle çalıştığı için önceden fark edilmiyordu.

### 36. Sipariş geçmişi kök nedeni, sipariş e-postası (Gmail SMTP) ve Profilim'in tam yeniden inşası

Üç ayrı iyileştirme:

**"Velilerden gelen siparişleri geçmişe dönük olarak göremiyorum."**
`AdminOrdersRestController` bölüm 34'te `scp_order_line_items` tablosu
üzerinden (`OrderLineItemQueryInterface`) filtreleniyordu - bu tablo yalnızca
`OrderPersistenceHooks`'un checkout anında başarıyla yazdığı satırları
içeriyor; bir yazma başarısız olursa, öğrenci/şube artık çözümlenemiyorsa
veya kanca hiç tetiklenmediyse gerçek, ödemesi yapılmış bir sipariş HQ/Şube
Müdürü'nden SESSİZCE gizleniyordu. Kök neden düzeltmesi: controller artık
`OrdersRestController`'ın (velinin kendi `/mine` sayfası, ki bu ZATEN
güvenilir şekilde çalışıyordu) izlediği aynı yolu izliyor -
`wc_get_orders()`'ı DOĞRUDAN okuyor, her kalemin `_scp_student_id` meta'sını
`StudentLookupInterface` ile çözüyor, ikincil bir önbellek tablosuna hiç
güvenmiyor. WooCommerce'in kendisinin bildiği HER sipariş artık görünür.
Şube kapsamı ve görünür kalem kümesi mantığı (bölüm 34'te açıklanan "eşleşen
siparişin İÇİNDEKİ görünürlük yalnızca şubeye göre belirlenir" kuralı)
davranış olarak AYNI kaldı, yalnızca veri kaynağı değişti.

**Sipariş e-postası (Gmail SMTP).** "Veliler sipariş verdiğinde otomatik
olarak velilerin mailine mail gidecek bir sistem." `OrderPersistenceHooks::
persistOrderLineItems()` artık, en az bir kalem başarıyla kaydedildiyse,
sipariş başına BİR KEZ (hakediş olaylarının aksine kalem başına değil)
`commerce.order_placed` olayını ateşliyor - payload kendi kendine yeterli
(sipariş no, toplam, kalemler), Notifications'ın WC_Order'a hiç dokunmasına
gerek kalmıyor (Finance'in `hakedisPayload()`'ı için zaten geçerli olan aynı
kural). Yeni `Notifications\Support\OrderPlacedNotificationListener`
(`PasswordResetNotificationListener`'ı birebir taklit ediyor) bu olayı
dinleyip veliye (`customer_id`) bir e-posta bildirimi gönderiyor - alıcı
adresi, her zamanki gibi `get_userdata()` üzerinden otomatik çözülüyor,
Commerce'e özel bir arama gerekmiyor.

"Google maili özelinde göndereceğiz" - yeni `Channel\GmailSmtpConfigurator`,
WordPress'in `phpmailer_init` çekirdek kancasına bağlanarak HER `wp_mail()`
çağrısını (yalnızca sipariş e-postalarını değil, WordPress'in kendi
e-postalarını da) Gmail'in SMTP sunucusu üzerinden göndermeye zorluyor -
kimlik bilgileri (Gmail adresi + Google'ın SMTP için istediği "Uygulama
Şifresi", normal hesap şifresi DEĞİL) `seviye/v1/notifications/email-settings`
üzerinden yapılandırılıyor (Genel Merkez only), NetGSM SMS ayarlarıyla
BİREBİR aynı "Settings-backed, boşsa devre dışı" deseni izliyor - ayarlar
girilmemişse `configure()` hiçbir şey yapmıyor, wp_mail() varsayılan
taşıyıcısında kalıyor.

**Profilim'in tam yeniden inşası.** Dört alt-madde:

- `ParentProfileRestController` artık `username` (wp_users.user_login,
  YALNIZCA okunur - "Kullanıcı adı kısmı veliler tarafından
  değiştirilmesin") ve `email` (wp_users.user_email, yazılabilir - native WP
  alanı olduğu için `wp_update_user()` ile güncelleniyor, `scp_parent_profiles`'ta
  ayrı bir sütun YOK) döndürüyor/güncelliyor. `phone` artık
  `normalizeTurkishMobile()` ile doğrulanıyor - "telefon numarası bölümünde
  türkiye özelinde olacak": 0/+90/90/0090 önekleri temizlenip tam olarak
  5 ile başlayan 10 haneli bir numara bekleniyor, aksi halde 422. Normalize
  edilmiş biçim (10 hane, başında 0 yok) zaten
  `NetgsmSmsChannel::normalizePhone()`'un okurken beklediği biçimle eşleşiyor.

- Yeni `Seviye\Security\Http\AccountRestController`
  (`PUT /security/password`) - "Şifre değiştirme de olsun". Mevcut şifreyi
  `CredentialGatewayInterface::verifyPassword()` ile doğruluyor (aynı
  `TwoFactorRestController::disable()`'ın "hâlâ sen olduğunu kanıtla" barı),
  ardından `wp_set_password()` çağırıyor. `wp_set_password()` o kullanıcının
  HER oturum jetonunu siler - şu anki isteğin kimliğini doğrulayan jeton da
  dahil - bu yüzden hemen ardından `wp_set_auth_cookie()` ile yeniden
  oturum açılıyor, aksi halde veli kendi şifresini değiştirdiği anda
  oturumu sessizce sonlanırdı.

- Yeni `Seviye\Commerce\Http\CustomerAddressRestController`
  (`GET/PUT /commerce/customer/me/addresses`) - "Gönderim adresi ve fatura
  adresi bölümü de olsun". Yeni bir Seviye tablosu YERİNE doğrudan
  WooCommerce'in kendi `WC_Customer` billing/shipping user meta'sını okuyup
  yazıyor - ürün/sipariş için zaten geçerli olan "tamamen WooCommerce'in
  kendisi kalsın" kuralının (bkz. "Kural") adreslere de uygulanmış hali.
  Böylece veli Profilim'de kaydettiği adres, WooCommerce'in checkout'unda
  otomatik dolduruluyor - ayrı, senkronize edilmesi gereken ikinci bir adres
  deposu hiç oluşmuyor.

- Tema tarafı: `templates/parent-dashboard.php`'ye kullanıcı adı (salt-okunur
  input) + e-posta alanı eklendi, ayrıca Fatura Adresi/Gönderim Adresi için
  TEK bir form içinde iki alt-bölüm içeren yeni "Adres Bilgileri" kartı
  eklendi (tek `PUT` çağrısıyla ikisi birden kaydediliyor -
  `CustomerAddressRestController`'ın tek uç noktasıyla eşleşiyor).
  `templates/partials/account-security.php` (zaten hem `/profilim` hem
  `/admin`,`/sube`'de paylaşılan "Hesap Güvenliği" kartı) yeni bir "Şifre
  Değiştir" formu kazandı - `assets/js/account-security.js` bu formu
  `PUT /security/password`'a bağlıyor.

### 37. Genel Bakış (anasayfa panosu) ve Toplu Duyuru

İki ayrı yeni özellik:

**Genel Bakış.** "Genel Merkez için özet/anasayfa panosu." Yeni
`Seviye\Reports\Http\OverviewRestController` (`GET /reports/overview`),
`/admin` ve `/sube`'nin EN ÜSTÜNDE (quicknav'da da ilk sırada) render
ediliyor - bugün/son 7 gün/son 30 günün sipariş sayısı + cirosu, en çok
satan 5 ürün, ve (yalnızca HQ) şube bazlı kırılım. Kapsam
`ReportsRestController` ile birebir aynı (`VIEW_REPORTS`: her şube,
`VIEW_OWN_REPORTS`: yalnızca kendi şube).

Bilinçli bir tasarım kararı: bu controller `ReportsRestController::sales()`
gibi `OrderLineItemQueryInterface`/`scp_order_line_items` üzerinden DEĞİL,
`AdminOrdersRestController`'ın bölüm 36'da benimsediği aynı yolla -
`wc_get_orders()`'ı doğrudan okuyup her kalemin şubesini
`StudentLookupInterface` ile çözerek - çalışıyor. Bir KPI panosunun,
satır eksik bırakabilen ikincil bir önbellek tablosundan beslenip Genel
Merkez'e güvenilmez rakamlar göstermesi kabul edilemezdi. (Not: mevcut
`ReportsRestController`'ın CSV/Excel satış raporu ve Finance'in hakediş
tetikleme akışı HÂLÂ o tabloyu kullanıyor - bu, ayrı ve daha büyük bir
düzeltme konusu, bu turun kapsamı dışında.) "completed" tanımı (ödemesi
kesinleşmiş, iade edilebilir değil) `ReportsRestController::sales()` ile
aynı.

**Toplu Duyuru.** "Toplu duyuru sistemi." Genel Merkez/Bölge Müdürü
(yeni `NotificationCapability::SEND_BROADCAST`) herhangi bir şubeye veya
TÜM velilere; Şube Müdürü (`SEND_OWN_BRANCH_BROADCAST`) yalnızca kendi
şubesinin velilerine duyuru gönderebiliyor - istekteki `branch_id` ne
olursa olsun Şube Müdürü'nün kapsamı sunucu tarafında kendi şubesine
sabitleniyor (platformun her yerinde geçerli "sunucu kapsamı çözer"
kuralı).

Alıcılar Students'ın yeni yayınlanmış `Contracts\BranchParentLookupInterface`'i
üzerinden çözülüyor - `ParentBranchLookupInterface`'in (veli -> hangi
şubeler) TERSİ (şube -> hangi veliler), `scp_student_parents` ⨝
`scp_students`'ı `branch_id`'ye göre gruplayan tek bir JOIN sorgusu. Bu,
Seviye Notifications'ın ilk kez Students VE Branches'e (composer.json'a,
`Requires Plugins` başlığına eklendi) doğrudan bağımlı olduğu an - önceden
yalnızca Core ve Parents'a (telefon numarası için) bağımlıydı.

Her alıcı, seçilen HER kanal için `NotificationDispatcherInterface::dispatch()`
ile ayrı ayrı çağrılıyor - yeni bir gönderim mekanizması DEĞİL, mevcut
kaydet→alıcı çöz→gönder→işaretle akışı üzerinde bir fan-out (varsayılan
kanallar: e-posta + panel; SMS isteğe bağlı, NetGSM yapılandırılmamışsa
zaten sessizce başarısız kaydediliyor - platformun her yerinde geçerli
"dürüst başarısızlık" kuralı).

### 38. Ürün varyantları, düşük stok uyarısı, öğrenci harcama limiti, mağaza arama/filtreleme, kupon sistemi

Kullanıcının "daha farklı neler yapabiliriz" sorusuna verilen beş öneri
listesinin tamamının ("Bunları yap") uygulandığı tur - beş ayrı, birbirinden
bağımsız özellik:

**1. Ürün varyantları (beden/renk).** `ProductsRestController::store()`
artık `sizes`/`colors` (virgülle ayrılmış listeler) verilirse
`WC_Product_Variable` (verilmezse eskisi gibi `WC_Product_Simple`)
oluşturuyor. `beden`/`renk` global öznitelik taksonomileri
(`wc_create_attribute()`) yoksa oluşturuluyor; WooCommerce'in kendi
gotcha'sı - yeni oluşturulan bir öznitelik taksonomisi AYNI istek içinde
kayıtlı değil - `delete_transient('wc_attribute_taxonomies')` +
`WC_Post_Types::register_taxonomies()` ile WC çekirdeğinin kendi admin Ajax
düzeltmesi birebir taklit edilerek çözüldü. Her beden×renk kombinasyonu
için ayrı bir `WC_Product_Variation` (kendi fiyat/stok alanlarıyla)
otomatik üretiliyor (`generateVariations()`). Yeni
`GET/PUT /commerce/products/{id}/variations` uç noktası varyantları listeler/
günceller - `updateVariations()` yalnızca gerçekten o ürünün çocuğu olan
varyant id'lerine yazıyor (`in_array($variationId, $childIds, true)`),
kötü niyetli bir istekle başka bir ürünün varyantının ele geçirilmesini
engelliyor. Tema tarafında "Ürünler" panelinde beden/renk alanları (yalnızca
YENİ ürün oluştururken - mevcut bir varyanlı ürünün varyant KÜMESİ
sonradan değiştirilemiyor, yalnızca tek tek varyantların fiyat/stoğu) ve
ayrı bir "Varyantları Düzenle" alt paneli eklendi.

**2. Düşük stok uyarısı.** WooCommerce'in kendi
`woocommerce_product_low_stock_notification`/
`woocommerce_variation_low_stock_notification` hook'larını (stok, ürünün
kendi `_low_stock_amount`'ı ya da site varsayılanı eşiğinin altına
düştüğünde WC çekirdeğinin ateşlediği, eşik tespitini yeniden yazmaya
gerek bırakmayan noktalar) dinleyen yeni
`Seviye\Commerce\Http\LowStockNotificationHooks`, platform-native
`commerce.product_low_stock` event'ini fırlatıyor. Notifications'ın yeni
`LowStockNotificationListener`'ı bunu dinleyip HER Genel Merkez/Bölge
Müdürü kullanıcısına (stok şubeye değil TÜM platforma ait paylaşılan bir
`WC_Product` olduğu için tüm HQ'ya, `OrderPlacedNotificationListener`/
`BroadcastRestController`'ın aksine tek bir şubeye değil) hem panel hem
e-posta kanalından bildirim gönderiyor - az önce (bölüm 36) kurulan Gmail
SMTP altyapısını yeniden kullanıyor, yeni bir gönderim mekanizması yok.

**3. Öğrenci/veli bazlı harcama limiti.** Yeni `scp_student_spending_limits`
tablosu (`student_id` UNIQUE, `period` monthly/term, `limit_amount`,
`scp_students`'a `ON DELETE CASCADE` FK) - satırın YOKLUĞU "limit yok"
anlamına geliyor (opt-in, `scp_product_branches`'ın opt-out modelinin
tersi). `period=term` ("dönemlik") bilinçli olarak sınırsız/kümülatif -
platformun Türk okul dönemi takvimine dair hiçbir doğruluk kaynağı
olmadığından "dönem" başlangıç/bitiş tarihini türetmeye çalışmak yerine bu
basitleştirme tercih edildi; `period=monthly` ("aylık") içinde bulunulan
takvim ayıyla sınırlı.

Harcanan tutar, `AdminOrdersRestController`/`OverviewRestController`'ın
bölüm 36-37'de benimsediği aynı ilkeyle -
`scp_order_line_items` yerine `wc_get_orders()`'ı DOĞRUDAN okuyup
`_scp_student_id` meta'sı eşleşen kalemleri toplayarak
(`StudentSpendingCalculator`, yalnızca ödenmiş - `wc_get_is_paid_statuses()`
- siparişler sayılıyor) hesaplanıyor.

Uygulama noktası: `WooCommerceCartHooks`'un aynı
`woocommerce_add_to_cart_validation` filtresine, ondan SONRAKİ bir
öncelikte (20 vs. 10) kaydolan yeni `SpendingLimitCartHooks` -
öğrencinin onaylanmış harcaması + sepetteki (o öğrenciye ait, henüz
sipariş olmamış) diğer kalemler + eklenmek istenen kalem limiti aşarsa,
`ProductVisibilityHooks`/`WooCommerceCartHooks`'un zaten kurduğu
`wc_add_notice()` + `return false` sert-engelleme örüntüsüyle sepete
eklemeyi reddediyor (kalan limit tutarıyla birlikte). Yönetim uç noktası
`GET/PUT/DELETE /commerce/students/{id}/spending-limit`, Students'ın
kendi `scp_manage_students` yetkisini ve `StudentsRestController::
canAccessStudent()` ile BİREBİR aynı şube-kapsama mantığını yeniden
kullanıyor (yeni bir yetki TANIMLANMADI). Tema tarafında "Öğrenciler"
panelindeki öğrenci düzenleme formuna gömülü küçük bir "Harcama Limiti"
alt bölümü eklendi.

**4. Mağaza tarafında arama ve kategori filtreleme.** Bu turdan önce hiç
yapılmamış, tamamen müşteri (veli) tarafına dönük ilk round. Özel bir
REST/JS filtre arayüzü YAZILMADI - WooCommerce'in kendi native arama formu
(`get_product_search_form()`) ve kategori taksonomi listesi
(`wp_list_categories(['taxonomy' => 'product_cat', ...])`) `inc/
woocommerce.php`'deki yeni `scp_render_shop_filters()` ile, mağaza sayfası
ve her ürün kategorisi arşivinin (`woocommerce_before_shop_loop` hook'u,
öncelik 5 - ürün döngüsünden hemen önce) üstünde render ediliyor. Bu
sayede WC'nin zaten var olan `?s=...` arama sorgu işleme ve
`product_cat` arşiv URL yapısı hiçbir ek kod olmadan çalışmaya devam
ediyor. `assets/css/woocommerce.css`'e platformun tasarım
token'larıyla (`--scp-*`) uyumlu bir `.scp-shop-filters` stili eklendi.

**5. Kupon/kampanya kodu sistemi.** "OKUL2026" gibi zaman sınırlı promosyon
kodları - Seviye Pricing'in mevcut şube/öğrenci bazlı fiyat kurallarından
(otomatik, satır bazında çözülen yapısal indirimler) tamamen ayrı bir
ihtiyaç: müşterinin ödeme sırasında KENDİSİNİN girdiği bir kod. Yeni
`Seviye\Commerce\Http\CouponsRestController` (`GET/POST /commerce/coupons`,
`PUT/DELETE /commerce/coupons/{id}`), WooCommerce'in kendi `WC_Coupon`'unu
(`shop_coupon` post type) sarmalayan ince bir katman - kupon depolaması da
Products/Orders gibi tamamen WooCommerce'in kendisinde kalıyor (bkz.
"Kural"). Müşteri tarafında hiçbir tema işi gerekmiyor: WooCommerce'in
`[woocommerce_cart]` şort kodunun kendi native "Kupon Kodu Uygula" formu
zaten çalışıyor.

Yeni `CouponCapability::MANAGE_COUPONS`, Products/Pricing'in aksine YALNIZCA
Genel Merkez/Bölge Müdürü'ne veriliyor - Şube Müdürü katmanı YOK, çünkü bir
kampanya kodu platform/kampanya seviyesinde bir kavram (paylaşılan
`shop_coupon` post type'ı), öğrenci/şube gibi tek bir şubeye ait değil.
Tema tarafında yalnızca `/admin`'de (Bölge Müdürü de `/admin` bölgesinde
oturduğu için ayrıca bir `/bolge` kontrolüne gerek yok) yeni bir "Kampanya
Kodları" paneli eklendi.

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
  temiz şekilde oturmuyor. Seviye Pricing bunu artık kurdu (bkz. bölüm 13) —
  `Contracts\PriceResolverInterface` yayınlıyor; WooCommerce'in
  `woocommerce_product_get_price` filtrelerine kancalanmak ise Pricing'in
  değil, Seviye Commerce'in sorumluluğu (yalnızca Commerce, sepete-ekleme
  anında hangi öğrenci için fiyatlandığını bilir — bkz. bölüm 13'ün
  gerekçesi).
- **Doğrudan WP hook'ları yerine EventBus**: yukarıda 2. maddede açıklandı.
- **FK kısıtlamaları**: WordPress çekirdek tabloları geleneksel olarak FK
  kullanmaz, ama `scp_*` tabloları InnoDB üzerinde gerçek FK kısıtlamalarıyla
  kurulmalıdır (spesifikasyonun kendisi de bunu istiyor). Bu, modül-özel
  migration'lar yazılırken uygulanacak.
