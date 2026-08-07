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

### 39. Seviye Depo (yeni eklenti) - tedarikçi, satın alma siparişi, stok hareketi defteri

"Depo tarafı için çok detaylı bir çalışma hazırla" isteğiyle önce ayrı bir
plan dokümanı hazırlandı (tek merkezi depo mu, şube bazlı çoklu depo mu -
platformun kritik açık kararı), kullanıcı "Tek bir depo vardır" diyerek
onayladı; bu bölüm o planın Faz 1'inin ("temel": tedarikçi CRUD + satın
alma siparişi CRUD + mal kabul + stok hareketi defteri) uygulanmasını
belgeliyor.

**Yeni bir eklenti, yeni bir modül.** `seviye-depo`, platformdaki 12.
eklenti - Finance'ın Commerce'den ayrı bir eklenti olarak kurulmasıyla
aynı ilke: bağımsız bir sorumluluk, bağımsız bir kurulum/aktivasyon
döngüsü. Tek bağımlılığı Core ve WooCommerce (aktivasyonu
`Environment::isWooCommerceActive()` ile doğrular) - Branches/Students'ın
Contracts'ına HİÇ bağımlı değil, çünkü "tek bir depo vardır" kararı
sonucu şube kavramı Depo modülünün hiçbir yerinde yok. `WarehouseCapability`
(scp_manage_suppliers, scp_manage_purchase_orders, scp_receive_stock,
scp_view_stock_movements), Products/Pricing'in aksine tek bir katman:
Genel Merkez + Bölge Müdürü (gözetim) ve Depo rolü (işin sahibi) hepsine
sahip, Şube Müdürü'ne hiçbiri verilmiyor.

**Ürün/stok yine WooCommerce'in.** Bu modül de platformun "Kural"ına
sadık: `wp_posts`'taki ürün/varyasyon kaydı ve `stock_quantity` alanı
WooCommerce'in tek doğruluk kaynağı olmaya devam ediyor. Depo yalnızca
ÜZERİNE üç yeni tablo ekliyor - `scp_suppliers` (tedarikçi),
`scp_purchase_orders`/`scp_purchase_order_items` (satın alma siparişi
başlığı + kalemleri, `purchase_order_id` CASCADE, `supplier_id` bilinçli
olarak RESTRICT - aktif/geçmiş siparişi olan bir tedarikçi sessizce
silinemesin, `scp_students.branch_id`'yle aynı ilke) ve
`scp_stock_movements` (append-only defter - stoğun NEDEN değiştiğinin
geçmişi, `scp_hakedis_entries`'in "asla düzenlenmez" ilkesiyle aynı).
`product_id` alanlarında FK yok - WC/WP çekirdek tablolarına hiçbir yerde
FK konmuyor.

**Satın alma siparişi durum makinesi.** draft → sent → (partially_received) →
completed, ya da her aşamada cancelled. draft→sent ve her durumdan
cancelled dışındaki geçişler MANUEL değil - `partially_received`/`completed`
her zaman kalemlerin toplam durumundan HESAPLANIR
(`PurchaseOrdersRestController::receive()` → `PurchaseOrderRepositoryInterface::receiveItem()`
→ `WpdbPurchaseOrderRepository::recalculateStatus()`). Bu hesaplamanın
gerçek kararı - "tüm kalemler tam teslim alındıysa completed, en az biri
teslim alındıysa partially_received, hiçbiri alınmadıysa değişmez" - ayrı,
saf bir sınıfta: `Support\PurchaseOrderStatusCalculator`. Commerce'in
`CartPricingService`/`SplitPaymentCalculator`'ıyla birebir aynı gerekçe:
repository'nin kendisi `FakeConnection`'ın "her `getResults()` çağrısı
AYNI sabit sonucu döndürür" sınırlaması yüzünden çok-sorgulu akışları uçtan
uca test edemiyor (bkz. `WpdbPurchaseOrderRepositoryTest`'in bu sınırlamayı
açıkça belgeleyen `testCreateInsertsAnAutoGeneratedCodeAndEveryItemRow()`
testi), ama asıl iş kuralı `PurchaseOrderStatusCalculatorTest`'te veritabanı
olmadan tam kapsamlı test ediliyor.

**Mal kabul'ün stok tarafı.** `receive()` her kalem için İKİ şey yapıyor:
WooCommerce'in kendi `wc_update_product_stock($productId, $quantity, 'increase')`
fonksiyonuyla gerçek stok sayısını artırıyor, VE
`StockMovementRepositoryInterface::record()` ile bir defter satırı
yazıyor (`type=purchase_in`, `reference_type=purchase_order`). Biri
gerçek sayıyı, diğeri NEDEN değiştiğinin denetlenebilir geçmişini tutuyor
- ikisi birbirinin yerine geçmez. `receive()` yalnızca sipariş SENT veya
PARTIALLY_RECEIVED iken çalışır, ve yalnızca o siparişin GERÇEK
kalemlerine yazar (`ProductsRestController::updateVariations()`'daki
`in_array($itemId, $validItemIds, true)` güvenlik önlemiyle aynı örüntü -
rastgele/başka bir siparişe ait bir `item_id` verilerek onun stoğunun
manipüle edilmesini engelliyor).

**Tema paneli.** Yeni "Depo" bölümü, `scp_manage_purchase_orders`
yetkisine göre KAPSANIYOR - zone kontrolü YOK (Pricing panelinin
örüntüsüyle aynı): `RoleRouter`'da Depo rolü `/sube` bölgesine
yönlendiriliyor, Genel Merkez/Bölge Müdürü ise `/admin`'e - aynı
markup/script (`depo-panel.js`) her iki bölgede de render ediliyor, hangi
zone'da olduğuna bakılmaksızın. Tedarikçiler basit bir CRUD alt paneli;
Satın Alma Siparişleri liste + oluşturma formu (dinamik kalem satırları)
+ bir detay görünümü (durum geçiş düğmeleri: Gönder/İptal Et, ve
sent/partially_received durumdaki siparişler için kalem başına "şimdi
teslim al" miktarı giren bir Mal Kabul formu).

**Faz 2/3 (stok sayımı, satın alma önerisi, Depo Raporları) artık
uygulandı** - bkz. bölüm 40.

### 40. Seviye Depo Faz 2/3: stok sayımı, düşük stok → satın alma önerisi, Depo Raporları

Bölüm 39'un "Kapsam dışı bırakılanlar" listesinin tamamının uygulanmasını
belgeliyor - plan dokümanının Faz 2 ("Denetim") ve Faz 3 ("Raporlama")
bölümleri.

**Stok sayımı (cycle count).** İki yeni tablo: `scp_stock_counts` (başlık -
`status` open/completed, `started_by`/`completed_by`; ayrı bir
`started_at`/`completed_at` sütunu YOK - `created_at` açılışı,
`status=completed` olduğunda `updated_at` kapanışı temsil ediyor,
`scp_purchase_orders`'ın "durum geçişine özel zaman damgası yok" ilkesiyle
aynı) ve `scp_stock_count_items` (`expected_quantity` - açılış anında
donan anlık görüntü, `counted_quantity` - sayılana kadar NULL).
`StockCountsRestController::store()` WC'nin `manage_stock` açık her
ürününü/varyasyonunu tarayıp o anki stok miktarını topluyor - bu tarama
WC'ye bağımlı olduğundan repository'de değil Http katmanında yapılıyor
(`PurchaseOrdersRestController::receive()`'ın `wc_update_product_stock()`'u
doğrudan çağırmasıyla aynı ilke). `complete()` fark ≠ 0 olan her kalem için
İKİ şey yapıyor: `wc_update_product_stock()` ile gerçek stoğu düzeltiyor VE
`StockMovementRepositoryInterface`'e `type=count_adjustment` satırı
yazıyor (`StockMovementType::COUNT_ADJUSTMENT`, Faz 1'de zaten
tanımlanmıştı) - fark = 0 olan kalemler için hiçbir yazma olmuyor. Yeni
`scp_manage_stock_counts` yetkisi, Faz 1'deki üç rolün (Genel
Merkez/Bölge Müdürü/Depo) hepsine veriliyor.

**Düşük stok → satın alma önerisi.** Yeni `scp_purchase_suggestions`
tablosu (`status` pending/dismissed/converted,
`converted_purchase_order_id` - yalnızca CONVERTED olduğunda dolu, FK
`scp_purchase_orders`'a). `LowStockPurchaseSuggestionListener`,
Commerce'in `commerce.product_low_stock` event'ini dinliyor (Notifications'ın
`LowStockNotificationListener`'ıyla AYNI event'in ikinci bir dinleyicisi -
yeni bir bildirim kanalı değil), `hasPending()` ile dedup ediyor (aynı
ürün için zaten bekleyen bir öneri varsa ikincisi açılmaz - WC bu hook'u
her satışta yeniden ateşleyebilir). `suggestedQuantity`, ürünün kendi
düşük stok eşiğine (`low_stock_amount` - event'e Commerce'in
`LowStockNotificationHooks::onLowStock()` tarafından eklendi,
`wc_get_low_stock_amount()` üzerinden site varsayılanına da düşebiliyor)
göre hesaplanıyor: eşiğin kabaca iki katına stoklanacak miktar
(`threshold * 2 - stockQuantity`, en az eşik kadar, en az 1) - kesin bir
sipariş değil, yalnızca bir başlangıç noktası, Depo görevlisi panelde
siparişe çevirirken serbestçe değiştirebiliyor. Event'te bu alan yoksa
(eski payload/WC < 5.4) sabit bir varsayılana (20) düşülüyor.
`PurchaseSuggestionsRestController::convert()`, `PurchaseOrderRepositoryInterface::create()`
üzerinden tek kalemli bir DRAFT satın alma siparişi açıyor - kod tekrarı
yok. Yeni `scp_manage_purchase_suggestions` yetkisi, aynı üç role
veriliyor. Listener kaydı `init`'e ertelendi (Commerce henüz boot
olmamış olabilir - `NotificationsModule`'ün aynı gerekçesiyle).

**Depo Raporları (Reports eklentisi genişlemesi).** Depo, ilk kez bir
Contract yayınlıyor: `Contracts\WarehouseReportQueryInterface`
(+`PurchaseOrderReportRecord`/`PurchaseOrderReportFilter`) ve
`Contracts\SupplierLookupInterface` (+`SupplierSummary`) -
`Seviye\Commerce\Contracts\OrderLineItemQueryInterface`/`WpdbOrderLineItemQuery`
ile birebir aynı "ayrı, minimal adapter" ilkesi
(`WpdbWarehouseReportQuery`/`WpdbSupplierLookup`, Domain sınıflarını asla
sızdırmıyor). `PurchaseOrderReportRecord.totalCost` tek bir SQL sorgusunda
(`scp_purchase_order_items` üzerinde `GROUP BY` alt sorgusu + `LEFT JOIN`)
önceden toplanıyor - N+1 sorgu yok. Reports artık `seviye-depo`'yu
"Requires Plugins" bağımlılığı olarak listeliyor (seviye-commerce'le aynı
düzeyde). Yeni `WarehouseReportBuilder` (saf, Commerce'in
`SalesReportBuilder`'ıyla aynı split) tedarikçi bazında gruplayıp iki şey
hesaplıyor: toplam satın alma tutarı ve "zamanında teslim oranı" -
yalnızca hem COMPLETED hem `expected_date`'i olan siparişler paydaya
giriyor (`expected_date`'i hiç verilmemiş bir sipariş "geç" sayılmaz,
sadece hesaba katılmaz). `GET seviye/v1/reports/warehouse`, yalnızca
`scp_view_reports`'a açık (Şube Müdürü'nün `scp_view_own_reports`'u
GEÇERSİZ - Depo'nun kendisi şube kavramından tamamen bağımsız olduğu
için raporlanacak hiçbir şube boyutu yok, bölüm 39'un "tek bir depo
vardır" kararıyla aynı gerekçe). `CsvExporter`/`XlsxExporter`'a
`exportWarehouse()` eklendi (yeni bir sınıf değil - aynı sınıfın ikinci
bir satır şekli için ikinci bir metodu, `XlsxExporter`'ın zip-iskelet
kodu `buildWorkbook()` altında ortaklaştırıldı).

**Tema paneli.** Depo panelinde iki yeni alt bölüm: "Stok Sayımı" (sayım
başlat → kalem bazlı miktar girişi, her girişte otomatik `PUT` → "Tamamla"
düğmesi) ve "Satın Alma Önerileri" (liste + Reddet/Siparişe Çevir).
Raporlar panelinde yeni bir "Depo Raporları" alt bölümü (yalnızca
`scp_view_reports` - Raporlar panelinin ana bölümündeki HQ-vs-own-branch
ayrımından bağımsız, kendi `current_user_can()` kontrolü).

### 41. Students: toplu öğrenci kaydı (CSV içe aktarma)

Okul yılı başında tek tek form doldurmak yerine bir CSV dosyasıyla çok
sayıda öğrenci tek istekte açılabiliyor. `Support\StudentImportParser`
(saf, WP'ye bağımsız - "Support classes stay pure, Http classes touch the
platform" ilkesi, bkz. `SalesReportBuilder`'ın docblock'u) yalnızca
YAPISAL doğrulama yapıyor (başlık satırı eşleşmesi, zorunlu alan boşluğu);
iş kuralı doğrulaması (geçersiz eğitim yılı/T.C. No biçimi) satır
`StudentRepositoryInterface::create()`'e verildiğinde, `store()`'un zaten
kullandığı `EducationYear::fromString()`/`InvalidArgumentException`
yoluyla gerçekleşiyor - `validateTcNo()` bu yüzden `resolveStudentTcNo()`
(request'ten okuyan) ile `import()` (CSV satırından okuyan) arasında
ortaklaştırıldı. `POST /students/import`, `store()`'daki
`resolveBranchIdForWrite()`'ın AYNISINI kullanıyor: dosyanın TÜMÜ tek bir
şubeye yazılıyor (Şube Müdürü her zaman kendi şubesine, HQ `branch_id`
vermek zorunda) - bir dosyada birden fazla şubeye dağılmış satır
desteklenmiyor. Veli bilgisi CSV'de YOK - `store()`'un aksine, bir satırda
hem öğrenci hem veli alanlarını karıştırmak biçimi karmaşıklaştırırdı;
veli bağlama panelden ayrı, tekil bir işlem olarak kalıyor. Kısmi başarı
normal: bir satırdaki hata diğerlerini engellemiyor, her satır kendi
başarı/hatasıyla ayrı raporlanıyor. Tema panelinde CSV dosyasını
`FileReader` ile istemci tarafında okuyup metni JSON gövdede gönderen bir
form + örnek şablon indirme bağlantısı (client-side üretilen bir Blob,
ayrı bir REST uç noktası gerekmiyor).

### 42. Notifications: haftalık özet e-postası (WP Cron)

Platformun ilk WP Cron işi - bir EventBus tepkisi değil, çünkü "bir hafta
geçti"yi dışarıdan ateşleyen bir olay yok, tetikleyici zamanın kendisi.
`Http\WeeklyDigestHooks`, `cron_schedules` filtresine WP çekirdeğinin
sunmadığı bir `scp_weekly` aralığı ekliyor (`WEEK_IN_SECONDS`), sonra
`wp_schedule_event()` ile kendini haftalık tetikliyor.

Bu, Finance ve Depo'nun ilk kez PLATFORM GENELİ (branch_id'siz) bir
Contract yayınlamasını gerektirdi: `Finance\Contracts\HakedisTotalsInterface::totalOutstandingBalance()`
(`WpdbHakedisTotals` - `HakedisRestController`'ın kullandığı per-branch
`balanceForBranch()`/`settledForBranch()`'in AKSİNE, WHERE branch_id
olmadan tüm platformun toplamı) ve
`Depo\Contracts\PurchaseSuggestionSummaryInterface::pendingCount()`
(`WpdbPurchaseSuggestionSummary`) - ikisi de kendi modüllerinin dolu
Domain nesnelerini değil, tek bir sayıyı sızdıran minimal adapter'lar,
`WpdbSupplierLookup`'la aynı ilke. Haftalık satış rakamı ise bir Contract
gerektirmiyor - WC bir Seviye modülü olmadığından `wc_get_orders()`
doğrudan çağrılıyor (`OverviewRestController`/`AdminOrdersRestController`'ın
aynı hakkı kullanması gibi).

Notifications artık `seviye-finance` VE `seviye-depo`'yu "Requires
Plugins" bağımlılığı olarak listeliyor - `Activator`, `Parents` kontrolüyle
aynı şekilde ikisinin de aktif olduğunu doğruluyor. Sayılar
`Support\WeeklyDigestBuilder`'a (saf, WP'siz) veriliyor - her `__()`
çağrısı kendi literal string'ini taşıyor, `PasswordResetNotificationListener`'ın
dokümante ettiği kural gereği (bir `$text` parametresi alan paylaşılan bir
`translate()` sarmalayıcısı YAZILMADI, WordPress'in i18n araçları
`__()`'in argümanını literal olarak taradığı için). Alıcılar
`LowStockNotificationListener`'ın `hqUserIds()`'ıyla aynı: her Genel
Merkez/Bölge Müdürü kullanıcısı, hem e-posta hem panel kanalına.
`Deactivator`, `wp_clear_scheduled_hook()` ile zamanlanmış cron'u
temizliyor - saklanan veri deaktivasyonda KORUNUYOR ama sarkan bir cron
olayı öyle değil, temizlenmezse artık var olmayan bir container'ı
çağırmaya devam ederdi.

### 43. Tema: responsive/mobil gözden geçirme + PWA manifest

Gözden geçirme, platformun ÇOĞUNUN zaten makul ölçüde responsive
olduğunu ortaya çıkardı: `.scp-table-wrapper`'ın `overflow-x: auto`'su,
`.scp-form__row`/`.scp-form--inline`'ın `flex-wrap`'i, WooCommerce
mağaza/ürün/ödeme sayfalarının kendi `@media (max-width: 782px)`
blokları, ve `viewport` meta etiketi (`header.php` + `templates/login.php`)
zaten yerindeydi. Asıl boşluk panel.css'in hiç mobil breakpoint'i
OLMAMASIYDI - yeni `@media (max-width: 640px)` bloğu üç somut sorunu
çözüyor: `.scp-card__header` (başlık + eylem düğmesi satırı) artık
sarıyor, `.scp-form--inline` dar ekranlarda tam sütuna dönüşüyor (satır
içi sarma yerine), ve `.scp-form__actions` düğmeleri esneyip dokunma
hedefini büyütüyor. `.scp-table-wrapper`'a `-webkit-overflow-scrolling: touch`
eklendi (iOS'ta momentum scroll).

**PWA manifest.** Statik bir JSON dosyası DEĞİL - tema hiç statik ikon
taşımıyor, platformun logosu admin tarafından yüklenen bir WP attachment
(`inc/branding.php`, "Görünüm" paneli). `inc/pwa.php`,
`inc/zones.php`'nin rewrite-rule-tabanlı sanal endpoint örüntüsünü
birebir izliyor: `/manifest.webmanifest` → `template_redirect`
(ÖNCELİK 1 - `inc/access-gate.php`'in öncelik 5'teki login-yönlendirme
kapısından ÖNCE çalışmalı, aksi halde oturum açmamış bir tarayıcının
arka plan manifest isteği login ekranına yönlendirilirdi).
`scp_manifest_icons()`, yüklenmiş logo varsa `wp_get_attachment_image_src()`
ile GERÇEK piksel boyutlarını okuyup manifest'in `sizes` alanına yazıyor
(uydurma bir değer değil); logo yoksa boş bir icons dizisi - geçersiz
değil, tarayıcı genel bir ikona düşüyor. iOS Safari manifest'in icons
dizisini hiç okumadığından, ayrıca bir `<link rel="apple-touch-icon">`
de basılıyor.

### 44. wp-env entegrasyon test iskeleti

Her plugin'in kendi `tests/Unit`'i WordPress'i TAMAMEN taklit ediyor (fake
`ConnectionInterface`, WP fonksiyonu yok) - bu, "12 eklenti gerçekten
birlikte boot oluyor mu", "migration gerçekten tablo oluşturuyor mu", "bir
REST uç noktası uçtan uca gerçekten yanıt veriyor mu" sorularını YAPISAL
olarak asla kapsayamaz. Bu bölüm o katmanın iskeletini kuruyor - kök
`.wp-env.json` (WooCommerce + 12 eklenti + tema), `tests/integration/bootstrap.php`
(WP'nin kendi çekirdek test paketini yükleyip her eklentiyi
`plugin-installer.php`'nin belgelediği bağımlılık sırasıyla
`muplugins_loaded`'a require ediyor), `phpunit-integration.xml.dist`, ve
bir örnek test (`PluginActivationTest` - 12 eklentinin aktif olduğunu,
Core container'ının çalıştığını, birkaç kritik `scp_*` tablosunun var
olduğunu doğruluyor).

**Bu iskelet bu oturumda ÇALIŞTIRILAMADI.** `wp-env` bir Docker container'ı
gerektiriyor; bu sandbox'ta Docker daemon'u çalışmıyor VE `wordpress.org`'a
proxy üzerinden erişim 403 ile engelli (composer/packagist erişimi ayrı,
o çalışıyor - kök `composer.json`'a `phpunit`/`yoast/phpunit-polyfills`
eklenip `composer update` gerçekten çalıştırıldı ve doğrulandı). Bu yüzden
`.github/workflows/integration-tests.yml` bilinçli olarak yalnızca elle
tetikleniyor (`workflow_dispatch`) - `ci.yml`'nin push/PR'da otomatik
çalışan kontrollerinin AKSİNE, doğrulanmamış bir işi otomatik tetikleyip
her push'ta kırmızı bir kontrol riski yaratmamak için. Docker + internet
erişimi olan bir ortamda (yerel makine ya da bu workflow elle tetiklenerek)
bir kez doğrulandıktan sonra otomatik tetikleyicilere taşınabilir - bkz.
`tests/README.md`.

### 45. Güvenlik denetimi: Raporlar CSV/XLSX dışa aktarımında formül enjeksiyonu düzeltmesi

`/security-review` ile yapılan bir denetimde, Raporlar eklentisinin
`CsvExporter`/`XlsxExporter`'ının (bölüm/ürün/tedarikçi adı gibi) metin
hücrelerini hiç dönüştürmeden yazdığı, bunun da klasik bir CSV/formül
enjeksiyonu (CWE-1236) açığına yol açtığı tespit edildi: bir Şube Müdürü
(`ProductsRestController::store()` üzerinden, `MANAGE_PRODUCTS`
yetkisiyle) ürün adını `=HYPERLINK(...)` gibi bir formülle oluşturabiliyor;
bu ürün adı hiçbir zaman sanitize edilmeden Satış Raporu'na taşınıyor ve
daha yüksek yetkili bir Genel Merkez/Bölge Müdürü kullanıcısı raporu
CSV/XLSX olarak indirip Excel/LibreOffice/Sheets'te açtığında hücre bir
formül olarak çalıştırılabiliyor (ör. veri sızdırma amaçlı `HYPERLINK`).
WordPress'in kendi `sanitize_text_field()`/başlık kaydetme yolu `=`/`+`/
`-`/`@` gibi karakterleri temizlemediğinden bu koruma dışarıdan gelmiyordu.

Düzeltme: her iki exporter'a da OWASP'ın standart CSV-enjeksiyonu
önlemini uygulayan bir `neutralizeFormula()` yardımcı metodu eklendi -
bir hücre değeri `=`, `+`, `-`, `@`, tab veya CR ile başlıyorsa başına tek
tırnak (`'`) ekleniyor, böylece hücre metin olarak okunmaya zorlanıyor
(CSV tarafında `fputcsv`'ye geçmeden önce, XLSX tarafında
`htmlspecialchars` ile XML-kaçışından önce). `CsvExporterTest`/
`XlsxExporterTest`'e birer regresyon testi eklendi.

### 46. Tema: gerçek offline destek - service worker (`/service-worker.js`)

`inc/pwa.php`'deki manifest'in aynısı rewrite-rule-tabanlı sanal uç nokta
deseniyle, artık `/service-worker.js`'i de dinamik olarak üretiyor -
`scp_render_service_worker()`, `scp_render_manifest()`'in birebir eşi
(aynı priority-1 `template_redirect` gerekçesi: bir service worker isteği
tarayıcının arka plan isteğidir, `access-gate.php`'nin login yönlendirmesi
tarafından asla yakalanmamalı).

Bu bir panel uygulaması - neredeyse her ekran REST-destekli canlı veri
(sipariş, hakediş bakiyesi, stok) gösteriyor, dolayısıyla "gerçek offline
destek" burada yalnızca iki şey anlamına gelebilir: (1) statik tema
CSS/JS'i stale-while-revalidate ile önbellekten anında yüklenir, arka
planda güncellenir; (2) ağ tamamen koptuğunda bir sayfa navigasyonu
tarayıcının çirkin varsayılan hata sayfası yerine okunabilir bir "şu anda
çevrimdışısınız" ekranı gösterir (`scp_service_worker_offline_html()`,
gömülü tek bir HTML string). `/wp-json/` REST yanıtları KASITLI olarak
hiç önbelleğe alınmıyor/yakalanmıyor - sipariş/hakediş/stok gibi verilerin
"çevrimdışıyken" sessizce eski bir REST yanıtından gösterilmesi, o
ekranların zaten sahip olduğu normal yükleme-hatası durumundan daha
kötü olurdu.

`scp_service_worker_cache_version()`, `scp_asset_version()`'ın "dosya
mtime'larından türet, elle bump edilen bir sabite asla güvenme" ilkesini
service worker'ın kendi `CACHE_NAME`'i için de uyguluyor - herhangi bir
tema CSS/JS'i değiştiğinde worker'ın `activate` olayı eski önbelleği
otomatik siliyor.

### 47. Commerce: iade/iptal akışı

Sipariş yönetiminde iade/iptal hiç yoktu - Şube Müdürü/Genel Merkez'in
panelden bir siparişi durdurabilmesi ya da parayı geri kaydedebilmesi
gerekiyordu. İki ayrı, kasıtlı olarak farklı yetki seviyesinde uç nokta
eklendi (`AdminOrdersRestController::cancel()`/`refund()`,
`seviye/v1/commerce/orders/{id}/cancel|refund`):

- **İptal** (`CANCEL_ORDERS`/`CANCEL_OWN_BRANCH_ORDERS` - Genel Merkez/
  Bölge Müdürü/Şube Müdürü, `VIEW_ORDERS`/`VIEW_OWN_BRANCH_ORDERS`'la aynı
  iki katman): yalnızca henüz `completed`'e ulaşmamış (hakediş hiç
  tetiklenmemiş) bir sipariş için - `$order->update_status('cancelled', ...)`,
  para hareketi yok.
- **İade** (`REFUND_ORDERS` - yalnızca Genel Merkez/Bölge Müdürü/Muhasebe,
  Finance'in `RECORD_SETTLEMENT`'ıyla aynı "para hareketi HQ-only" ilkesi,
  branch-scoped bir katmanı KASITLI olarak yok): yalnızca `completed` bir
  sipariş için, `wc_create_refund()` ile tam veya kısmi tutar
  (`refund_payment => false` - bu platformun tek ödeme yöntemleri banka
  havalesi/nakit, gerçek bir gateway'e iade çağrısı yapılacak bir şey yok;
  `restock_items => false` - fiziksel iade Depo'nun kendi ayrı akışı,
  bookkeeping iadesinin otomatik bir yan etkisi değil).

Hakediş tersine çevirme YENİ bir mekanizma gerektirmedi -
`OrderPersistenceHooks::syncOrderStatus()` zaten `completed → refunded`/
`cancelled` geçişini dinliyordu (bkz. bölüm "Kural": HAKEDIS_REVERSAL_STATUSES).
Tek gerçek boşluk: KISMİ bir iade WooCommerce'in sipariş durumunu
`completed`'den hiç düşürmüyor (WC'nin kendi davranışı), dolayısıyla
`syncOrderStatus()`'un tepki vereceği bir geçiş hiç olmuyor - kısmi iade
hakedişi orantılı olarak asla ayarlamıyor, bu gerçek bir sınır olarak
`refund()`'ün docblock'unda açıkça belgelendi (sessizce yanlış yapmak
yerine).

Müşteri bildirimi için iki yeni olay: `commerce.order_cancelled`
(`syncOrderStatus()`'tan, tam iptal/iade ile aynı yerden) ve
`commerce.order_refunded` (WooCommerce'in KENDİ `woocommerce_order_refunded`
hook'undan - `woocommerce_order_status_changed`'in aksine hem tam HEM
kısmi iadede tetikleniyor, kısmi iade bildiriminin hiç ateşlenmemesini
önleyen asıl seçim buydu). `Seviye\Notifications\Support\OrderStatusNotificationListener`,
`OrderPlacedNotificationListener`'ın aynısı ilişkiyle (yalnızca olay
adı/payload, Commerce'e hiç bağımlılık yok) ikisini de dinleyip veliye
e-posta gönderiyor.

### 48. Reports: Genel Bakış'a günlük ciro trend grafiği

`OverviewRestController`'ın zaten çektiği son 30 günlük WC sipariş
verisi, yeni bir `Support\DailyTrendBuilder` (saf, `SalesReportBuilder`'la
aynı "Support pure/Http touches platform" ayrımı) ile günlük kovalara
gruplanıp `daily_trend` alanı olarak `/reports/overview` yanıtına eklendi.
`DailyTrendBuilder`, istenen aralıktaki HER günü sıfırla dolduruyor - sipariş
olmayan bir gün grafikte sessizce atlanırsa, trend çizgisi yanıltıcı bir
şekilde kesintisiz görünür.

Tema tarafında (`overview-panel.js`), harici bir grafik kütüphanesi
yerine elle yazılmış küçük bir SVG çizgi+alan grafiği - `XlsxExporter`'ın
kendi zip yazıcısı için belgelediği "dar, iyi anlaşılmış bir ihtiyaç için
ağır bir bağımlılıktan kaçın" gerekçesinin aynısı (bkz. bölüm 16): 30
nokta, tek çizgi, bir bağımlılığı hak etmiyor. Eksen/gridline yok
(kasıtlı - bu analitik değil, kompakt bir trend göstergesi); her noktanın
tarih/ciro/sipariş sayısı kendi native SVG `<title>` hover tooltip'inde.

### 49. KVKK: veri ihracı/silme talebi

Platform T.C. Kimlik No gibi hassas veri tutuyor ama bir "veri ihracı/silme
talebi" mekanizması hiç yoktu. Security eklentisine yeni bir `Privacy\*`
namespace'i eklendi (`scp_privacy_requests` tablosu, `PrivacyRequest`/
`PrivacyRequestType`/`PrivacyRequestStatus`, `WpdbPrivacyRequestGateway`) ve
`seviye/v1/privacy/requests/*` uç noktaları:

- **EXPORT** (`POST /privacy/requests/export`) anında tamamlanır, her giriş
  yapmış kullanıcı için self-service (2FA gibi capability'siz - "erişim
  hakkı" başkasının onayını gerektirmemeli). Yanıt bir JSON dosya indirme
  (`Content-Disposition: attachment`) - WP hesap alanları, `scp_user_identities`
  T.C. No + 2FA durumu, (varsa) veli telefonu, (varsa) bağlı öğrencilerin
  temel bilgileri. Sipariş/hakediş geçmişi KASITLI OLARAK dahil değil - o
  veri Commerce'in, platform bunu bir gizlilik talebinden bağımsız olarak
  (mali kayıt saklama yükümlülüğü) tutmak zorunda, ve onu dahil etmek
  Security'nin Commerce'e yeni bir bağımlılığını gerektirirdi - bu
  özelliğin kapsamını haklı çıkarmayacak kadar büyük bir değişiklik.
- **DELETION** (`POST /privacy/requests/deletion`) yalnızca PENDING bir
  talep oluşturur - Genel Merkez (`MANAGE_PRIVACY_REQUESTS`) onaylamadan
  hiçbir şey değişmez, çünkü onayın geri alınamaz bir yan etkisi var
  (`scp_user_identities` bağlantısı kaldırılır → hesap artık T.C. No ile
  giriş yapamaz - platformun TEK giriş yöntemi). Anonimleştirme kapsamı da
  aynı ilkeyle dar tutuldu: yalnızca WP hesabı (display_name/e-posta) ve
  `scp_user_identities`/2FA satırları temizleniyor; WooCommerce müşteri/
  fatura meta'sına ve öğrenci (child) kayıtlarına hiç dokunulmuyor - onlar
  okulun kendi operasyonel/kayıt tutma verisi, bir velinin self-service
  silme talebinin bunları silmeye yasal dayanağı yok.

Security ilk kez Parents'a bağımlı oldu (composer.json + "Requires
Plugins" - `ParentContactLookupInterface::phoneFor()` için) ve Students'ın
yeni `ParentChildrenLookupInterface::childrenOf()` Contract'ını kullanıyor
(bu talebin ihtiyacı için eklenen tek gerçek eksik parça - `StudentGuardianCheckInterface`
yalnızca "X, Y'nin velisi mi" soruyordu, "X'in tüm çocukları" değil).

### 50. Pricing: toplu fiyat kuralı CSV içe aktarma

`Students\Support\StudentImportParser`'ın aynısı desenle
(`Pricing\Support\PriceRuleImportParser`, saf) `product_id, scope, target_id,
price` sütunlu bir CSV'yi satır satır ayrıştırıyor. `PricingRestController::store()`
kural oluşturma mantığının TAMAMI (kapsam çözümleme, RBAC kapsam kontrolü,
aktif-kural-tekrarı kontrolü, taban fiyat floor kontrolü) `createRule()`
adında paylaşılan bir metoda çıkarıldı - hem `store()` hem yeni `import()`
bunu çağırıyor, böylece bir CSV satırı tek bir manuel POST'un tabi olduğu
hiçbir kuralı asla atlayamaz. Yanıt şekli `StudentsRestController::import()`'la
birebir aynı (`imported_count`/`error_count`/`imported`/`errors`).

### 51. Reports: şube/ürün performans karşılaştırma grafiği

Yeni bir uç nokta YOK - `reports-panel.js` zaten `/reports/sales`'ten
çektiği aynı satırları (branch_id/name, product_id/name, total_price)
istemci tarafında yeniden gruplayıp bir karşılaştırma grafiği çiziyor
("Şubelere Göre"/"Ürünlere Göre" değiştirilebilir). Bir SVG grafik yerine
düz CSS genişlik-yüzdesi çubukları (flexbox) - uzun şube/ürün adlarıyla
yatay bir çubuk listesi, hazır SVG yazmaktan daha basit, aynı "gereksiz
bağımlılık/karmaşıklıktan kaçın" ilkesinin (bkz. bölüm 48) bir başka
uygulaması.

### 52. Tedarikçi portalı (Seviye Depo, tema)

Tedarikçi kullanıcılarının kendi satın alma siparişlerini görüp "gönderildi"
işaretleyebildiği bir portal — ama tedarikçiler için ayrı bir Rol EKLENMEDİ.
`Suppliers` zaten `user_id` alanıyla bir WP kullanıcısına bağlanabiliyordu
(bölüm 39); bu bağlantı doğrudan yetkilendirme temeli olarak kullanıldı.
`DepoModule::boot()` bir `scp_depo_supplier_id_for_user` filtresi kaydediyor
(`SupplierRepositoryInterface::findByUserId($userId)?->id ?? $default`);
Depo dışındaki her yer (Security'nin `AuthRestController::landingPathFor()`'ı,
temanın `inc/zones.php`/`inc/access-gate.php`'i) bu filtreyi
`RoleRouter::landingPathFor()`'dan ÖNCE danışıyor — yani "tedarikçi" bir Rol
değil, "bu kullanıcı bir tedarikçiye bağlı mı" sorusuna verilen ayrı bir
cevap. Yeni REST uçları (`GET /depo/purchase-orders/mine`,
`POST /depo/purchase-orders/{id}/mark-shipped`) da herhangi bir Capability
değil, doğrudan bu bağlantının varlığını kontrol ediyor
(`requireLinkedSupplier()`). Tema tarafında yeni bir `/tedarikci` zone'u,
`templates/supplier-dashboard.php` ve `assets/js/supplier-panel.js`.

### 53. Veli destek/talep (helpdesk) sistemi (yeni eklenti: Seviye Destek)

Platformun 13. eklentisi. Veli'nin "şikayet/soru" ticket'ı açıp mesaj
thread'i üzerinden yazışabildiği, ilgili şube personelinin (Şube Müdürü,
Bölge Müdürü, Genel Merkez, Rehberlik) kuyruğu görüp yanıtlayabildiği ayrı
bir sistem — KVKK veri talebi akışından (bölüm 49) tamamen bağımsız.
`scp_support_tickets.branch_id` nullable ve bilinçli olarak FOREIGN KEY
DEĞİL (Branches Contract sınırını aşan bir `scp_*`→`scp_*` referansı, aynı
gerekçe daha önce `scp_scheduled_broadcasts`/diğerlerinde de kullanıldı);
`scp_support_messages.ticket_id` ise gerçek bir
`FOREIGN KEY ... ON DELETE CASCADE` — ikisi de aynı eklenti içinde. Ticket
durum makinesi basit: personel yanıtı → `ANSWERED`, veli yanıtı → tekrar
`OPEN`; kapatma yalnızca personel tarafından. RBAC: `SUBMIT_TICKET` (Veli),
`MANAGE_TICKETS` (şube kapsamlı personel). Tema: velinin kendi ticket'larını
gördüğü self-service kart (`templates/partials/support-tickets.php`,
yalnızca `scp_submit_support_ticket` sahibiyse `parent-dashboard.php`'den
include edilir) ve personel kuyruğu (`templates/zone.php`).

### 54. Ürün inceleme/puanlama sistemi (Seviye Commerce)

Özel bir puanlama tablosu/API'si KURULMADI — WooCommerce'in kendi native
yorum/puanlama sistemi (comments tablosu üzerine kurulu) olduğu gibi
kullanılıyor, yalnızca "yalnızca gerçekten satın alanlar yorum yapabilir"
kısıtı eklendi. `CommerceModule`'ün var olan WC-gated `init` closure'ı
içinde kayıtlı yeni `Http\ProductReviewGate`: `register()` önce
`ensureReviewsEnabled()` ile `woocommerce_enable_reviews`/
`woocommerce_enable_review_rating` ayarlarını zorla `yes` yapıyor, sonra
`pre_comment_approved` filtresine bağlanıyor. Bu filtre WC'nin kendi
"doğrulanmış satın alma" rozeti ayarından (`woocommerce_review_rating_
verification_required`) FARKLI — o yalnızca bir rozet gösterir, gönderimi
engellemez; `pre_comment_approved`'dan `WP_Error` döndürmek ise gönderimi
tamamen reddeder. Personel (`scp_manage_products`) her zaman geçebilir;
veli/müşteri için `wc_customer_bought_product()` ile gerçek satın alma
kontrolü yapılıyor.

### 55. Toplu sınıf/eğitim yılı geçişi (Seviye Students)

Yeni bir "yıl sonu" iş akışı: bir şubedeki (veya tüm şubelerdeki) aktif
öğrencileri bir eğitim yılından bir sonrakine topluca taşıma, isteğe bağlı
sınıf adı eşlemesiyle (`"5-A=6-A"` gibi). `EducationYear::next()` (start+1 -
start+2) eklendi. Yeni `POST /students/promote` ucu KENDİ güncelleme
mantığını yazmıyor — `StudentsRestController`'ın var olan tekil `update()`
metodunu her eşleşen öğrenci için tekrar tekrar çağırıyor (eşleşme: durumu
`ACTIVE` ve `education_year`'ı verilen `from` ile birebir aynı olan
öğrenciler), böylece tek bir öğrenci güncellemesinin tabi olduğu hiçbir
kural (RBAC şube kapsamı dahil) toplu geçişte atlanmıyor — bölüm 50'nin CSV
içe aktarma turunda kurulan "paylaşılan tekil işlem metodu" deseninin bir
başka uygulaması. Tema: öğrenci panelinde şube + `from_education_year` +
sınıf-eşleme metin alanından oluşan iç içe bir kart.

### 56. Kısmi iade → hakediş orantılı ters kayıt (Seviye Commerce + Finance)

Bölüm 47'nin iade/iptal akışı yalnızca TAM iadeyi hakediş defterine
yansıtıyordu (`syncOrderStatus()`'un %100 iade → `REVERSED` yolu); kısmi bir
iade hakediş bakiyesini hiç etkilemiyordu — bu tur o boşluğu kapatıyor.
`OrderPersistenceHooks::onOrderRefunded()` artık
`$order->get_remaining_refund_amount() > 0.0` olduğunda (yani iade TAM
DEĞİLSE) `ratio = refund->get_amount() / order->get_total()` oranını
hesaplayıp `commerce.order_line_item_partially_reversed` olayını
tetikliyor; bu kontrol WooCommerce'in iç hook sırasının güvenilmez
olabileceği varsayımıyla iadenin kendi tutarından değil, siparişin GÜNCEL
gerçek durumundan türetiliyor — %100'e TAMAMLAYAN bir kısmi iadenin
`syncOrderStatus()`'un kendi tam-iade yoluyla ÇİFT ters kayıt yaratması
böyle engelleniyor. Finance tarafında yeni `HakedisEntryType::
PARTIAL_REVERSAL` ve `scp_hakedis_entries.refund_id` — aynı sipariş
kalemine birden çok kısmi ters kayıt satırı düşebildiğinden, mevcut
tekil-kayıt idempotency güvencesini bozmamak için bölüm 39'da
`scp_suppliers.user_id`'de kurulan "NULL yerine sentinel 0" deseni
(`UNIQUE KEY (order_id, order_item_id, type, refund_id)`, depoda `0`,
domain'de `null`) burada da uygulandı.

### 57. Zamanlanmış toplu duyuru (Seviye Notifications)

Bölüm 37'nin toplu duyuru sistemine "ileri bir tarihte gönder" seçeneği
eklendi. Yeni `scp_scheduled_broadcasts` tablosu ve
`WP Cron`'un TEK SEFERLİK deseni (`wp_schedule_single_event($timestamp,
ScheduledBroadcastHooks::HOOK, [$id])` + iptal için
`wp_clear_scheduled_hook()`) — bölüm 42'nin `WeeklyDigestHooks`'unun
kullandığı TEKRARLI `wp_schedule_event` deseninden bilinçli olarak farklı.
`ScheduledBroadcastHooks::send()` alıcıları OLUŞTURMA anında değil, ateşlenme
anında yeniden çözüyor (bir şubeye o zamana kadar yeni eklenen veliler de
duyuruyu alsın diye). Şube kapsamı çözümü, bölüm 53'ün destek talebi kapsam
mantığıyla aynı ilkeyi izliyor: `null` branch_id = tüm şubeler (Genel
Merkez-tipi capability), dolu branch_id = tek şubeye kilitli
(şube-kapsamlı capability). `ScheduledBroadcastHooks`, Students'ın
`BranchParentLookupInterface`'ini kullandığından, `ModuleRegistry::
bootAll()`'un modülleri kayıt sırasına göre (bağımlılık sırasına göre değil)
boot ettiği bilinen kısıtı yüzünden kaydı `add_action('init', ...)` içine
ertelendi (bölüm 42'de `WeeklyDigestHooks` için kurulan aynı desen). Tema:
duyuru formunda `datetime-local` alanı + zamanlanmış duyurular tablosu
(durum: bekliyor/gönderildi/iptal, iptal düğmesi).

### 58. Tasarım sistemi turu: token/bileşen kütüphanesi yenilenmesi

Önceki turların çoğu tek bir özelliğin ekranını kapsıyordu; bu tur bunun
yerine platformun TÜM önceden var olan panellerinin üzerine oturan tutarlı
bir tasarım-token ve bileşen katmanı ekledi — 12 ayrı alt sistem yerine tek
bir kapsamlı geçiş olarak ele alındı.

**Token katmanı** (`theme.css`): derinlik (`--scp-shadow-0..3`), tipografi
(`--scp-text-xs..2xl`), boşluk (`--scp-space-1..12`) ve hareket
(`--scp-ease`, `--scp-duration-fast/base/slow`) ölçekleri; `prefers-
reduced-motion: reduce` global olarak tüm animasyon/geçiş sürelerini
`0.01ms`'e indiriyor. Karanlık mod (`prefers-color-scheme: dark`) elevation
gölgeleri eklendi.

**Paylaşılan JS yardımcı kütüphanesi** (`assets/js/scp-ui-kit.js`, yeni) —
bu temanın bundler'ı olmadığından `scp-api-fetch.js`'in ağ çağrıları için
oynadığı rolün bileşen tarafındaki karşılığı: `window.scpToast()`,
`window.scpModal()` (Promise tabanlı `confirm()` yerine geçen action-sheet/
modal), `window.scpKebabMenus()` (bağlam menüsü otomatik bağlama),
`window.scpAnimateCounter()`, `window.scpSkeletonRows()`,
`window.scpSuccessPulse()`, `window.scpQuicknavReorder()` (quicknav'ın
sürükle-bırak sırası, yalnızca istemci tarafında `localStorage`'da
saklanıyor — bölüm bağlantılarının GÖRÜNÜRLÜĞÜ hâlâ tamamen sunucu
tarafında capability kontrolünden geliyor, bu yalnızca zaten erişilebilen
bölümlerin sırasını değiştiriyor) ve bir Cmd+K komut paleti. Komut paleti
kendi capability mantığını YAZMIYOR — `.scp-quicknav a` bağlantılarını
(zaten sunucu tarafında doğru şekilde izin-filtrelenmiş) DOM'dan tarayarak
listesini oluşturuyor, `zone.php`'nin `$scp_sections` mantığını JS'te
tekrarlamak yerine.

**Modül kimliği** (`.scp-module-tile`, `panel.css`): platformun ~10 temel
modülüne (öğrenciler/şubeler/ürünler/siparişler/fiyatlandırma/depo/hakediş/
raporlar/destek/ayarlar) özgü renkli ikon karoları. İkonlar yeni
`templates/partials/icon.php`'deki `scp_module_icon_svg()`'den geliyor —
harici bir ikon kütüphanesi yerine ~10 glif için elle yazılmış minimal
outline SVG yolları (bölüm 48'in "dar bir ihtiyaç için ağır bir bağımlılıktan
kaçın" ilkesiyle aynı gerekçe). `zone.php`'nin quicknav döngüsü artık
`$scp_section_variants` eşlemesiyle bilinen bölümleri karo, kalanları düz
bağlantı olarak render ediyor.

**Doğrulama sınırı**: bu ortamda çalışan bir WordPress+tarayıcı kurulumu
yok; bu turun doğrulaması `php -l`/`vendor/bin/phpcs` (tüm dokunulan PHP
dosyaları, 0 hata), `node --check` (tüm dokunulan/yeni JS dosyaları) ve
CSS için elle yazılmış bir süslü-parantez dengesi betiğiyle sınırlı —
GÖRSEL/işlevsel tarayıcı testi YAPILMADI. Bu yüzden yeni altyapı (toast,
modal, komut paleti, sürükle-bırak, animasyonlu sayaç) bilinçli olarak
riskten kaçınan bir stratejiyle sadece birkaç gerçek, izole dokunma
noktasına bağlandı (`header.php`, `notifications-bell.js`,
`overview-panel.js`, `account-security.js`'in şifre değişikliği başarı
akışı, `zone.php`'nin quicknav'ı) — daha önce test edilmiş her panel
scripti'ni bu altyapıyı kullanacak şekilde yeniden yazmak, tarayıcıda
doğrulanamayan bir regresyon riski olarak görüldü ve bilinçli olarak
yapılmadı.

### 59. Görsel sadeleştirme turu: tek vurgu rengi, gradyanların kaldırılması, daha az "süs"

Bölüm 58'in bileşen turundan sonra kullanıcı isteği: "daha kullanıcı dostu
ve daha da sadeleştirilmiş bir UI". Kapsam kullanıcıyla netleştirildi -
sayfa yapısını/buton sayısını DEĞİŞTİRMEYEN, yalnızca görsel gürültüyü
azaltan bir geçiş (bilgi yoğunluğunu azaltma - form adımlaştırma, ikincil
eylemleri gizleme gibi yapısal değişiklikler bilinçli olarak kapsam DIŞI
bırakıldı). Yalnızca `theme.css`/`panel.css`/`auth.css`/`woocommerce.css`
dokunuldu - hiçbir PHP/JS dosyası değişmedi, bu yüzden riski özellikle
düşük (markup/davranış aynı, yalnızca token değerleri ve birkaç kural).

**Modül kimliği karoları tek vurgu rengine indirgendi**: bölüm 58'in 10
farklı doygun renkli `.scp-module-tile--*` kuralı (mavi/mor/yeşil/turuncu/
camgöbeği/kahve/bordo/indigo/turkuaz/gri) TAMAMEN kaldırıldı; artık tüm
modül karoları `--scp-primary-bg` (soluk mavi zemin) üzerinde
`--scp-primary` renkli tek bir ikon stiliyle render ediliyor. Modüller
artık renkle değil ikon şekli + etiketle ayırt ediliyor - "bir modülü
hatırlamak için hangi rengi aradığını bilmen gerekmiyor" ilkesi.
`zone.php`'deki `scp-module-tile--{variant}` sınıf isimleri markup'ta
kalmaya devam ediyor (zararsız, artık hiçbir CSS kuralı onları hedeflemiyor)
- gereksiz bir PHP değişikliğinden kaçınmak için silinmedi.

**Dekoratif gradyanlar düzleştirildi**: üst menünün altındaki iki renkli
3px gradyan şerit (`.scp-site-header::after`) tamamen kaldırıldı (header
zaten `border-bottom` + gölgeyle ayrışıyordu, şerit saf süstü); marka
işareti ("S" kutusu, hem `header.php`'de hem giriş ekranında) diyagonal
iki tonlu gradyandan düz `--scp-primary`'ye indirgendi; giriş ekranının
arka planındaki iki radial gradyan kaldırılıp düz `--scp-bg`'ye
indirgendi. Fonksiyonel gradyanlar (iskelet yükleme shimmer'ı gibi,
bölüm 58) DOKUNULMADI - yalnızca saf dekoratif olanlar.

**Hover "sıçrama" azaltıldı**: modül karoları ve mağaza sayfasındaki ürün
kartlarının hover'daki `translateY` kaldırma efekti kaldırıldı (yalnızca
kenarlık/gölge değişimi kaldı) - daha sakin, daha az "zıplayan" bir
etkileşim hissi.

**Elevation orantılandı**: bildirim paneli açılır menüsü daha önce
`--scp-shadow-3` (modal/komut paleti seviyesindeki EN ağır gölge)
kullanıyordu; bir açılır menü için orantısız ağırdı - kebab menüsüyle
tutarlı olacak şekilde `--scp-shadow-2`'ye indirildi.

**Doğrulama**: yalnızca CSS değişti; `node -e` ile süslü parantez dengesi
(4 dosya, hepsi 0) doğrulandı. Görsel/işlevsel tarayıcı testi yine
YAPILMADI (bölüm 58'deki aynı ortam kısıtı) - ama bu turun tüm
değişiklikleri saf değer değişimleri (renk/gölge/transition kaldırma)
olduğundan, sözdizimi/denge doğrulaması ötesinde bir işlevsel regresyon
riski taşımıyor.

### 60. Ürün sahipliği (şube bazlı görünürlük), mağazada dinamik fiyat, taban fiyat genişletmesi, Ürünler panelinde tıkla-düzenle yapısı

Dört parçalı bir istek, dördü de Seviye Commerce'in "paylaşımlı katalog"
modelini (bölüm 38-39: tüm ürünler tek bir ortak katalogda, her şube
kendi öğrencisi için ayrı ayrı aktif/pasif yapabilir) temelden değiştirmeden
üzerine kuruldu - var olan hiçbir ürün/kural, bu turdan önce olduğu gibi
davranmaya devam ediyor (geriye dönük uyumluluk aşağıda açıklanıyor).

**Ürün sahipliği** (yeni `Support\ProductOwnership`): bir ürünü hangi
şubenin oluşturduğu, yeni bir tablo yerine sıradan bir WooCommerce post
meta'sı (`_scp_owner_branch_id`) olarak tutuluyor - ürünler zaten tamamen
WooCommerce'in kendi verisi (`ProductsRestController`'ın kendi doc
yorumu), yeni bir tablo bu ilkeyi bozardı. Meta'nın YOK OLMASI "Genel
Merkez'e ait" anlamına geliyor - bu turdan önce oluşturulmuş HER ürünün
zaten sahip olduğu varsayılan durumla birebir aynı, yani geriye dönük bir
taşıma/migration script'ine gerek yok: eski bir ürün yeni davranışta hiçbir
şekilde farklılaşmıyor. `ProductsRestController::store()` artık oluşturan
kullanıcının şubesi varsa (Şube Müdürü) o şubeyi sahip olarak kaydediyor;
Genel Merkez/Bölge Müdürü oluşturursa meta hiç yazılmıyor.

**Mağaza görünürlüğü** (`ProductVisibilityHooks`): bölüm 38'in "opt-out"
modelinin (satır yoksa aktif kabul et) ÖNÜNE sert bir sahiplik kapısı
eklendi - sahibi olan bir ürün, velinin çocuklarının HİÇBİRİ o şubede
değilse görünmez, o ürünün kendi aktif/pasif toggle durumu ne olursa
olsun. Sahibi olmayan (Genel Merkez) bir ürün bu kapıdan hiç etkilenmiyor,
eskisi gibi opt-out modeliyle çalışmaya devam ediyor.

**Mağazada dinamik fiyat** (yeni `Http\StorefrontPriceDisplayHooks`):
`PriceResolverInterface` (bölüm 13) şimdiye kadar yalnızca sepet
hesaplaması sırasında (`woocommerce_before_calculate_totals`) danışılıyordu
- bir şubenin ürüne verdiği özel fiyat, ürün sepete eklenene kadar mağaza/
ürün sayfasında hiç görünmüyordu. Bu, WooCommerce'in `get_price_html()`'inin
(ve dolayısıyla her mağaza/ürün şablonunun) zaten geçtiği
`woocommerce_product_get_price` filtresine bağlanarak çözüldü. Mağazada
gezinirken henüz bir öğrenci seçilmediğinden (o yalnızca sepete eklerken
seçiliyor) yalnızca ŞUBE bazlı çözümleme yapılabiliyor - velinin birden
çok şubede çocuğu varsa EN UCUZ sonuç gösteriliyor ("başlangıç fiyatı"
önizlemesi; sepete eklendiğinde CartPricingService daha spesifik bir
öğrenci kuralını hâlâ doğru şekilde uyguluyor). Sepet/checkout/AJAX
istekleri bilinçli olarak hariç tutuldu (`is_cart()`/`is_checkout()`/
`DOING_AJAX`) - CartPricingService orada fiyatı zaten öğrenci bazlı ve
doğru şekilde `set_price()` ile belirliyor; bu yeni filtre oraya karışırsa
daha kaba (şube bazlı) bir sonuçla doğru öğrenci fiyatının üzerine
yazabilirdi.

**Taban fiyat genişletmesi** (`PricingRestController::violatesBasePriceFloor()`,
bölüm 39'da kurulmuştu): önceden yalnızca AÇIK bir GENERAL fiyat kuralı
varsa bir taban oluşturuyordu - bir ürünün kendi temel fiyatının (WC
`regular_price`) hiçbir zaman taban olarak sayılmadığı bir boşluk vardı.
Şimdi GENERAL kural yoksa ve ürün Genel Merkez'e aitse (bkz. yukarıdaki
sahiplik), ürünün kendi temel fiyatı tabana geriye düşüyor. Pricing bunu
Commerce'in verisine bir Contract/DI bağımlılığıyla DEĞİL,
`apply_filters('scp_commerce_product_owner_branch_id'/'scp_commerce_product_base_price', ...)`
üzerinden okuyor (yeni `Http\ProductOwnershipBridge`'in yayınladığı) -
Commerce zaten Pricing'e bağımlı olduğundan (CartPricingService), ters
yönde bir Contract iki eklentiyi birbirine bağımlı hale getirirdi;
bölüm 39'daki filtre-köprüsü deseninin (tedarikçi portalı) aynısı. Commerce
etkin değilse filtreler varsayılanı (null) döner, taban kontrolü sessizce
devre dışı kalır - Pricing bağımsız çalışmaya devam eder.

**Ürünler panelinde tıkla-düzenle yapısı**: `ProductsRestController::
canManageProductFully()` artık HQ için hep true, bir Şube Müdürü için ise
YALNIZCA kendi oluşturduğu üründe true (asla bir Genel Merkez ürününde,
asla başka bir şubenin ürününde) - önceden bu tamamen HQ-only'ydi, bir
şube kendi oluşturduğu ürünü bile sonradan düzenleyemiyordu. Yanıt artık
her ürün için sunucu tarafında hesaplanmış bir `can_manage` bayrağı
taşıyor; `products-panel.js` bunu ayrı bir yetki mantığı yazmadan doğrudan
kullanıyor - `can_manage` true olan bir satıra TIKLAMAK (ya da "Düzenle"
düğmesine basmak) aynı düzenleme yapısını (isim/fiyat/stok/görsel/varyant)
açıyor, "Sil" düğmesi de aynı bayrakla gösteriliyor/gizleniyor ("ürün
Genel Merkez'den oluşturulduysa silemez" - artık tam olarak bunu ifade
ediyor). Satır içi düğmeler (`event.stopPropagation()`) satır tıklamasını
tetiklemiyor. "Şubeler" (HQ'nun herhangi bir ürünün TÜM şube durumlarını
yönettiği ekran) ve bir Şube Müdürü'nün KENDİ şubesi için tek toggle'ı
bilinçli olarak sahiplikten bağımsız bırakıldı - kapsam dışı, kullanıcının
isteği yalnızca tam düzenleme/silme hakkını kapsıyordu.

**Doğrulama**: Commerce (17) ve Pricing (32) PHPUnit paketleri değişmeden
yeşil (dokunulan sınıfların tamamı WP/WC'ye dokunan adapter'lar -
`PricingRestController`/`ProductsRestController` gibi REST controller'lar
bu kod tabanında zaten hiç birim testli değil, bkz. "Test stratejisi");
repo geneli phpcs 0 hata. Görsel/işlevsel tarayıcı testi bu ortamda yine
mümkün değil (bölüm 58'deki aynı kısıt).

### 61. Ürünler panelinde gömülü fiyat kuralı düzenleyici + tıklanamayan satır geri bildirimi

İki küçük ama doğrudan kullanıcı geri bildirimine dayanan düzeltme.

**Tıklanamayan satır artık sessiz değil**: bölüm 60'ta `can_manage=false`
olan bir satıra tıklamak hiçbir şey yapmıyordu - "tıkladım ama açılmadı"
ile "bu ürünü düzenleme yetkim yok" birbirinden ayırt edilemiyordu. Artık
HER satır tıklanabilir: yönetilebiliyorsa aynı düzenleme yapısını açıyor,
yönetilemiyorsa `data-scp-products-status` alanında hangi şubenin (ya da
Genel Merkez'in) sahibi olduğunu gösteriyor.

**Fiyat kuralları artık Ürünler panelinin içinde**: önceden bir ürünün
fiyat kuralını (genel/şube/öğrenci) düzenlemek için ayrı bir "Fiyat
Kuralları" bölümüne gidip ürün ID'sini elle yazıp "Fiyatları Getir"
demek gerekiyordu. Artık mevcut bir ürünü düzenlemek için tıklandığında,
aynı yapının içinde o ürüne ait fiyat kuralları da otomatik yükleniyor -
elle ID girmeye gerek yok. Bu saf bir tema/JS birleştirmesi -
`products-panel.js` `seviye/v1/pricing/rules/*`'a doğrudan bir REST
çağrısıyla konuşuyor (Commerce ile Pricing arasında PHP bağımlılığı
YOK, iki eklenti birbirinden habersiz kalmaya devam ediyor). Yeni
localize edilen `scpPanel.canManagePricing`/`canManageBasePricing`
bayrakları (`scp_manage_pricing`/`scp_manage_base_pricing`) gömülü
düzenleyicinin görünürlüğünü/GENEL kapsam seçeneğini, standalone Fiyat
Kuralları panelininkiyle BİREBİR aynı kurallarla kontrol ediyor.

Standalone "Fiyat Kuralları" bölümü (elle ID arama + CSV toplu içe
aktarma) BİLİNÇLİ OLARAK olduğu gibi bırakıldı, kaldırılmadı - şu iki
sebep: (1) CSV toplu içe aktarma tek bir ürünle ilgili değil, mantıklı
bir "ürün düzenleme yapısı" yeri yok; (2) `scp_manage_pricing`'i olup
`scp_manage_products`'ı OLMAYAN tek rol (Sistem) Ürünler panelinin
düzenleme yapısına hiç erişemiyor (salt-okunur ürün listesi görüyor) -
onlar için tek yol hâlâ bu standalone panel. Yani bu tur saf katkı
(additive): var olan hiçbir yetki/akış kaldırılmadı, yalnızca
Commerce+Pricing'e aynı anda erişimi olan roller için daha hızlı bir
yol eklendi.

**Doğrulama**: yalnızca tema (JS/PHP) değişti, hiçbir plugin dosyası
dokunulmadı - bu yüzden plugin PHPUnit paketlerinin yeniden çalıştırılmasına
gerek yoktu. `node --check`/`php -l`/`vendor/bin/phpcs` hepsi temiz (0
hata, yalnızca önceden var olan kabul edilmiş bir satır-uzunluğu uyarısı).

### 62. Ürünler bağımsız bir sayfaya taşındı

"Ürünler için ayrı bir sayfa yapıp dinamik bir şekilde ürünleri
geliştirebilecek bir yapı" isteği - bölüm 35'in Sipariş Yönetimi'ni
`/admin`/`/sube` panosunun içindeki bir bölümden `/admin/siparisler` +
`/sube/siparisler` bağımsız bir sayfaya taşıdığı DEĞİŞİKLİĞİN birebir
aynısı, aynı gerekçeyle: oluşturma/düzenleme/varyant/fiyat-kuralı gibi
alt yapıları olan bir katalog, panodaki bir düzine diğer kartla yer
paylaşan bir kart yerine gerçek bir sayfaya ihtiyaç duyuyor.

**Yeni rota**: `/admin/urunler`, `/sube/urunler` (yeni
`scp_admin_products_path()`, `inc/zones.php`) - `siparisler` rotasının
zaten kayıtlı `^admin/(.+)/?$`/`^sube/(.+)/?$` rewrite kurallarını (
`scp_zone_path`'e yakalanan) yeniden kullanıyor, YENİ bir üst düzey
rewrite kuralı eklemeye gerek yok. Erişim `scp_manage_products`/
`scp_view_products`'a bağlı, aksi halde `/admin` ya da `/sube`'ye geri
yönlendiriliyor - `siparisler` rotasının izin kontrolüyle birebir aynı
desen.

**Taşıma, kopyalama değil**: `zone.php`'nin eski `#scp-products-panel`
bölümünün TAMAMI (249 satır - ürün formu, gömülü fiyat kuralı düzenleyici
bölüm 61, varyant paneli, şube durumu paneli) `zone.php`'den silinip
yeni `templates/products-admin.php`'ye taşındı; `id="scp-products-panel"`
ve tüm `data-scp-*` seçiciler AYNEN korundu, bu yüzden `assets/js/
products-panel.js` hiç değişmeden çalışmaya devam ediyor -
`getElementById('scp-products-panel')` artık farklı bir sayfada bulunuyor
olsa da script'in kendisi bunu bilmiyor/bilmesine gerek yok.
`inc/assets.php`'nin script enqueue koşulu da `admin-orders-panel.js`'in
zaten kullandığı `$isAdminOrdersPage` desenini birebir taklit eden yeni
bir `$isProductsPage` koşuluna (`scp_zone_path === 'urunler'`) geçirildi -
artık yalnızca bu yeni sayfada yükleniyor, önceden olduğu gibi HER
`/admin`/`/sube` sayfa yüklemesinde değil.

Quicknav'daki "Ürünler" girdisi ve modül-kimliği karo eşlemesi
(`$scp_section_variants`) `#scp-products-panel` yerine artık
`scp_admin_products_path()`'e işaret ediyor - Siparişler girdisinin
`scp_admin_orders_path()`'i kullanmasıyla birebir aynı desen.

**Doğrulama**: `diff` ile eski/yeni `zone.php` karşılaştırılıp SİLİNEN
blok satır satır (249 satır) doğrulandı - taşınan içerikte hiçbir
karakter kaybı/değişikliği olmadığından emin olmak için. Yalnızca tema
değişti, plugin dosyası yok; `php -l`/`vendor/bin/phpcs` (repo geneli,
0 hata) temiz.

### 63. Ürünün kendi ayrı düzenleme sayfası (`/urunler/{id}`, `/urunler/yeni`)

"Ürünün üzerine tıklandığında o ürünün düzenleme sayfası gelsin. Ürün
düzenlenme aşamasında tüm düzenlemeler yapılabilsin." isteği - bölüm 61'in
gömülü/inline formu (liste sayfasının İÇİNDE açılıp kapanan bir form) bir
adım öteye taşınıyor: artık gerçek, kendi URL'i olan bir sayfa. Liste
(`templates/products-admin.php`) ve düzenleme artık iki ayrı sayfa, iki
ayrı script.

**Yeni rotalar** (`inc/zones.php`, hâlâ `siparisler`/`urunler` rotalarının
kullandığı `^admin/(.+)/?$`/`^sube/(.+)/?$` yakalamasını paylaşıyor, yeni
bir üst düzey rewrite kuralı yok):
- `/admin/urunler`, `/sube/urunler` → liste (değişmedi)
- `/admin/urunler/yeni`, `/sube/urunler/yeni` → boş oluşturma formu
- `/admin/urunler/{id}`, `/sube/urunler/{id}` → o ürünün kendi düzenleme
  sayfası

Üç alt yol da tek bir `urunler` ön ekinin altında, `scp_zone_path`'in
`urunler` sonrasındaki kısmı (`$productSubPath`) ayrıştırılarak
yönlendiriliyor. Düzenleme/oluşturma sayfası YAZAR - `scp_view_products`
sahibi salt-okunur bir rol (Muhasebe/Depo/Sistem) buraya hiç giremiyor,
`scp_manage_products` yoksa listeye geri yönlendiriliyor; `yeni` de doğru
bir pozitif tamsayı da olmayan bir alt yol (bozuk/typo bir URL) aynı
şekilde listeye geri düşüyor. `scp_admin_product_new_path()` ve
`scp_admin_product_edit_path(int $productId)` yeni yardımcı fonksiyonlar,
`scp_admin_products_path()`'in yanında.

**Liste artık sadece liste**: `templates/products-admin.php`'den ürün
formu, varyant paneli ve gömülü fiyat kuralı düzenleyici (bölüm 61)
tamamen kaldırıldı - yalnızca tablo ve HQ'nun şube-bazlı durum grid'i
(`data-scp-product-branches-panel`) kaldı. "Yeni Ürün" artık bir JS
düğmesi değil, doğrudan `scp_admin_product_new_path()`'e giden bir link.

**Düzenleme sayfası** (`templates/product-edit.php`, yeni): ürünün kendi
formu (ad/fiyat/açıklama/kategori/stok/görsel), varyant paneli ve (
`scp_manage_pricing` sahibiyse) fiyat kuralları paneli - hepsi TEK sayfada,
"Vazgeç" düğmesi yok (sayfanın kendisi zaten "düzenleme hâli", inline
formdan çıkmaya gerek yok) ve varyant paneli artık bir düğmeyle açılan bir
şey değil, ürünün tipine göre script'in kendisi gösterip/gizliyor.

**Script ikiye bölündü**:
- `products-panel.js` (liste) - form/fiyat-kuralı/varyant mantığının HEPSİ
  kaldırıldı; satır tıklaması artık inline form açmak yerine
  `scpPanel.productsBasePath + '/' + product.id`'ye (yeni localize edilen
  `productsBasePath`, `scp_admin_products_path()`'ten) yönlendiriyor -
  `can_manage=false` olan bir satır hâlâ bölüm 60'takiyle aynı "Bu ürünü
  yalnızca X düzenleyebilir" mesajını gösterip yönlendirmiyor. Şubeler
  paneli/durum değiştirme mantığı (HQ'nun grid'i + Şube Müdürü'nün kendi
  şubesi için tek buton) AYNEN kaldı, çünkü bu ikisi listede kalmaya devam
  ediyor.
- `product-edit-panel.js` (yeni) - bölüm 61'in inline form/varyant/fiyat
  kuralı mantığının BİREBİR taşınmış hâli, artık `openProductForm()`
  kapatma/açma yerine sayfa yüklendiğinde tek seferlik çalışıyor: DOM'daki
  `#scp-product-edit-panel[data-scp-product-id]`'den id'yi okuyor, id
  varsa `GET commerce/products/{id}` ile ürünü çekip formu dolduruyor, id
  yoksa boş oluşturma formuyla başlıyor. Yeni ürün kaydedilince
  `scpPanel.productsBasePath + '/' + result.data.id`'ye yönlendiriyor
  (varyant/fiyat kuralı düzenlemesi gerçek bir id ister). Silme başarılı
  olunca listeye (`scpPanel.productsBasePath`) yönlendiriyor.

**Sahiplik yeniden kontrolü, ikinci bir savunma katmanı olarak**:
`inc/zones.php`'nin düzenleme sayfası rotası yalnızca genel
`scp_manage_products` capability'sini kontrol ediyor - ÜRÜN BAZLI sahiplik
kontrolü değil (bunu yapmak, sadece yönlendirme kararı için tema rotalama
kodundan Commerce'e girmek anlamına gelirdi - REST katmanı zaten bunu
güvenli şekilde reddediyor). Yani bir Şube Müdürü, kendi oluşturmadığı bir
ürünün `/urunler/{id}` linkine (ör. eski bir link, elle yazılmış bir URL)
YİNE DE ulaşabilir. `product-edit-panel.js` bu durumu backend'in
`serialize()`'ının hesapladığı `can_manage` bayrağıyla ele alıyor: `false`
ise formun tüm alanlarını `disabled`, "Ürünü Sil" düğmesini gizli, varyant
girişlerini salt-okunur, fiyat kuralları panelini tamamen gizli yapıp
bölüm 59'daki AYNI "Bu ürünü yalnızca X düzenleyebilir" mesajını
gösteriyor - bir PUT/DELETE'in sessizce 403 dönmesini beklemek yerine.

**Doğrulama**: `php -l`/`vendor/bin/phpcs` (repo geneli, 0 hata) ve
`node --check` (her iki script) temiz. Backend (Commerce/Pricing plugin
PHP'si) bu turda HİÇ değişmedi - yalnızca tema; bu yüzden plugin zip'leri
yeniden derlenmedi, yalnızca tema zip'i.

### 64. Quicknav gruplandırması + tasarımda "daha canlı/zengin" tur

"Genel menü yapısı daha anlaşılır bir yapıda olsun. Kullanıcı odaklı. UI
Tasarımı daha çok güzel yap." isteğinin iki parçası, kullanıcıyla
netleştirilen kapsamla:

**1. Quicknav gruplandırması** (`templates/zone.php`). `$scp_sections`
artık tek düz `href => label` listesi değil, yedi mantıksal kümeye ayrılmış
bir yapı (`$scp_sections[$grup][$href] = $label`): `genel` (Genel Bakış,
başlıksız - tek öğeye başlık koymak gürültü olurdu), `katalog` (Ürünler,
Fiyat Kuralları, Kampanya Kodları), `operasyon` (Siparişler, Depo),
`kisiler` (Öğrenciler, Şubeler), `finans` (Cari Bakiye, Raporlar),
`iletisim` (Toplu Duyuru, Destek Talepleri), `hesap` (Hesap Güvenliği,
KVKK, IP Kısıtlaması, SMS/E-posta Ayarları, API Anahtarları, Görünüm,
Aktivite Günlüğü). `$scp_group_labels` her grubun başlığını taşıyor.
Genel Merkez gibi çoğu capability'ye sahip bir rol artık kayıt sırasına
göre dizilmiş bir düzine aynı görünen pil yerine ilişkili girdileri bir
arada görüyor.

Gruplama SALT görsel: `.scp-quicknav__group-label` bir `<span>`, `<a>`
etiketlerinin arasına serpiştirilmiş bir başlık - HER `<a>` hâlâ
`.scp-quicknav`'ın DOĞRUDAN çocuğu (kendi sarmalayıcı `<div>`'i yok). Bu
bilinçli bir tercih: `scpQuicknavReorder()`'ın (assets/js/scp-ui-kit.js)
sürükle-bırak mantığı `nav.querySelectorAll('a')`/`nav.insertBefore(dragged,
...)` ile çalışıyor - `<a>`'ları başka bir `<div>` grubunun içine
sarmalasaydık bu kod ya kırılırdı ya da yeniden yazılması gerekirdi.
Grup başlığı bir `<span>` olduğu için hem sürükle-bırak'ın hem de komut
paletinin (`document.querySelectorAll('.scp-quicknav a')`) tarama mantığı
DEĞİŞMEDEN çalışmaya devam ediyor - bir kullanıcı bir linki sürükleyip
başka bir grubun yanına bırakırsa (kozmetik bir kenar durumu, zaten
"yalnızca istemci tarafında kalıcı" bir kişiselleştirme) görsel olarak o
gruba "taşınmış" görünür, işlevsel bir sorun değil.

CSS: `.scp-quicknav__group-label` `flex-basis: 100%` ile flex-wrap satırını
zorla kırıp kendi satırına geçiyor - küçük, büyük harf, soluk renkli bir
küme başlığı.

**2. "Daha canlı/zengin görünüm"** - kullanıcı, bölüm 59'un "tek vurgu
rengi, gradyansız" sadeleştirmesini KISMEN geri almayı seçti (bölüm
165-176'nın zengin tasarım sistemine daha yakın bir görünüm). Bölüm 59
commit'inin (`43f714c`) module-tile/header/auth/woocommerce hunk'ları
`git apply -R` ile TERSİNE çevrilip aynen eski haline döndürüldü (10 farklı
modül rengi `.scp-module-tile--{variant} .scp-module-tile__icon`'a geri
geldi, header marka işareti ve giriş ekranı logosu gradyan+gölgeye geri
döndü, header'ın altındaki 2 renkli gradyan çizgi geri geldi, modül
karosu/ürün kartı hover'ındaki hafif kaldırma (`translateY`) animasyonu
geri geldi, bildirim panelinin gölgesi `--scp-shadow-3`'e geri döndü) -
YALNIZCA `.scp-status--error`'ın `var(--scp-danger)` token'ı KORUNDU (o
hunk bir tasarım-token temizliğiydi, "sade/zengin" ekseniyle ilgisizdi,
geri almanın bir anlamı yoktu).

**Doğrulama**: `php -l`/`vendor/bin/phpcs` (repo geneli, 0 hata) temiz;
dokunulan CSS dosyalarının `{`/`}` sayıları eşit. Yalnızca tema (zone.php +
4 CSS dosyası) - plugin zip'leri yeniden derlenmedi.

### 65. "Burada her bir menü için ayrı bir sayfa yap" - /admin, /sube panosunun tamamı bağımsız sayfalara ayrıldı

Bölüm 35 (Sipariş Yönetimi) ve bölüm 62 (Ürünler) daha önce tek tek
standalone sayfaya taşınmıştı; bu turda AYNI dönüşüm `templates/zone.php`
panosunda kalan HER BÖLÜME uygulandı: Öğrenciler, Şubeler, Fiyat
Kuralları, Kampanya Kodları, Depo, Cari Bakiye, Raporlar, Toplu Duyuru,
Destek Talepleri (personel kuyruğu), Hesap Güvenliği, Verilerim (KVKK),
KVKK Talepleri (personel kuyruğu), IP Kısıtlaması, SMS Ayarları, E-posta
Ayarları, API Anahtarları, Görünüm, Aktivite Günlüğü - 18 bölüm.

**"Genel Bakış" bilinçli olarak taşınmadı**: `/admin`/`/sube` KÖKÜ hâlâ
bu bölümün kendi içeriği - sadece bir quicknav gösteren boş bir kök sayfa,
üzerine bir tık daha eklemeden bir şey gösteren bir panodan daha kötü bir
kullanıcı deneyimi olurdu. Kökte Genel Bakış'a erişimi olmayan bir rol
(nadiren) artık "Yukarıdaki menüden bir bölüm seçin." boş durumu görüyor
- eskiden Öğrenciler bölümünün `else` dalındaki (artık anlamsız kalacak)
"Bu panelin içeriği... burada yer alacak" yer tutucusunun yerini alıyor.

**Tek bir paylaşılan rota tablosu, 17 neredeyse özdeş dal yerine**:
`inc/zones.php`'ye yeni `scp_menu_pages(): array` fonksiyonu eklendi -
slug => `{zones, capability (closure), template}` eşlemesi. Sipariş
Yönetimi/Ürünler'in ayrı ayrı yazılmış yönlendirme bloklarının aksine, 17
bölümün HEPSİ `scp_render_zone_template()`'deki TEK bir döngüden geçiyor:
`$scp_menu_pages[$zonePath]` varsa capability kontrol edilip (başarısızsa
`/admin` ya da `/sube`'ye geri yönlendirme) ilgili template include
ediliyor. 17 kez tekrar eden "capability kontrolü + zone kısıtlaması +
template include" kalıbı için bu tekrar sayısı bir tabloyu haklı
çıkarıyor - kod tabanının genelindeki "üç benzer satır bir soyutlamadan
iyidir" ilkesinin istisnası, çünkü burada 17 benzer BLOK var.
`scp_menu_page_path(string $slug): string` yardımcı fonksiyonu her
slug'ın CURRENT zone'daki URL'ini (`/admin/{slug}` ya da `/sube/{slug}`)
üretiyor - `scp_admin_products_path()` ailesiyle aynı desen.

**Markup birebir taşındı, değiştirilmedi**: her bölümün `id`/`data-scp-*`
seçicileri AYNEN korunarak kendi `templates/{slug}-admin.php` dosyasına
taşındı, bu yüzden ilgili JS dosyaları (`students-panel.js`,
`branches-panel.js`, `pricing-panel.js`, `coupons-panel.js`,
`depo-panel.js`, `hakedis-panel.js`, `reports-panel.js`,
`broadcast-panel.js`, `support-tickets-panel.js`,
`account-security.js`, `privacy-requests-panel.js`,
`ip-allowlist-panel.js`, `notifications-settings-panel.js`,
`email-settings-panel.js`, `api-keys-panel.js`, `branding-panel.js`,
`activity-log-panel.js`) HİÇBİRİ değiştirilmedi - `getElementById` artık
farklı bir sayfada bulduğu elemente bağlanıyor olsa da script'in kendisi
bunu bilmiyor/bilmesine gerek yok, bölüm 62'nin Ürünler dönüşümündeki
AYNI ilke.

**İki paylaşılan partial, iki ince "kabuk" sayfa**: "Hesap Güvenliği" ve
"Verilerim (KVKK)" `templates/partials/account-security.php` ve
`templates/partials/privacy-requests.php` partial'larını hem
`templates/zone.php`'den (artık kaldırıldı) HEM DE
`templates/parent-dashboard.php`'den (/profilim, DOKUNULMADI)
paylaşıyordu. Bu ikisi için yeni `templates/account-security-admin.php`/
`templates/privacy-requests-admin.php` sadece standart sayfa kabuğunu
(`<h1>` + "Panele Dön" linki) sarıp AYNI partial'ı include ediyor -
partial'ın kendisi (ve onu bağlayan script) değişmedi, /profilim hâlâ
aynı partial'ı kullanmaya devam ediyor.

**İkinci bir savunma katmanı, Ürünler dönüşümündeki AYNI ilke**:
`scp_menu_pages()`'in yönlendirme döngüsü yalnızca capability +
zone'u kontrol ediyor, URL'i elle yazan/eski bir linki tıklayan bir
kullanıcı için ekstra bir state kontrolü yok - REST katmanı zaten her
şeyi kendi başına doğru şekilde reddediyor, sayfanın kendisi sadece
"buraya hiç girmemeliydin" durumunu 403 yerine düzgün bir yönlendirmeye
çeviriyor.

**`inc/assets.php`: 17 script artık HER `/admin`/`/sube` yüklemesinde
değil, yalnızca KENDİ sayfasında yükleniyor**. Paylaşılan
`$zonePath = rtrim((string) get_query_var('scp_zone_path'), '/');`
fonksiyonun başında bir kez hesaplanıyor (önceden Ürünler/Siparişler
blokları kendi yerel kopyalarını hesaplıyordu, şimdi hepsi bunu paylaşıyor)
ve her script'in koşuluna `$zonePath === '{slug}'` eklendi. "Genel Bakış"ın
scripti (`overview-panel.js`) İSTİSNA - kökte kaldığı için
`$zonePath === ''`e bağlı. Üç script BİLİNÇLİ OLARAK KOŞULSUZ kaldı
(`account-security.js`, `privacy-requests-panel.js`,
`support-tickets-panel.js`) - bunlar zaten ÜÇ farklı sayfada (iki yeni
`/admin`, `/sube` sayfası + `/profilim`, ya da self-service+personel
kuyruğu farklı sayfalarda) DOM elemanına bağlanıyor, script'in kendisi
her elementin varlığını kontrol ediyor - bu, `scp-privacy-requests-panel`/
`scp-support-tickets-panel` için turdan ÖNCE de zaten belgelenmiş bir
kalıptı, yeni bir istisna değil.

**Doğrulama**: `php -l` (repo geneli, dokunulan/yeni her PHP dosyası) ve
`vendor/bin/phpcs` (repo geneli, 0 hata - yeni satırların ikisi 120
karakteri aştığı için `if (\n ... \n)` şeklinde satırlara bölündü) temiz.
Eski `#scp-*-panel` çapa referanslarının hiçbiri (tema genelinde `grep`
ile doğrulandı) kalmadı. Yalnızca tema değişti, plugin dosyası yok - plugin
zip'leri yeniden derlenmedi.

### 66. Sol menü + Ürünler erişim düzeltmesi + etkileşimli trend grafiği + Fiyat Kuralları ürün seçici/Excel içe aktarma + sınıf bazlı ürün görünürlüğü

Tek bir kullanıcı mesajında toplanmış 5 ayrı istek, bölüm 65'in "her bölüm
kendi sayfasında" dönüşümünün hemen ardından gelen bir kullanılabilirlik
turu.

**a) Menü üstten sola taşındı**: `templates/zone.php`'nin quicknav'ı (bölüm
64'ün gruplaması) `inc/sidebar.php` (yeni) içine taşınıp `header.php`'den
(`<div class="scp-layout"><?php scp_render_sidebar(); ?><main>...`) HER
`/admin`/`/sube` sayfasında render edilecek şekilde genişletildi - bölüm
65'in ayırdığı 18 bağımsız sayfanın kendi menüsü YOKTU (sadece "←
Panele Dön"), artık hepsinde var. Sidebar'ın kendi `<nav>`'ı hâlâ
`scp-quicknav` sınıfını taşıyor (SADECE `scp-ui-kit.js`'nin komut paleti
`document.querySelectorAll('.scp-quicknav a')` taramasıyla uyumluluk
için) ama tüm gerçek stil artık `.scp-sidebar-nav__*` sınıflarında.
Gruplu menü öğeleri `:hover`/`:focus-within` ile CSS-only bir flyout
(`position:absolute; left:100%`) açıyor; dokunmatik/klavye için
`window.scpSidebarNav()` (tıkla-aç/kapa, akordeon tarzı `.is-open`) aynı
işlevi JS tarafında sağlıyor. Eski sürükle-bırak quicknav sıralama
özelliği (`scpQuicknavReorder`, localStorage) TAMAMEN kaldırıldı - düz
pilleri sürüklemek anlamlıyken, artık iç içe grup/flyout yapısında
sürükleme mantıklı bir etkileşim modeli sunmuyordu.

**b) Ürünler'de tıklanacak yer yoktu - kök neden bir yetki farkıydı**:
`inc/zones.php`'nin ürün düzenleme sayfası yönlendirmesi HEM `/yeni` HEM
`/{id}` için `scp_manage_products` şart koşuyordu; oysa
`templates/product-edit.php`/`product-edit-panel.js` bölüm 61'den beri
salt-okunur render etmeyi zaten biliyordu (`can_manage=false` ise form
disabled). Yani `scp_view_products`-only bir rol (Muhasebe/Depo/Sistem)
HİÇBİR ürünün sayfasına erişemiyordu - "tıklanacak yer yok" hissi buradan
geliyordu. Düzeltme: `/yeni` (YAZAR) hâlâ `scp_manage_products` istiyor,
ama `/{id}` artık `scp_manage_products` VEYA `scp_view_products` yeterli.
`inc/assets.php`'nin script enqueue koşulu ve `products-panel.js`'nin
satır tıklama/aksiyon butonu (artık HERKESE `can_manage` durumuna göre
"Düzenle"/"Detay" etiketli bir buton gösteriyor, "bu ürünü sadece X
düzenleyebilir" ile tıklamayı ENGELLEYEN eski dal kaldırıldı - o mesaj
artık hedef sayfada zaten gösteriliyor) aynı gate'e göre güncellendi.

**c) Genel Bakış trend grafiği artık mouse'u takip ediyor**:
`overview-panel.js`'nin elle çizilmiş SVG grafiğindeki nokta başı
`<title>` (gecikmeli, sadece 3px dairenin üstünde) yerine tüm grafiği
kaplayan şeffaf bir `<rect class="scp-trend-chart__capture">`
`mousemove`/`touchmove` dinliyor, en yakın günü (`nearestCoord()`, X
mesafesine göre) bulup kesikli dikey bir `<line>` + vurgulu bir
`<circle>` + SVG DIŞINDA düz bir HTML `<div class="...__tooltip">`
gösteriyor. Tooltip'in piksel konumu SVG'nin `viewBox` birimlerinden
gerçek render genişliğine 0..1 oranıyla çevriliyor
(`preserveAspectRatio="none"` olduğu için eksenler farklı oranda
gerilmiş olabilir).

**d) Fiyat Kuralları: ürün ID yerine isimden seçim + ayrı, Excel'i de
kabul eden toplu içe aktarma**: `templates/pricing-admin.php`'nin
`<input type="number" name="product_id">`'ı `<input type="text"
name="product_search" list="scp-pricing-product-options">` +
`<datalist>` oldu - `pricing-panel.js` `commerce/products`'tan
"{ad} (#{id})" etiketleriyle listeyi dolduruyor, gönderimde tam
eşleşmeyi arıyor, yoksa sondaki rakam grubunu regex'le çekiyor
(`resolveProductId()`). "Toplu İçe Aktarma" artık ayrı bir
`scp-card--nested` içinde (önceden aynı kartta, tek satır çıplak bir
form gibiydi). Dosya girişi `.xlsx`'i de kabul ediyor:
`plugin/seviye-pricing/src/Support/XlsxToCsvConverter.php` (yeni) PHP'nin
kendi `ZipArchive`+`SimpleXMLElement`'iyle (Composer bağımlılığı YOK,
`XlsxExporter`'ın (bölüm 16) YAZMA tarafındaki "dar, iyi bilinen bir
format için ağır bir kütüphaneden kaçın" gerekçesinin OKUMA tarafı)
workbook'un ilk sayfasını düz CSV metnine çeviriyor, bu metin de
DEĞİŞMEDEN mevcut `PriceRuleImportParser::parse()`'a gidiyor - CSV ve
Excel arasında tekrarlanan doğrulama mantığı yok.
`PricingRestController::import()` artık `csv` YA DA `xlsx_base64`
kabul ediyor (ikisi de `required: false`, gövdede tam olarak biri
zorunlu); `xlsx_base64` verilirse `base64_decode` + `XlsxToCsvConverter`
ile CSV'ye çevrilip AYNI akıştan geçiyor. Yol boyunca fark edilen,
turdan ÖNCEKİ bir etiketleme hatası da düzeltildi: `pricing-panel.js`
fiyat kuralı içe aktarma özetinde YANLIŞLIKLA Öğrenciler'in "öğrenci içe
aktarıldı" metnini (`importSummary`) kullanıyordu - yeni, ayrı bir
`pricingImportSummary` string'iyle değiştirildi.

**e) Öğrenci Sınıf alanı serbest metinden menüye + ürünlerde sınıf bazlı
görünürlük filtresi**: "5. sınıftaki öğrenci için ayrı ürün, 8. sınıf
için ayrı ürün olacak" - iki parçalı bir özellik.

Ortak sözlük: yeni `theme/seviye-storefront/inc/grade-levels.php`'deki
`scp_grade_level_options(): array` - "Anasınıfı", "1. Sınıf" ... "12.
Sınıf", "Mezun" (14 seçenek). SAKLANAN DEĞER = GÖSTERİLEN ETİKET (ayrı bir
kod/id yok) - bilinçli bir tercih, çünkü `class_name` zaten sipariş
özetleri/CSV dışa aktarma/e-posta şablonlarında HAM METİN olarak
basılıyor; ayrı bir kod tabloya çeviri katmanı hem buraları bozar hem de
ürün `grade_levels` ile öğrenci `class_name`'i karşılaştırırken gereksiz
bir çeviri adımı ekler - şimdi ikisi de aynı 14 etiketten biri olduğu için
düz bir `in_array()` string eşleşmesi yeterli.

`templates/students-admin.php`'nin `<input type="text" name="class_name">`'ı
bu 14 seçenekle dolu bir `<select>` oldu. `StudentsRestController`'da
`class_name` zaten sadece `type: string` (biçim kısıtı yok) olduğundan
backend'de değişiklik gerekmedi. `students-panel.js`'nin düzenleme formu
doldurma mantığına bir geriye dönük uyumluluk önlemi eklendi: bu
değişiklikten ÖNCE serbest metinle girilmiş eski bir `class_name`
(ör. "5-A") 14 seçenekten biriyle eşleşmezse tarayıcı `<select>`'i sessizce
BOŞ gösterir - `form.class_name.selectedIndex === -1` kontrolüyle o eski
değer için geçici bir `<option selected>` enjekte edilip veri sessizce
kaybolmuyor.

Yeni Commerce özelliği - ürün bazlı sınıf filtresi:
`plugin/seviye-commerce/src/Support/ProductGradeLevels.php` (yeni,
`ProductOwnership`'i birebir yansıtıyor) `_scp_product_grade_levels` post
meta anahtarında bir etiket dizisi tutuyor - boş dizi (ya da meta hiç yok)
= "her sınıfa görünür", her ürünün bu özellikten ÖNCEKİ davranışıyla aynı,
geriye dönük taşıma gerektirmiyor. `ProductsRestController` artık
`grade_levels` alanını `store()`/`update()`'te yazıyor
(`applyGradeLevels()` - `applyCategory()`'nin aksine, AÇIKÇA boş bir dizi
göndermek anlamlı: sınırlamayı temizler; sadece parametre TAMAMEN yoksa
mevcut değer dokunulmadan kalır) ve `serialize()`'de geri döndürüyor.
Görünürlük uygulaması Students'tan yeni yayınlanmış bir Contract
üzerinden: `ParentClassLookupInterface`/`WpdbParentClassLookup` (yeni,
`ParentBranchLookupInterface`/`WpdbParentBranchLookup`'ı birebir
yansıtıyor - Commerce zaten bu dosyada `ParentBranchLookupInterface`
üzerinden Students'a bağımlı olduğu için yeni bir cross-plugin bağımlılık
değil). `ProductVisibilityHooks::isActiveForCurrentUser()` şube
kontrollerinden SONRA, aynı "velinin çocuklarından herhangi biri" mantığı
ile bir sınıf kontrolü daha yapıyor: ürünün `grade_levels`'ı boşsa
sınırlama yok; doluysa velinin çocuklarından en az birinin `class_name`'i
o listede olmalı. Personel (`MANAGE_PRODUCTS`) hâlâ TÜM görünürlük
kontrollerini (şube + sınıf) atlıyor - yönettikleri şeyi görebilmeleri
gerekiyor.

`templates/product-edit.php`'ye aynı 14 seçeneği checkbox olarak
gösteren yeni bir "Görünür Olacağı Sınıflar" `<fieldset>` eklendi ("boş
bırakılırsa herkese görünür" ipucuyla); `product-edit-panel.js`
`populateForm()`'da `product.grade_levels`'a göre kutuları işaretliyor,
gönderimde işaretli kutuların `value`'larını `payload.grade_levels`'a
topluyor.

**Doğrulama**: `php -l` (dokunulan/yeni her PHP dosyası), `node --check`
(dokunulan her JS dosyası), `vendor/bin/phpcs` (repo geneli, 0 hata),
`plugin/seviye-students`+`seviye-commerce`+`seviye-pricing` PHPUnit
paketleri (yeni `WpdbParentClassLookupTest`/`XlsxToCsvConverterTest`
dahil, hepsi yeşil) temiz. `seviye-students`, `seviye-commerce`,
`seviye-pricing` plugin zip'leri VE tema zip'i bu turda yeniden derlendi
(hepsi PHP değişikliği içeriyor, ilk üçü plugin dosyalarında, tema ise
tüm 5 alt-özellik için).

**Ek düzeltme (aynı tur, teslimattan sonra bulundu)**: "alt menüler
görünmüyor" - `.scp-sidebar`'daki `overflow-y: auto`, CSS'in "bir eksen
`visible` değilse diğeri de `auto`'ya zorlanır" kuralı yüzünden
`overflow-x`'i de `auto`'ya çeviriyordu; bu da sağa doğru açılan flyout
alt menünün İÇERİĞİNİ (ikon/etiket - kutunun kendisi değil, o hâlâ
görünüyordu) kırpıyordu. Gerçek `theme.css`/`panel.css` dosyalarıyla
statik bir HTML test sayfası kurulup Playwright'ta hem hata yeniden
üretildi hem de düzeltme (sidebar'dan sabit `height`/`overflow-y`
kaldırıldı - `.scp-layout`'un zaten var olan `align-items: flex-start`'ı
sidebar'ın `<main>`'in yüksekliğine gerilmesini önlemeye yetiyor, sayfa
kendisi gerekirse kayar) doğrulandı.

### 67. Sipariş kargo durumu: "kargoya verildi/teslim edildi"

"Hem şubede hem genel merkezde sipariş hazırlanıyor diyor ama kargoya
verildi ve teslim edildi gibi bir özellik göremiyorum" - araştırma
sonucu: panel WooCommerce'in 6 yerleşik durumunu (Ödeme Bekliyor,
Hazırlanıyor, Beklemede, Tamamlandı, İptal Edildi, İade Edildi)
Türkçeleştirip gösteriyordu, personelin elindeki tek aksiyon iptal/iade
idi - kargo/teslimat kavramı hiç yoktu.

**Yeni bir WC sipariş durumu DEĞİL, ayrı bir "fulfillment" katmanı**:
`status` zaten hakediş tetiklemesini (`OrderPersistenceHooks::HAKEDIS_TRIGGER_STATUS
= 'completed'`), Reports'un ciro sorgularını, haftalık özeti ve
refund()'un `completed`-only kapısını yönetiyor. "Kargoya
verildi"/"teslim edildi"yi YENİ WC durumları yapmak, bir sipariş
kargoya verildiğinde artık `completed` OLMAMASI anlamına gelir ve
yukarıdakilerin hepsini bozardı. Bunun yerine yeni
`plugin/seviye-commerce/src/Support/OrderFulfillment.php` sınıfı,
sipariş meta'sında (`_scp_shipped_at`, `_scp_delivered_at`,
`_scp_tracking_number` - `WC_Order`'ın kendi `get_meta()`/
`update_meta_data()`/`save()` API'si üzerinden, HPOS-güvenli, order
ITEM meta'sı için zaten kullanılan aynı desen) `status`'tan TAMAMEN
BAĞIMSIZ bir kargo alt durumu tutuyor - aşama (`preparing`/`shipped`/
`delivered`) ayrı bir alan olarak SAKLANMIYOR, hangi zaman damgalarının
dolu olduğundan TÜRETİLİYOR, böylece ikisi asla birbirinden
sapamaz. "Teslim edildi" "kargoya verildi"yi ÖNCEDEN gerektirmiyor - bir
şube, kargo şirketi/takip numarası hiç devreye girmeden bir siparişi
veliye elden teslim edebilir.

**REST**: `AdminOrdersRestController`'a `ship()`/`deliver()` eklendi
(`POST commerce/orders/{id}/ship` + isteğe bağlı `tracking_number`,
`POST commerce/orders/{id}/deliver`) - her ikisi de `cancel()`/`refund()`
ile AYNI iki katmanlı HQ/kendi-şubesi yetki kontrolünü kullanıyor (yeni
`OrderCapability::UPDATE_ORDER_FULFILLMENT`/
`UPDATE_OWN_BRANCH_ORDER_FULFILLMENT` - REFUND_ORDERS'ın aksine bu bir
para hareketi değil, lojistik bir güncelleme, o yüzden Şube Müdürü'nün
de kendi şubesi için bir katmanı var). Yalnızca "kabul edilmiş" bir
sipariş (`FULFILLABLE_STATUSES = processing/on-hold/completed`) kargoya
verilebilir/teslim edilebilir olarak işaretlenebiliyor; zaten teslim
edilmiş bir siparişte her iki aksiyon da 422 döndürüyor (idempotency).
`OrderPresenter` artık `OrderFulfillment::present()`'i sonuca
birleştiriyor (`fulfillment_status`, `fulfillment_status_label`,
`shipped_at`, `delivered_at`, `tracking_number`) - hem admin listesi HEM
DE velinin kendi `/mine` geçmişi AYNI presenter'dan geçtiği için ikisi
de otomatik olarak bu alanları kazandı.

**Bildirim**: `commerce.order_shipped`/`commerce.order_delivered`
olayları (yeni, paylaşılan `Support\OrderPayloadBuilder`
kullanılarak - `OrderPersistenceHooks`'un ÖNCEDEN private olan
`orderPayload()` metodu buraya taşındı, çünkü artık iki farklı sınıf
[`OrderPersistenceHooks` VE `AdminOrdersRestController`] aynı payload
şeklini üretmesi gerekiyordu) `Seviye\Notifications\Support\OrderStatusNotificationListener`'ın
(bölüm 47'nin iptal/iade dinleyicisiyle AYNI sınıf, iki yeni metot -
`onOrderShipped()`/`onOrderDelivered()`) veliye e-posta göndermesini
tetikliyor; kargo takip numarası varsa e-postaya "Kargo Takip No: ..."
satırı olarak ekleniyor (mevcut `reason`/"Not:" satırı deseninin genel
hâle getirilmiş versiyonu - `notify()` artık opsiyonel `$note`/
`$noteLabel` parametreleri alıyor).

**Tema**: `templates/orders-admin.php`'nin listesinde ("Sipariş
Yönetimi") her siparişin altına, uygun olduğunda, "Kargoya Ver" (takip
numarası için `window.prompt`) ve "Teslim Edildi Olarak İşaretle"
(`window.confirm`) butonları eklendi; kargo durumu WC durum rozetinin
yanında İKİNCİ bir rozet olarak gösteriliyor (SADECE `preparing`
DIŞINDA bir aşamada - `preparing` zaten mevcut "Hazırlanıyor" rozetiyle
kapsandığı için ayrı bir rozet kazanmıyor). Velinin kendi
`/siparislerim` geçmişi (`orders-panel.js`) AYNI rozeti VE takip
numarası/tarih bilgisini salt-okunur gösteriyor - aksiyon butonu yok,
sadece görünürlük.

**Doğrulama**: `php -l` (dokunulan/yeni her PHP dosyası), `node --check`
(dokunulan her JS dosyası), `vendor/bin/phpcs` (repo geneli, 0 hata),
`plugin/seviye-commerce` (17 test, değişmedi - `OrderPresenter`/
`AdminOrdersRestController`'ın constructor'ları değişti ama mevcut
testler zaten bu sınıfları doğrudan constructor ile kurmuyordu) ve
`plugin/seviye-notifications` (48 test, 3 yeni -
`testShippedOrderDispatchesAnEmailWithTheTrackingNumber`,
`testShippedOrderWithoutATrackingNumberOmitsTheNoteLine`,
`testDeliveredOrderDispatchesAnEmailNotification`) PHPUnit paketleri
yeşil. `seviye-commerce` ve `seviye-notifications` plugin zip'leri VE
tema zip'i yeniden derlendi.

### 68. Hata düzeltmesi: `scpPanel`/`scpPanelText` global çakışması - hiçbir panelde aksiyon butonu görünmüyordu

Kullanıcı bölüm 67'nin butonlarının (Kargoya Ver/Teslim Edildi) hiç
görünmediğini bildirdi. Uzun bir teşhis sürecinden sonra (kurulum
sihirbazı yeniden çalıştırıldı, hesabın şubeye bağlı olduğu doğrulandı,
tarayıcı konsolunda `scpPanel` yazdırıldı) kök neden bulundu: **bu, yeni
kargo özelliğine özgü değildi - İptal Et/İade Et gibi ÇOK DAHA ESKİ
butonlar da aynı şekilde görünmüyordu**, çünkü sorun tamamen farklı bir
katmandaydı.

**Kök neden**: Her panel script'i `wp_localize_script()` ile AYNI global
JavaScript değişken adını (`scpPanel`, `scpPanelText`) kullanıyor - bu
kasıtlı bir kod tekrarı önleme kalıbı (`inc/assets.php`'nin `$localized`/
`$text` taban dizileri her script için `array_merge()`lenip AYNI isimle
localize ediliyor). Ancak üç script - `account-security.js`,
`privacy-requests-panel.js`, `support-tickets-panel.js` - "her sayfada
kendi kök elemanını arayıp yoksa çık" mantığıyla bilinçli olarak HER
`/admin`,`/sube` sayfasında KOŞULSUZ enqueue ediliyor (bkz. bu üç
script'in `inc/assets.php`'deki kendi yorumları). WordPress
`wp_localize_script()`, her script handle'ı için KENDİ `var scpPanel =
{...};` satırını, o handle'ın `<script src>` etiketinden hemen önce
basıyor - yani sayfadaki HER script kendi çalışma anından hemen önce
`window.scpPanel`'i KENDİ (dar) verisiyle değiştiriyor.

Bu tek başına sorun değildi (her script kendi SENKRON kod bloğunda
`scpPanel`'i doğru okuyordu) - asıl sorun, `admin-orders-panel.js`'nin
buton çizme kodunun (`renderOrderActions()`) `loadOrders()`'ın ASENKRON
`apiFetch().then()` callback'i İÇİNDEN çalışması: bu callback, sayfadaki
TÜM script'lerin senkron kodu bittikten SONRA (fetch cevabı geldiğinde)
tetikleniyor - o ana kadar sayfadaki EN SON script (örn.
`support-tickets-panel.js`, dosyada daha aşağıda enqueue edildiği için)
zaten kendi (dar, `canCancelOrders`/`canUpdateFulfillment` içermeyen)
`scpPanel` nesnesiyle global'i ezmiş oluyordu. `renderOrderActions()`
çalıştığında okuduğu `scpPanel.canCancelOrders` artık YANLIŞ script'in
verisiydi - `undefined`, yani "false". Aynı asenkron-okuma deseni bu
platformdaki NEREDEYSE HER panel script'inde vardı (`scpPanel`/
`scpPanelText`'e sadece ilk `typeof` kontrolünde değil, kodun her
yerinde, çoğunlukla `.then()` callback'leri içinde referans veriliyordu)
- yani bu, sadece Sipariş Yönetimi'ni değil, muhtemelen TÜM platformdaki
her koşullu buton/alan/metni etkileyen, daha önce hiç fark edilmemiş
(gerçek bir tarayıcıda hiç uçtan uca test edilmediği için) bir hataydı.

**Düzeltme**: WordPress'in script basma sırası aslında her handle için
"önce o handle'ın kendi localize verisi, hemen ardından o handle'ın
kendi `<script src>`'i" şeklinde ÇİFTLER hâlinde ilerliyor - yani her
script'in KENDİ localize verisi, o script'in KENDİ kodu ilk çalıştığı
anda (senkron olarak) doğru. Çözüm: 26 panel script'inin HER BİRİNİN
IIFE'sinin başına (`typeof scpPanel === 'undefined'` koruma bloğundan
hemen sonra), global'i BİR KEZ yerel bir değişkene yakalayan iki satır
eklendi:
```js
var scpPanelData = scpPanel;
var scpPanelTextData = typeof scpPanelText !== 'undefined' ? scpPanelText : {};
```
ve dosyanın GERİ KALANINDAKİ (bu satırlardan sonraki) HER `scpPanel.x`/
`scpPanelText.x` referansı `scpPanelData.x`/`scpPanelTextData.x` olarak
değiştirildi - artık her script kendi verisini script YÜKLENİRKEN (daha
sonra başka bir script global'i ezmeden ÖNCE) bir kapanışa (closure)
sabitliyor, callback ne zaman çalışırsa çalışsın hep DOĞRU, KENDİ
verisini okuyor. Mekanik bir dönüşüm olduğu için otomatik bir Python
betiğiyle (aynı `if (...typeof scpPanel === 'undefined'...) { return; }`
koruma deseni neredeyse tüm dosyalarda birebir aynıydı) uygulanıp her
dosya `node --check` ile doğrulandı; kalan birkaç eşleşme sadece
docblock YORUMLARINDA kalan `scpPanel.x` referanslarıydı (kod değil,
dokunulmadı). `scp-api-fetch.js` (paylaşılan `scpApiFetch()`/
`scpUploadMedia()` yardımcıları) kasıtlı olarak DEĞİŞTİRİLMEDİ - o sadece
`scpPanel.nonce`/`restUrl`/`wpRestRoot` okuyor, bu üç alan HER script'in
localize verisinde birebir AYNI (tek bir `$localized` taban dizisinden
geliyor), yani hangi script'in "kazandığı" onun için önemsiz.

Neden PHP tarafında (enqueue sırasını değiştirmek gibi) değil de JS
tarafında düzeltildi: her handle'ın kendi localize+script çifti birlikte
bastığı için PHP sıralaması ASLA sorunun kaynağı değildi - script'ler
KENDİ localize verilerini her zaman doğru okuyordu, sorun sadece "daha
sonra çalışan kod hâlâ geçerli olduğunu SANDIĞI global'e güveniyordu"
idi; JS tarafındaki yerel yakalama, PHP tarafındaki enqueue sırasından
tamamen bağımsız, kalıcı bir düzeltme.

**Doğrulama**: 26 dosyanın hepsi `node --check`'ten geçti; `git diff
--stat` (758 satır eklenme/368 satır silinme) gözden geçirildi,
`pricing-panel.js`/`admin-orders-panel.js` diff'leri elle örneklendi.
PHP dosyası değişmedi (sadece tema JS'i), plugin zip'leri yeniden
derlenmedi - sadece tema zip'i.

**Ek düzeltme (aynı tur)**: "Teslim edildi yazınca WooCommerce'de
tamamlandı olarak düzenlensin" - `deliver()` artık `OrderFulfillment`
meta'sını işaretlemenin yanı sıra, sipariş `completed` değilse
`$order->update_status('completed', $note)` da çağırıyor (`ship()`
BUNU YAPMIYOR - kargoya verilen bir sipariş hâlâ sürüyor olabilir, asıl
"satış bitti" sinyali teslimat). Bu, cancel()'ın zaten kullandığı AYNI
`update_status()` çağrısı olduğu için `woocommerce_order_status_changed`
kancasını tetikliyor -
`OrderPersistenceHooks::syncOrderStatus()` (hakediş event'leri,
`scp_order_line_items` senkronu) hiçbir özel durum eklenmeden, sanki
personel durumu elle `completed`'e çevirmiş gibi doğru çalışıyor;
zaten `completed` olan bir siparişi teslim edildi işaretlemek (personel
önce tamamlandı demiş, günler sonra teslim etmiş) no-op kalıyor.

### 69. Mağaza sayfası kullanılabilirlik turu + header logosu kırpma düzeltmesi

Kullanıcı iki ekran görüntüsü paylaştı (tekli ürün sayfası + Mağaza arşiv
sayfası) ve "Mağaza sayfasını daha iyi yap kullanıcı dostu olsun, header'daki
logoyu da tam görünür şekilde olsun" dedi. İki ayrı, ilgisiz kök nedeni olan
iki görsel hata:

**1) Header logosu kırpılıyordu.** `header.php`, logoyu
`scp_logo_url('thumbnail')` ile çekiyordu. WordPress'in `'thumbnail'` boyutu
- `'medium'`/`'large'`/`'full'`'ün aksine - varsayılan olarak HER ZAMAN kare
olacak şekilde SUNUCU TARAFINDA kırpılır (yüklenen logonun kendi en-boy
oranı ne olursa olsun). `.scp-site-header__logo`'nun CSS'i (önceki bir
turda "yarım görünüyor" hatası için zaten `max-height`/`object-fit:
contain` olarak düzeltilmişti) buradan sonra hiçbir şey yapamaz - kırpılan
pikseller kaynak dosyadan zaten silinmiş. Düzeltme: `templates/login.php`
ile aynı boyuta, `'medium'`e (oranı koruyan, kırpmasız bir yeniden
boyutlandırma) geçildi.

**2) Kategori filtresi düz bir madde imi listesi olarak görünüyordu**
(ekran görüntüsünde "• Dijital (1)  • Genel (2)"). Kök neden:
`scp_render_shop_filters()` (`inc/woocommerce.php`), `wp_list_categories()`u
doğrudan bir `<nav>` içine, SARAN bir `<ul>` OLMADAN çağırıyordu.
`wp_list_categories()` yalnızca çıplak `<li>` elemanları basar - saran
`<ul>`'u çağıran şablonun sağlaması WordPress'in dokümante edilmiş API
sözleşmesidir. `<ul>` DOM'da hiç var olmadığından mevcut `.scp-shop-
filters__categories ul { list-style: none; ... }` CSS kuralı hiçbir zaman
eşleşmiyordu, tarayıcının varsayılan disk madde imine geri düşülüyordu.
Düzeltme: eksik `<ul>` eklendi; ayrıca bir kategori seçiliyken (`product_cat`
arşivinde) listenin başına, filtreyi temizleyip Mağaza'ya dönen bir "Tümü"
linki eklendi (`wp_list_categories()`'in kendi `current_category` parametresi
yalnızca AKTİF kategoriyi vurgular, bir "tümü" seçeneği eklemez - geri
dönmenin tek yolu tarayıcı geri tuşuyken).

**Genel görsel cila** (`assets/css/woocommerce.css`, aynı tur): ürün
kartları artık `height: 100%` ile eşit yükseklikte; fotoğrafsız ürünler
için düz gri kutu yerine ortalanmış bir "fotoğraf" ikonlu placeholder arka
plan (inline SVG data URI, yeni markup/wrapper class'ına bağımlı olmadan
doğrudan mevcut `img` selector'üne uygulandı); kart üzerine gelince hafif
bir `scale(1.04)` büyütme; kategori linkleri artık hap (pill) biçimli
düğmeler (`border-radius: 999px`, hover'da kenarlık rengi değişimi); arama
kutusu/düğmesi de hap biçimine getirildi; sıralama/sonuç sayısı satırı ve
filtre paneli için boşluk/gölge iyileştirmeleri.

Bir ara adımda, ürün resmi placeholder'ı için WooCommerce'in varsayılan
`content-product.php` şablonunun ürettiğini VARSAYDIĞIM (`.scp-product-
thumb` sarmalayıcı div'i, `a.woocommerce-LoopProduct-link` gibi) ama yerel
WC kaynağı/internet erişimi olmadığı için DOĞRULAYAMADIĞIM class adlarına
dayanan bir CSS yaklaşımı yazıldı, sonra bu risk fark edilip geri alındı -
bunun yerine zaten önceki bölümlerde kanıtlanmış, gerçekten var olan
`.woocommerce ul.products li.product img` selector'ü doğrudan
genişletildi (yeni markup bağımlılığı yok).

**Doğrulama**: `php -l` + `vendor/bin/phpcs` (değişen 2 PHP dosyası ve
tüm repo) temiz - repo genelinde kalan tek uyarılar önceki turlardan
bilinen, kabul edilmiş `MissingTranslatorsComment` uyarıları
(`inc/plugin-installer.php`). CSS/HTML görsel değişiklikleri gerçek bir
tarayıcıda test EDİLEMEDİ (bu platform hiç uçtan uca görsel olarak test
edilmedi - bkz. bölüm 68'in aynı notu); kullanıcının ekran görüntüsündeki
belirtilere karşı kod okuma yoluyla doğrulandı. PHP dosyası dışında sadece
tema (`header.php`, `inc/woocommerce.php`, `assets/css/woocommerce.css`)
değişti - plugin zip'leri yeniden derlenmedi, sadece tema zip'i.

### 70. Ürün ürün vergilendirme: WooCommerce'in tax class/rate motorunu saran "Vergi Oranları" yönetimi

"Woo commerce'te yok bu" - kullanıcının gördüğü gerçek eksiklik buydu:
Commerce eklentisi zaten WooCommerce'in KENDİ vergi hesaplamasının
SONUCUNU (`get_total_tax()`) okuyup Finance'a hakediş kaydı olarak
yazıyordu (bkz. bölüm 15, "12d-A: Commerce VAT capture"), ve
`WooCommerceCartHooks` (Seviye'nin fiyat çözümleyicisi) yalnızca sepetteki
BİRİM FİYATI değiştiriyor - `WC_Cart::calculate_totals()`'ın kendisi ve
dolayısıyla ürünün kendi `tax_class`'ına göre vergi uygulaması hâlâ normal
şekilde çalışıyor. Yani WooCommerce'in vergi MOTORU zaten doğru
çalışıyordu; eksik olan, platformun kendi özel "Ürünler" sayfasının hiçbir
zaman bir "Vergi Sınıfı" alanı göstermemesiydi - her ürün sessizce
mağazanın TEK global vergi ayarını kullanıyordu, ürün bazlı bir seçim
imkânsızdı.

**Tasarım kararı** (kullanıcıya iki seçenek sunuldu, "WooCommerce
motorunu kullan" seçildi): sıfırdan bir Seviye vergi hesaplayıcısı
yazıp WooCommerce'in vergi motorunu devre dışı bırakmak yerine,
WooCommerce'in KENDİ tax class/rate depolamasının üstüne ince bir
sarmalayıcı eklendi - `plugin/seviye-commerce/src/Support/
TaxRateGateway.php`. Bir "vergi oranı" burada TEK bir isim + TEK bir düz
yüzde olarak sunuluyor (`tax_rate_country`/`tax_rate_state` boş = her
yere uygulanır - tek ülkeli/TR mağazası için doğru basitleştirme;
WooCommerce'in bir vergi sınıfının prensipte birden çok ülke/eyalet satırı
taşıyabilmesi burada gerekmiyor). Yeni bir `scp_*` tablosu YOK - aynı
"Kural" (bkz. Ürünler/Siparişler/Kuponlar): sınıf adı `woocommerce_tax_
classes` seçeneğinde (WC_Tax'ın kendi belgelenmiş formatı - yeni satırla
ayrılmış isim listesi, slug her zaman `sanitize_title()`), oranı ise
WooCommerce'in kendi vergi oranları tablosunda, `WC_Tax::
_insert_tax_rate()`/`_update_tax_rate()`/`_delete_tax_rate()` üzerinden
(alt çizgi WC'nin bir isimlendirme tuhaflığı, gerçek PHP görünürlüğü
değil - WooCommerce'in KENDİ `wc/v3/taxes` REST API'sinin arkasındaki
`WC_REST_Tax_Rates_Controller`'ın çağırdığı AYNI metodlar). Bu, bu
ortamda çalışan bir WooCommerce kurulumuna karşı DOĞRULANAMADI (yerel WC
kaynağı/internet erişimi yok - dokümanın tamamında tekrarlanan aynı kısıt)
- bu yüzden `TaxRateGateway::isSupported()` her yazma yolunu (create/
update/delete) korur: beklenmedik bir WC sürümünde ham bir fatal yerine
501 + Türkçe hata mesajı döner.

"Standart" (WooCommerce'in her zaman var olan varsayılan sınıfı, gerçek
`tax_rate_class` değeri boş string) API/URL katmanında `standard` sözde
slug'ı olarak sunuluyor - boş string bir REST route'un
`(?P<slug>[^/]+)` path segmenti olarak ASLA eşleşemez, bu yüzden gerçek
WC değeri yalnızca sınırlarda çevriliyor (`toWooCommerceClass()`/
`toPublicSlug()`); silinemez (WooCommerce her zaman ihtiyaç duyar).

**RBAC ikili katman**: vergi oranı KATALOĞUNU tanımlamak/silmek yeni
`ProductCapability::MANAGE_TAX_RATES` ile HQ-only (Genel Merkez/Bölge
Müdürü, Kupon'la aynı şekilde Şube Müdürü katmanı yok - hukuki/mağaza
geneli bir ayar). Zaten TANIMLANMIŞ bir oranı bir ÜRÜNE atamak ise ayrı
tutulmadı, mevcut `MANAGE_PRODUCTS`'ın bir parçası kaldı - bir Şube
Müdürü zaten o ürünün fiyatını/kategorisini değiştirebiliyorsa, HQ'nun
tanımladığı listeden bir vergi oranı SEÇMESİ de aynı yetki seviyesinde
(yeni bir oran ekleyemez/silemez, sadece seçer).

**`ProductsRestController`**: `applyWritableFields()`'a `tax_class` alanı
eklendi - varyantlı (`WC_Product_Variable`) ürünlerin fiyat/stoktan farklı
olarak KENDİ vergi sınıfı olduğu için (varyasyonlar varsayılan olarak
bunu miras alır), bu atama varyant erken-dönüşünden ÖNCE yapılıyor.
`serialize()` hem `tax_class` (public slug) hem `tax_rate_percent`
(o an geçerli yüzde) döndürüyor, böylece tema ek bir istek atmadan
"KDV: %20" gösterebiliyor.

**Tema**: yeni HQ-only `/admin/vergi-oranlari` sayfası
(`templates/tax-rates-admin.php` + `assets/js/tax-rates-panel.js`,
`coupons-admin.php`/`coupons-panel.js`'den birebir kopyalanan yapı) -
isim+yüzde formu, "Standart"ın adı değiştirilemez (sadece yüzdesi),
kullanımda olan/olmayan özel oranlar listede gösteriliyor, kullanımdaki
bir oran silinemiyor (`TaxRateGateway::delete()` zaten bunu reddediyor,
buton da `disabled`). Ürün düzenleme sayfasına (`templates/
product-edit.php`) bir "Vergi Oranı" `<select>` eklendi;
`product-edit-panel.js` sayfa açılışında `commerce/tax-rates`'i AYRI,
paralel bir istekle çeker (ürünün kendi verisiyle aynı anda) -
`pendingTaxClass`/`applyPendingTaxClass()` iki isteğin hangisinin önce
bittiğine bakmaksızın doğru seçili değeri garanti ediyor.

**Doğrulama**: `php -l` + `vendor/bin/phpcs` (değişen dosyalar ve tüm
repo) temiz - tek kalan uyarılar önceki turlardan bilinen, kabul edilmiş
uyarılar. `seviye-commerce`'in 17 testi (47 assertion) yeşil kaldı - yeni
`TaxRateGateway` için ayrı bir birim testi YAZILMADI, çünkü sınıfın
tamamı `WC_Tax`/`get_option`/`get_posts` gibi WordPress/WooCommerce çalışma
zamanı fonksiyonlarına sarılı (bu platformdaki diğer WC-entegrasyon
sınıflarıyla - `ProductsRestController`, `WooCommerceCartHooks`, `CouponsRestController`
- aynı, hiçbiri birim testli değil, gerçek bir WP/WC ortamı gerektiriyor).
`node --check` iki yeni/değişen JS dosyasında temiz. Gerçek bir
WooCommerce kurulumunda uçtan uca test EDİLEMEDİ (dokümanın tamamında
tekrarlanan aynı ortam kısıtı) - özellikle `WC_Tax::_insert_tax_rate()`
ailesinin tam davranışı kullanıcının kendi WooCommerce sürümünde
doğrulanmalı.

### 71. "İlk Şifremi Oluştur" kaldırıldı: kurum tarafından oluşturulan şifreyle ilk giriş + giriş sonrası zorunlu şifre değişimi

Kullanıcının isteği üç parçaydı: (1) "İlk Şifremi Oluştur" bölümünü
kaldır, (2) bunun yerine kurum tarafından oluşturulan şifreyle ilk giriş
yapılsın, giriş yapıldıktan hemen sonra kullanıcı kendi şifresini
oluştursun, (3) "Şifremi Unuttum"da T.C. Kimlik No girilince eşleşen
hesabın e-postasına bir şifre değiştirme e-postası gitsin, bunun için bir
şifre değiştirme sayfası kurulsun.

Araştırma (bkz. bölüm 12'nin "first-setup" notu) üçüncü maddenin ZATEN
tam olarak istenen şekilde çalıştığını ortaya çıkardı: `forgotPassword()`
zaten yalnızca T.C. Kimlik No alıyor, `IdentityGatewayInterface::
findUserIdByTcNumber()` ile eşleşen hesabı buluyor, bir token üretip
`security.password_reset_requested` event'i yayınlıyor,
`PasswordResetNotificationListener` bunu dinleyip eşleşen hesabın
e-postasına bir sıfırlama bağlantısı gönderiyor, `templates/login.php`'nin
`set-password` görünümü de zaten o bağlantıyla açılan bir "şifre
değiştirme sayfası". Bu turda dokunulmadı - yalnızca birinci/ikinci
maddeler yeni iş.

**Hesap oluşturmada zaten gerçek bir şifre vardı**: hem
`UserListPage::handleCreate()` (personel hesapları) hem
`StudentsRestController::maybeCreateAndLinkParent()` (otomatik veli
hesabı) zaten oluşturma anında gerçek, kullanılabilir bir şifre
üretiyor/istiyordu - "İlk Şifremi Oluştur" hiçbir zaman hesap oluşturma
akışına bağlanmamıştı, kullanıcının kendi başına tetikleyebileceği ayrı,
kullanılmayan bir self-servis bağlantıydı. Yani asıl eksik "giriş
yapıldıktan hemen sonra kendi şifresini oluşturması zorunlu kılınması"
kısmıydı - bu daha önce hiç yoktu (`must_change_password`/
`force_password_change` gibi bir bayrak aranıp bulunamadı).

**Yeni mekanizma**: `plugin/seviye-security/src/Auth/
MustChangePasswordGatewayInterface.php` + `WpdbMustChangePasswordGateway`
(yeni `scp_must_change_password_flags` tablosu, tek sütun `user_id` -
satırın VARLIĞI bayrağın kendisi). İşaretlenme noktaları:
- `UserListPage::handleCreate()` - her yeni personel hesabı.
- `UserListPage::handleSave()` - bir personelin şifresi admin tarafından
  sıfırlandığında (satır formundaki "Şifre" alanı doldurulursa).
- Students'ın otomatik veli hesabı oluşturma akışı - ama Students,
  Security'nin PHP sınıflarına DOĞRUDAN bağımlı olamaz (tam tersi yön
  zaten var, döngüsel bağımlılık olurdu) - bu yüzden
  `StudentsRestController` yeni bir `students.parent_password_generated`
  event'i yayınlıyor, `SecurityModule::boot()` bunu dinleyip bayrağı kendi
  işaretliyor (HakedisEventListener'ın Commerce→Finance için kullandığı
  AYNI gevşek bağlama deseni).

Temizlenme noktaları (şifreyi ARTIK kullanıcının kendisi seçtiği her yer):
`AuthRestController::setPassword()` (token redemption - hem "Şifremi
Unuttum" hem aşağıdaki zorunlu değişim akışı buradan geçiyor) ve
`AccountRestController::update()` (Profilim'deki kendi kendine şifre
değiştirme).

**"Giriş yapıldıktan hemen sonra"**: `finishLogin()` (hem `login()` hem
`login2fa()`'nın ortak son adımı) artık yanıtına `must_change_password`
ekliyor; true ise AYRICA `PasswordTokenService::issue()` ile normal bir
şifre sıfırlama token'ı üretip `password_change_token` alanında DOĞRUDAN
bu yanıtta döndürüyor (e-postayla DEĞİL - zaten aynı tarayıcı sekmesinde,
zaten kimliği doğrulanmış bir isteğe cevap veriyoruz). Tema'nın
`require-password-change` görünümü bu token'ı `set-password` uç
noktasına postluyor - yani zorunlu ilk-değişim akışı, "Şifremi Unuttum"un
redemption adımıyla AYNI kodu kullanıyor, ayrı bir uç nokta değil.

Neden ayrı, oturum tabanlı ("zaten giriş yaptın, sadece yeni şifreyi
gönder") bir REST uç noktası KURULMADI: `wp_set_auth_cookie()` çağrısı bu
İSTEĞİN kendi `$_COOKIE` superglobal'ini güncellemiyor (tarayıcıya bir
sonraki istek için Set-Cookie başlığı gönderiyor, ama şu anki PHP
isteğinin `$_COOKIE`'si aynı kalıyor) - yani AYNI istekte hemen
`wp_create_nonce('wp_rest')` çağırmak, `wp_get_session_token()`'ın henüz
görmediği (boş/eski) bir oturum token'ına bağlı bir nonce üretirdi; bu
nonce, bir SONRAKİ istekte (tarayıcı artık gerçek çerezi taşırken)
`wp_verify_nonce()` tarafından reddedilirdi - "aynı istekte üretilen
nonce, farklı bir sonraki istekte doğrulanamıyor" tuzağı. Zaten
kanıtlanmış, oturumdan bağımsız token mekanizmasını yeniden kullanmak bu
sınıfı tamamen ortadan kaldırıyor.

`PasswordTokenPurpose::FIRST_SETUP` case'i tamamen silindi (yalnızca
`RESET` kaldı) - `/auth/first-password` uç noktası artık hiç yok, enum
değeri hiçbir yerde üretilmiyordu. `PasswordResetNotificationListener`in
`subjectFor()`'ı buna göre sadeleştirildi (bilinmeyen bir `purpose` için
genel bir başlığa düşüyor, gelecekte üçüncü bir amaç eklenirse diye
`if`/`else` yapısı korundu, hardcode edilmedi).

**Tema**: `templates/login.php`'den "İlk Şifremi Oluştur" linki ve
görünümü tamamen kaldırıldı. Yerine, `set-password` görünümüyle aynı
alan yapısını (yeni şifre + tekrar) kullanan ama "vazgeç"/girişe dön
linki OLMAYAN yeni bir `require-password-change` görünümü eklendi -
zorunlu bir geçit, ayrı bir akış değil (hesap zaten oturum açık,
`finishSession()` bu görünümü göstermeden ÖNCE `redirect_url`'i asla
takip etmiyor). Başarıdan sonra `/`'ye yönlendiriyor - kullanıcı zaten
çerezle giriş yapmış olduğundan `inc/access-gate.php`'nin rol-bölge
zorlaması onu doğru panele (RoleRouter'ın hesapladığı gerçek `redirect_url`
DEĞİL, çünkü set-password'ün token-redemption yanıtı bunu taşımıyor -
ama `/`'ye düşmek zaten aynı sonucu veriyor) otomatik gönderiyor.

**Doğrulama**: `php -l` + `vendor/bin/phpcs` (değişen dosyalar ve tüm
repo) temiz - kalan uyarılar önceki turlardan bilinen, kabul edilmiş
uyarılarla birebir aynı (`UserListPage.php` hâlâ 9, değişmedi).
`seviye-security` (78 test), `seviye-notifications` (48 test, FIRST_SETUP
testi genel-purpose bir "bilinmeyen purpose" testine dönüştürüldü),
`seviye-students` (47 test) yeşil. `node --check` `auth.js`'de temiz. Yeni
`WpdbMustChangePasswordGateway` için ayrı birim testi YAZILMADI - aynı
"WP çalışma zamanına sarılı adaptörler test edilmez" kuralı
(`WpdbIdentityGateway`'in kendisi de test edilmemiş). Gerçek bir
WordPress/WooCommerce kurulumunda uçtan uca test EDİLEMEDİ (aynı ortam
kısıtı) - özellikle nonce/çerez zamanlama akıl yürütmesi kullanıcının
kendi ortamında doğrulanmalı.

### 72. Görsel/UX Tur 1: e-posta HTML şablonu, şifre gücü göstergesi, sipariş zaman çizelgesi, düşük stok rozeti, karakter sayacı, markalı 404

Kullanıcı, önceki turlarda önerilen ~90 maddelik görsel/UX birikiminin
neredeyse tamamını tek seferde "yapılsın" diye işaretledi; bu boyutta bir
isteği tek seferde denemek gözden geçirilemez/güvensiz olacağından,
`AskUserQuestion` ile nasıl ilerleneceği soruldu - kullanıcı "Ben sıralayıp
parça parça ilerleyeyim (Önerilen)" seçeneğini seçti. Bu, her turu tek tek
onaya sunmadan, düşük riskli/yüksek etkili maddelerden başlayarak kendi
kendine sıralı turlar halinde ilerleme yetkisi verdi. Bu ilk tur, birikimden
düşük riskli/bağımsız 6 maddeyi kapsıyor.

**E-posta bildirimlerinin HTML şablonu**: `EmailChannel::send()` artık düz
metni `wp_mail()`'e vermeden önce markalı bir HTML gövdeye sarıyor
(`Content-Type: text/html` başlığıyla). Tek noktadan yapıldı (her
`NotificationDispatcher` dinleyicisine tek tek dokunmak yerine) -
`NotificationDispatcher::dispatch()` uygulama içi/geçmiş kaydı için hâlâ
ORİJİNAL düz metni saklıyor (`record()`'u `send()`'DEN ÖNCE, kendi
dönüştürülmemiş kopyasıyla çağırıyor); yalnızca SMTP üzerinden giden
gövde HTML'e çevriliyor. Logo, temanın `scp_logo_url()`'ünün okuduğu AYNI
`branding_logo_attachment_id` ayarından - Notifications zaten
`seviye/core`'a sabit bağımlı olduğundan `Plugin::instance()->container()
->get(SettingsRepositoryInterface::class)` ile doğrudan erişiliyor (bir
eklentinin Core'un paylaşılan container'ına uzanması sorun değil; bir
eklentinin TEMA koduna uzanması olurdu, o yapılmadı). Gövde metni
`bodyToHtml()` ile boş satırlara göre paragraflara bölünüyor; tek başına
bir URL olan paragraf "Devam Et" biçiminde stilli bir buton pill'ine
dönüşüyor, diğerleri `nl2br(esc_html())` ile `<p>` oluyor.

**Şifre gücü göstergesi**: saf istemci-taraflı bir UX sezgisi (0-6 puanlık
skor: uzunluk≥8, uzunluk≥12, küçük harf, büyük harf, rakam, sembol) -
backend doğrulamasına (`PasswordPolicy::isAcceptable()`, hâlâ yalnızca
min 8 karakter) KESİNLİKLE dokunulmadı; o sınıfın kendi docblock'u zaten
bu eklemeyi öngörmüştü ("bir güç göstergesi Tema'nın UI'ına ait, bu
backend doğrulama geçidine değil"). `templates/login.php`'nin hem
`set-password` hem `require-password-change` formlarına aynı
`.scp-password-strength` işaretlemesi eklendi; `auth.js`'e
`passwordStrengthScore()`/`bindPasswordStrength()` eklendi.
`auth.css` login ekranında `is_user_logged_in()`'DEN ÖNCE yüklendiği için
kasıtlı olarak kendi kendine yeterli bir dosya - `theme.css`'in
`--scp-danger`/`--scp-warning` token'ları o ekranda hiç yüklenmiyor - bu
yüzden `--scp-warning`/`--scp-success` YENİ yerel token'lar olarak
doğrudan `auth.css`'in kendi `:root` bloklarına (açık + koyu) eklendi.

**Sipariş durumu görsel zaman çizelgesi**: eski tek koşullu rozet yerine
her 3 adım (Hazırlanıyor/Kargoya Verildi/Teslim Edildi) her zaman görünür,
`--done`/`--active`/`--upcoming` durum sınıflarıyla ve noktalar arasını
birleştiren bir `::after` çizgisiyle. `orders-panel.js` (veli, salt-okunur)
ile `admin-orders-panel.js` (admin/şube, aksiyon butonlarıyla) arasında
paylaşılan yeni bir dosya yerine YİNELENDİ - bu kod tabanının küçük,
WP-bağlama-özel yardımcıları paylaşılan bir dosya bağımlılığı kurmak
yerine yinelemesi kuralına uygun (bkz. bölüm 68).

Bu maddede bir HATA bulunup bu tur içinde düzeltildi: zaman çizelgesinin
"Hazırlanıyor"/"Kargoya Verildi"/"Teslim Edildi" metinleri ilk yazımda
yanlışlıkla yalnızca giriş ekranına özel `scpAuthText` localize
çağrısına (`scp_enqueue_auth_assets()`) eklenmişti; oysa
`orders-panel.js`/`admin-orders-panel.js` bu metinleri paylaşılan
`scpPanelText`'ten (`scp_enqueue_panel_assets()`'in `$text` dizisi)
okuyor - giriş ekranı hiç yüklenmeyen sipariş sayfalarında karşılığı
`undefined` olurdu. Doğrulama sırasında (dosyalar arası grep ile hangi
JS'in hangi localize objesini okuduğu karşılaştırılarak) yakalandı;
üç anahtar doğru diziye taşındı.

**Düşük stok rozeti (mağaza)**: `woocommerce_before_shop_loop_item_title`
hook'una öncelik 15'te bağlanıyor (WC'nin kendi thumbnail çıktısının,
öncelik 10, HEMEN ardından). `wc_get_low_stock_amount($product)` kullanıyor
- bu, `plugin/seviye-commerce/src/Http/LowStockNotificationHooks.php`'nin
zaten sunucu tarafında kullandığı AYNI eşik fonksiyonu (Depo/Ürünler'in
düşük stok uyarılarıyla tutarlılık için).

**Karakter sayacı**: paylaşılan `scp-ui-kit.js`'e (her kimliği doğrulanmış
sayfada global yüklenir) genel, kendi kendini başlatan bir `initCharCounters()`
eklendi - panel başına yinelemek yerine (zaman çizelgesinin aksine): bir
karakter sayacı saf, genel, tekrar kullanılabilir bir yardımcı (sayfaya
özel render mantığı değil), bu yüzden paylaşılan dosyaya konması kasıtlı
bir istisna. `DOMContentLoaded`'da `textarea[data-scp-char-counter][maxlength]`
taranıyor, her birine canlı bir `X / max` sayaç ekleniyor. Uygulandığı 5
textarea: ürün açıklaması, toplu duyuru mesajı, veli destek talebi
(yeni talep + yanıt), personel destek kuyruğu yanıtı - hepsi
`maxlength="1000"` (bu alanlar için backend'de zorlanan bir üst sınır
yok, tutarlılık için diğerleriyle aynı değer seçildi).

**Markalı 404 sayfası**: yeni `theme/seviye-storefront/404.php`.
`inc/access-gate.php`'nin `template_redirect` (öncelik 5) zaten oturum
açmamış her isteği `templates/login.php`'ye yönlendiriyor, ve
`inc/zones.php`'nin `scp_render_zone_template()`'i (öncelik 10) tanıdığı
her `/admin`, `/sube` alt-yolunu (tanımadığı bir alt-yol dahil - o zaman
zone kökünün kendisine sessizce düşüyor, 404 vermiyor, bkz. o fonksiyonun
kendi docblock'u) zaten ele alıyor - yani bu dosyaya WordPress'in şablon
hiyerarşisi yalnızca GERÇEKTEN eşleşmeyen bir istekte ulaşıyor: eskimiş/
yanlış yazılmış bir WooCommerce ürün linki, eski bir yer imi, vb. -
oturum açmış bir kullanıcının başına gelen. `get_header()`'ın
koşulsuz çağrılması bu yüzden güvenli (`index.php`'nin aynı varsayımı
gibi - `is_user_logged_in()` bu noktada zaten garanti true). Tasarım
`.scp-empty-state`'in (panel.css) aynı ortalanmış-kart şeklini kullanan
ama ayrı, yeni bir `.scp-not-found` bileşeni - marka renginde büyük bir
"404" rakamı + "Panele Dön" linki (`scp_current_user_landing_path()`).

**Doğrulama**: `node --check` (`scp-ui-kit.js`, `auth.js`, `orders-panel.js`,
`admin-orders-panel.js`) temiz. `php -l` + `vendor/bin/phpcs` (tüm
değişen dosyalar) temiz - `inc/assets.php`'deki tek uyarı (satır uzunluğu)
bu turdan ÖNCE var olan, dokunulmamış bir satırda. `seviye-notifications`
(48 test) yeşil - bu turda dokunulan tek eklenti plugin tarafı
(`EmailChannel.php`); geri kalan her şey yalnızca tema. Gerçek bir
WordPress/WooCommerce kurulumunda uçtan uca test EDİLEMEDİ (aynı ortam
kısıtı) - özellikle e-posta HTML render'ı ve 404 sayfasının gerçek bir
tarayıcıda görünümü kullanıcının kendi ortamında doğrulanmalı.

### 73. Görsel/UX Tur 2: mini sepet, kategori banner'ları, ödeme sonrası kutlama, ürün hızlı önizleme

Aynı "Ben sıralayıp parça parça ilerleyeyim" yetkisiyle devam eden ikinci
tur - birikimden 5 madde seçildi: kırıntı navigasyonu, mini sepet,
kategori banner'ları, ödeme sonrası kutlama animasyonu, ürün hızlı
önizleme. Tamamı tema-only (bu turda hiçbir eklenti dosyasına
dokunulmadı).

**Kırıntı navigasyonu**: araştırma, bunun ZATEN var olduğunu ortaya
çıkardı - WooCommerce'in kendi `woocommerce_breadcrumb()`'u
`woocommerce_before_main_content` önceliği 20'de hiç kaldırılmadan
çalışıyor, `assets/css/woocommerce.css`'in 10-27. satırları da onu zaten
stillendirmiş durumda (önceki bir turdan). Yeni iş yapılmadı.

**Mini sepet (kayan panel)**: header.php'deki "Sepetim" linki artık
doğrudan Sepetim sayfasına gitmek yerine bir kayan paneli açıyor -
`inc/woocommerce.php`'nin `scp_render_mini_cart_drawer()`'ı,
WooCommerce'in kendi `woocommerce_mini_cart()` şablon fonksiyonunu
(widget/shortcode'un da kullandığı `cart/mini-cart.php`) render ediyor.
Özel bir sepet REST uç noktası veya AJAX fragment-refresh mekanizması
KURULMADI: bu platformda sepete ekleme zaten tam sayfa yeniden
yüklemesiyle oluyor (tekil ürün sayfasındaki standart WC formu; mağaza
listesindeki linkler `scp_replace_loop_add_to_cart_link()` ile "Öğrenci
Seç"e çevrilmiş, ayrıca ajax değil) - yani header her sayfa
yüklemesinde ZATEN güncel sepeti render ediyor. `assets/js/scp-ui-kit.js`'in
`initMiniCart()`'ı yalnızca aç/kapat etkileşimini yönetiyor.

**Kategori banner'ları**: `scp_render_category_banner()`,
`woocommerce_before_shop_loop` önceliği 4'te (filtre çubuğundan ÖNCE) -
kategorinin WooCommerce'in kendi "Ürün kategorileri" ekranındaki "Görsel"
alanı (`thumbnail_id` term meta'sı, kategori ızgarasının zaten kullandığı
AYNI alan) varsa geniş bir arka plan banner'ı, açıklaması varsa üzerinde
metin olarak basıyor. Kategori ADI burada TEKRAR basılmadı - WC'nin kendi
`archive-product.php`'si (dokunulmadı) zaten ayrı bir
`.woocommerce-products-header__title` başlığı basıyor; o başlık bu turda
CSS ile banner'la görsel bütünlük kuracak şekilde stillendirildi.

**Ödeme sonrası kutlama animasyonu**: `initOrderCelebration()`,
`body.woocommerce-order-received` sınıfını kontrol ediyor - bu sınıf
WooCommerce'in KENDİ `wc_body_class()`'ı tarafından zaten ekleniyor
(`is_order_received_page()` true olduğunda), yani ayrı bir PHP koşulu/
enqueue şartı kurmaya gerek yok. Üçüncü parti bir kütüphane KULLANILMADI -
birkaç saniyeliğine DOM'a eklenip kaldırılan, CSS `@keyframes` ile düşen
basit `<span>` parçacıkları. `prefers-reduced-motion: reduce` için ayrı
bir kontrol de YAZILMADI - theme.css'in bölüm 66'dan beri var olan global
`* { animation-duration: 0.01ms !important }` geçersiz kılması zaten tüm
CSS animasyonlarını (bunu da) kapsıyor.

**Ürün hızlı önizleme**: mağaza ızgarasındaki her karta bir "Hızlı Bakış"
düğmesi + o ürünün detaylarını (görsel, fiyat, kısa açıklama) taşıyan
gizli bir `<template>` ekleniyor (`scp_render_quick_view_trigger()`,
`woocommerce_after_shop_loop_item` önceliği 15). Ayrı bir REST çağrısı/
AJAX KURULMADI - araştırma, `ProductsRestController`'ın
`canViewProducts()` izin denetiminin yalnızca personelde bulunan
`scp_view_products`/`scp_manage_products` yetkisini istediğini, mağazayı
gezen bir veli'de bu yetkinin OLMADIĞINI ortaya çıkardı; bu yüzden
detaylar sayfa zaten render edilirken sunucu tarafında basılıyor,
`initQuickView()` yalnızca bu ZATEN VAR olan `<template>` içeriğini bir
modale klonluyor. Sepete ekleme bilerek modalin içine TAŞINMADI - "Ürün
Sayfasına Git" linki öğrenci seçiminin yapıldığı tekil ürün sayfasına
yönlendiriyor; sepet/harcama limiti/fiyat kuralı doğrulamalarını burada
yeniden uygulamaktan kaçınmak için kasıtlı bir kapsam sınırı.

**Doğrulama**: `php -l` + `vendor/bin/phpcs` (tüm değişen dosyalar) temiz.
`node --check` (`scp-ui-kit.js`) temiz. Bu turda hiçbir eklenti dosyasına
dokunulmadığından PHPUnit çalıştırılmadı (değişen tek şey tema). Gerçek
bir WordPress/WooCommerce kurulumunda uçtan uca test EDİLEMEDİ (aynı ortam
kısıtı) - özellikle mini sepetin `woocommerce_mini_cart()` çıktısı,
kategori banner'ının gerçek bir kategori görseliyle görünümü ve hızlı
önizleme modalinin gerçek ürün verileriyle davranışı kullanıcının kendi
ortamında doğrulanmalı.

### 74. Görsel/UX Tur 3: gelişmiş mağaza filtreleri, ürün galerisi zoom, boş sepet illüstrasyonu, yukarı kaydır, sipariş numarası kopyalama

Üçüncü tur, aynı yetkiyle devam - 5 madde seçildi. Bu turda da hiçbir
eklenti dosyasına dokunulmadı (tema-only).

**Gelişmiş mağaza filtreleri**: `scp_render_shop_filters()`'e fiyat
aralığı (min/maks) + "yalnızca stokta olanlar" alanları eklendi, AYRI bir
`<form method="get">` olarak (arama kutusuyla aynı forma konulmadı - biri
gönderildiğinde diğerinin alanı kaybolmasın diye; arama terimi varsa
gizli bir `s` input'uyla yeni forma taşınıyor). `scp_apply_shop_filters()`
(`pre_get_posts`, yalnızca ana ürün arşivi sorgusu) bu GET
parametrelerini WooCommerce'in kendi `_price`/`_stock_status`
postmeta alanlarına (fiyat aralığı widget'ının/katalog filtrelerinin de
kullandığı AYNI, dokümante edilmiş alanlar) bir `meta_query` olarak
uyguluyor.

**Ürün galerisi yakınlaştırma**: kendi zoom/lightbox'ımız YAZILMADI -
araştırma, WooCommerce'in kendi bundled PhotoSwipe tabanlı zoom/
lightbox/slider'ının `add_theme_support('wc-product-gallery-zoom'/
'-lightbox'/'-slider')` bildirimleri OLMADAN hiç enqueue edilmediğini
ortaya çıkardı (`wc_current_theme_supports_gallery_zoom()` vb. - WC'nin
tema entegrasyonunda bilinçli olarak opt-in). Bu üç satır
`inc/setup.php`'ye eklenerek WC'nin zaten test edilmiş, kendi kendine
yeten (CDN'e çıkmayan) kütüphanesi açıldı - hiçbir şablon/JS dosyası
değişmedi.

**Sepet sayfası boş durum illüstrasyonu**: WooCommerce'in kendi
`cart/cart-empty.php` şablonu (dokunulmadı) restyled - `.cart-empty`
(WC'nin stabil, uzun süredir değişmeyen sınıf adı) artık ortalanmış,
ikonlu bir kart olarak görünüyor. İkon bir `background-image` SVG data
URI değil, bir CSS `mask-image` - `background-color: var(--scp-text-muted)`
üzerinden tema token'ıyla renkleniyor (bir `background-image` SVG'nin
`currentColor`/CSS değişkenlerini güvenilir şekilde alması tarayıcılar
arası tutarlı değil). Belirsiz bir hook'a (`woocommerce_cart_is_empty`'in
tam olarak hangi WC sürümünde/sırada ateşlendiği gibi) bağımlı KALINMADI -
yalnızca kesin, dokümante edilmiş sınıf adları kullanıldı.

**Yukarı kaydır düğmesi**: `initScrollToTop()`, 400px'ten fazla
kaydırıldığında beliren, sayfanın başına yumuşak kaydıran sabit bir
düğme - her kimliği doğrulanmış sayfada global (scp-ui-kit.js). Etiketi
`scpPanelText.scrollToTop`'tan okunuyor ama scp-ui-kit.js bu değişkeni
localize eden bir "panel" script'i OLMADIĞINDAN (bkz. bu dosyanın kendi
docblock'u - paylaşılan temel dosya, tüketici değil) değişkenin o
sayfada hiç var olmama ihtimaline karşı savunmacı okunuyor
(`typeof scpPanelText !== 'undefined' && ...`), sabit bir Türkçe
fallback'e düşerek.

**Sipariş numarası kopyalama düğmesi**: `orders-panel.js` (veli) ve
`admin-orders-panel.js` (admin/şube) - ikisinde de AYNI
`renderOrderNumberCopyButton()` yinelendi (bu kod tabanının küçük,
sayfa-bağlamına-özel yardımcıları paylaşılan bir dosya yerine yinelemesi
kuralına uygun, bkz. bölüm 68). `navigator.clipboard.writeText` yoksa
(güvensiz bağlam/eski tarayıcı) düğme hiç render EDİLMİYOR - sessizce
çalışmayan bir düğme bırakmak yerine. Başlık artık `.scp-card__header`'ın
DOĞRUDAN çocuğu değil, yeni bir `.scp-card__header-title` sarmalayıcının
içinde (başlık+kopyala düğmesi bir arada) - `justify-content: space-between`
üç öğe yerine iki "mantıksal" öğeyi (başlık grubu, durum rozeti) ayırsın
diye.

**Doğrulama**: `php -l` + `vendor/bin/phpcs` (tüm değişen dosyalar) temiz
- kalan uyarılar (fiyat/stok filtresinin GET parametreleri için nonce
uyarıları, WP'nin kendi `?s=` arama deseniyle aynı kategoriden salt-okunur
filtreler için beklenen/kabul edilen; `inc/assets.php`'nin satır uzunluğu
uyarısı önceki turdan) yeni değil. `node --check` (`orders-panel.js`,
`admin-orders-panel.js`, `scp-ui-kit.js`) temiz. Bu turda hiçbir eklenti
dosyasına dokunulmadığından PHPUnit çalıştırılmadı. Gerçek bir WordPress/
WooCommerce kurulumunda uçtan uca test EDİLEMEDİ (aynı ortam kısıtı) -
özellikle fiyat/stok filtresinin gerçek ürün verisiyle davranışı, WC'nin
PhotoSwipe zoom/lightbox'ının gerçek bir ürün galerisiyle görünümü ve
Clipboard API'nin gerçek bir tarayıcıda (http değil, https/localhost)
davranışı kullanıcının kendi ortamında doğrulanmalı.

### 75. Görsel/UX Tur 4: mobil kart görünümlü tablolar, ürün rozetleri, bildirim zili animasyonu, genel ağ etkinliği göstergesi

Dördüncü tur - bu turda tema-only kalmaya devam edildi. İki madde, önceki
turlarda ertelenen veya kapsamı önemli ölçüde değişen maddelerdi.

**Mobil kart görünümlü tablolar**: önceki bir turda "her paneli tek tek
değiştirmek gerekir" diye ertelenmişti - bu turda GENEL bir çözümle
(sıfır panel-script değişikliği) çözüldü.
`scp-ui-kit.js`'in `initResponsiveTables()`'ı her `.scp-table`'ın kendi
`<thead th>` metnini okuyup her `<td>`'ye karşılık gelen `data-label`
yazıyor; `panel.css`'in 640px altı kuralı `.scp-table--responsive-cards`
(yalnızca etiketleme BAŞARILI olduğunda eklenen sınıf) tabloyu yatay
kaydırma yerine etiketli kartlara çeviriyor. Sorun: panellerin çoğu
tablo satırlarını KENDİ REST çağrısıyla, DOMContentLoaded'DAN SONRA
dolduruyor (bazıları statik `<thead>`'i olan ama başlangıçta boş bir
`<tbody>`'yi dolduruyor, `orders-panel.js`/`admin-orders-panel.js`'in
sipariş kalemleri tablosu gibi ikisi ise TÜM `<table>`'ı JS'te sıfırdan
kurup sonradan ekliyor) - tek seferlik bir DOMContentLoaded taraması bu
satırların hiçbirini yakalamazdı. Çözüm: TEK bir `document.body` geneli
MutationObserver, herhangi bir DOM değişikliğinde sayfadaki tüm
`.scp-table`'ları yeniden tarayıp etiketliyor - hem "zaten var olan ama
sonradan doldurulan tablo" hem "sonradan sıfırdan eklenen tablo"
durumunu TEK mekanizmayla kapsıyor.

**Ürün rozetleri**: araştırma, "İndirimde" rozetinin WooCommerce'in kendi
varsayılan `woocommerce_show_product_sale_flash()`'ı üzerinden ZATEN var
olduğunu ve zaten stillendirildiğini ortaya çıkardı - yeni iş yalnızca
"Yeni" rozetiydi (ürün 14 günden daha yeni yayınlanmışsa, WP'nin kendi
`post_date`'i üzerinden). "İndirimde" ile "Son N adet!" (düşük stok)
rozetlerinin İKİSİ DE sol üst köşede aynı konumda (`top:12px; left:12px`)
- önceden fark edilmemiş bir üst üste binme hatası. Bunu bir sarmalayıcı
ile düzeltmek/yeniden konumlandırmak yerine ("Yeni" rozeti dahil üçünü
tek bir dikey rozet yığınına almak), bilinçli olarak YAPILMADI: WC'nin
mağaza ızgarası şablonundaki thumbnail + sale-flash hook'larının TAM
önceliğini bu sandbox'ta canlı bir WooCommerce kurulumu olmadan
doğrulayamıyoruz - yanlış tahmin edilen bir öncelik aralığı, sarmalayıcının
ürün GÖRSELİNİ de içine alıp mağaza ızgarasını bozabilirdi. Bunun yerine
"Yeni" rozeti tamamen AYRI bir köşeye (sağ üst) kondu - hem çakışmayı
önlüyor hem hiçbir mevcut hook/CSS'e dokunmuyor. Var olan "İndirimde"/
düşük-stok çakışması dokunulmadan (önceden var olan, bu turun kapsamı
dışında bir sorun olarak) bırakıldı.

**Bildirim zili rozet animasyonu + panel geçişi**: rozet, okunmamış sayı
BİR ÖNCEKİ kontrolden fazla çıktığında (0→N ilk yüklemede de sayılır,
gerçek yeni bir bildirim gibi) kısa bir "pop" animasyonu alıyor -
`markRead()`'in kendi `refreshBadge()` çağrısı sayıyı DÜŞÜRDÜĞÜNDE tekrar
tetiklenmiyor. Panel: araştırma, AÇILIŞIN zaten `.scp-notif-bell__panel`'in
kendi `animation` özelliği üzerinden (hidden→false her seferinde CSS
animasyonunu yeniden başlatıyor) animasyonlu olduğunu, ama KAPANIŞIN
`hidden = true` ile ANINDA gerçekleştiğini (hiç animasyonsuz) ortaya
çıkardı - asıl eksik yalnızca kapanıştı. `closePanel()` artık bir
`--closing` sınıfı ekleyip `hidden = true`'yu YALNIZCA o animasyonun
`animationend`'i ateşlendiğinde yazıyor - `scp-ui-kit.js`'in kendi
`scpToast()`'unun "leaving" durumuyla AYNI desen.

**Genel ağ etkinliği göstergesi**: `Kaydet düğmesi yükleniyor/başarı
durumları` maddesi araştırma sırasında kapsamı değişti - bir düğme-bazlı
spinner, HANGİ düğmenin HANGİ isteği tetiklediğini bilmesi gerekirdi (her
panel script kendi fetch'ini kendi başına yönetiyor, tek bir merkezi
"bu istek şu düğmeye ait" eşlemesi yok). Bunun yerine TEK bir merkezi
kilit noktası kullanıldı: her panel script'in REST çağrısı ZATEN
`scp-api-fetch.js`'in `scpApiFetch()`'inden geçiyor - bu TEK fonksiyona
`scpBeginNetworkActivity()`/`scpEndNetworkActivity()` (yeni,
`scp-ui-kit.js`) kancalanarak, sayfanın en üstünde ince bir ilerleme
çubuğu HERHANGİ bir istek uçuştayken (yalnızca form gönderimleri değil,
liste yüklemeleri de dahil) beliriyor - hiçbir panel script'ine
dokunmadan. Bir sayaç (boolean değil) kullanıldı ki iki örtüşen istekten
biri bitince çubuk erken kaybolmasın.

**Doğrulama**: `php -l` + `vendor/bin/phpcs` (tüm değişen dosyalar) temiz
- kalan uyarılar (nonce) önceki turdan, yeni değil. `node --check`
(`notifications-bell.js`, `scp-api-fetch.js`, `scp-ui-kit.js`) temiz. Bu
turda hiçbir eklenti dosyasına dokunulmadığından PHPUnit çalıştırılmadı.
Gerçek bir WordPress/WooCommerce kurulumunda uçtan uca test EDİLEMEDİ
(aynı ortam kısıtı) - özellikle mobil kart tablolarının gerçek panel
verisiyle davranışı, "Yeni" rozetinin gerçek ürün yayın tarihleriyle
görünümü ve ağ etkinliği çubuğunun gerçek bir tarayıcıda zamanlaması
kullanıcının kendi ortamında doğrulanmalı.

### 76. Görsel/UX Tur 5: manuel karanlık mod, baş harf avatarı, klavye kısayolları yardımı, sipariş durum sekmeleri

Beşinci tur - tema-only kalmaya devam edildi.

**Manuel karanlık mod anahtarı**: header.php'ye bir açma/kapama düğmesi
eklendi, seçim `localStorage.scpTheme`'e yazılıyor. Asıl kritik kısım
ERKEN uygulama: `assets/js/scp-ui-kit.js`'in `initThemeToggle()`'ı bir
FOOTER script'i (`in_footer: true`) olduğundan, tercih orada uygulansaydı
sayfa YANLIŞ temayla bir kare boyandıktan SONRA doğru temaya geçerdi
(görünür bir "flaş"). Bunun yerine `inc/setup.php`'nin yeni
`scp_theme_preload_script()`'i `<head>`'e, `wp_head()`'DEN (CSS
`<link>`'lerin basıldığı yer) ÖNCE, satır içi bir `<script>` olarak
basılıyor - `header.php` VE `templates/login.php`'nin İKİSİNDE de (giriş
ekranında düğme YOK, ama saklanan tercih orada da saygı görüyor,
`auth.css`'in kendi ayrı token bloklarına `[data-theme]` desteği
eklenerek). CSS tarafı: `@media (prefers-color-scheme: dark)` bloğu
`:not([data-theme="light"])` ile daraltıldı (açık moda zorlanmışsa OS
karanlık dese bile kazansın), `[data-theme="dark"]` ayrı bir blok da
aynı token değerlerini taşıyor (OS açık modda bile karanlığa
zorlanabilsin) - plain CSS'te mixin olmadığından iki blok da AYNI değerleri
tekrarlıyor, tıpkı auth.css'in kendi (farklı) token setini zaten
tekrarladığı gibi.

**Kullanıcı/öğrenci baş harf avatarı**: `inc/setup.php`'nin
`scp_render_avatar()`'ı (sunucu tarafı, header.php'nin kullanıcı adı için)
ve `scp-ui-kit.js`'in `scpAvatar()`'ı (istemci tarafı, Öğrenciler
tablosunun adı JS'te render ettiği için) AYNI görünümü üretiyor ama
BİREBİR aynı renk sonucu üretmeye ÇALIŞILMADI - PHP'nin UTF-8 byte'ları ile
JS'in UTF-16 code unit'leri Türkçe karakterli adlarda (İ, ş, ğ, ü, ö, ç)
aynı hash'i güvenilir şekilde üretemez; her bağlamın kendi içinde tutarlı
olması (aynı ad her zaman aynı rengi alır) yeterli görüldü.

**Klavye kısayolları yardım ekranı**: "?" tuşu (`event.key === '?'`,
klavye düzeninden bağımsız) `.scp-modal-overlay`/`.scp-modal`'ı (command
palette/quick view'ın da yaptığı gibi elle kurulmuş) yeniden kullanan bir
liste açıyor - mevcut ⌘K/Esc kısayollarını + "?" tuşunun kendisini
belgeliyor (uygulamada başka klavye kısayolu YOK, araştırma bunu
doğruladı). Bir metin alanına yazarken "?" karakterinin kendisini
yakalamaması için odaklı öğe input/textarea/select/contenteditable ise
hiç tetiklenmiyor.

**Sipariş listesi durum sekmeleri**: araştırma, admin sipariş
panelindeki filtre formunun ZATEN tam bir durum `<select>`'ine sahip
olduğunu (sunucu tarafında, `AdminOrdersRestController`'a giden bir GET
parametresi) ortaya çıkardı - istemci tarafında AYRI bir filtreleme
mekanizması kurmak hem gereksiz hem de iki kaynak arasında tutarsızlık
riski taşırdı. Bunun yerine `renderStatusTabs()`, o AYNI `<select>`'in
kendi `<option>`'larından (ikinci bir sabit durum listesi icat edilmeden)
tıklanabilir sekmeler üretiyor - bir sekmeye tıklamak `select.value`'yu
ayarlayıp `loadOrders()`'ı (formun kendi submit'inin çağırdığı AYNI
fonksiyon) çağırıyor. Yalnızca admin/şube sipariş paneline uygulandı -
veli'nin "Siparişlerim" sayfasında zaten bir durum filtre formu yok.

**Doğrulama**: `php -l` + `vendor/bin/phpcs` (tüm değişen dosyalar) temiz
- kalan uyarı (`inc/assets.php` satır uzunluğu) önceki turdan, yeni değil.
`node --check` (`admin-orders-panel.js`, `scp-ui-kit.js`,
`students-panel.js`) temiz. Bu turda hiçbir eklenti dosyasına
dokunulmadığından PHPUnit çalıştırılmadı. Gerçek bir WordPress/WooCommerce
kurulumunda uçtan uca test EDİLEMEDİ (aynı ortam kısıtı) - özellikle
karanlık mod anahtarının gerçek bir tarayıcıda flaşsız geçiş yapıp
yapmadığı, avatar renklerinin gerçek Türkçe adlarla görünümü ve durum
sekmelerinin gerçek sipariş verisiyle davranışı kullanıcının kendi
ortamında doğrulanmalı.

### 77. Görsel/UX Tur 6: WhatsApp bildirim kanalı, ürün etiketi filtre çipleri, sipariş listesi CSV dışa aktarma

Altıncı tur - bu turda ilk kez tema-only sınırının dışına çıkılıp
`seviye-notifications` eklentisine de dokunuldu.

**WhatsApp bildirim kanalı (backend)**: `WhatsAppChannel` (yeni),
`NetgsmSmsChannel`'ın BİREBİR aynı desenini izliyor - Settings-backed
kimlik bilgileri (`SETTING_PHONE_NUMBER_ID`, `SETTING_ACCESS_TOKEN`),
`wp_remote_post` ile `POST https://graph.facebook.com/v20.0/{phone_number_id}/messages`,
"boş = devre dışı" kapısı, SDK bağımlılığı yok. `NotificationChannel`
enum'una `WHATSAPP` case'i eklendi; bu, `WpRecipientResolver::resolve()`'ın
`$channel` üzerindeki (default kolu OLMAYAN) exhaustive `match()`'ini
BOZARDI - tüm eklenti genelinde grep ile bu match'in TEK exhaustive match
olduğu doğrulandı, `WHATSAPP` mevcut `SMS` koluna eklenerek çözüldü (ikisi
de aynı kaynaktan, `ParentContactLookupInterface::phoneFor()`'dan telefon
numarası istiyor). `NotificationsSettingsRestController`'a
`/notifications/whatsapp-settings` GET/PUT rotaları eklendi (mevcut
`show()`/`update()` deseniyle birebir - `phone_number_id` gizli değil,
`access_token` yalnızca yazılabilir sır).

**WhatsApp'ın gerçek dünya kısıtı bilinçli olarak GİZLENMEDİ**: WhatsApp
Business Cloud API, serbest metin (`type: text`) bir mesajı yalnızca
alıcının işletmeyle son 24 saat içinde yazışmış olması durumunda TESLİM
EDİYOR; bunun dışında Meta tarafından önceden onaylanmış bir ŞABLON mesajı
gerekiyor (şablon onayı, Meta İşletme Hesabı'nda ayrıca yapılması gereken
ve bu kod tabanının erişemeyeceği bir kurulum adımı). Bu kısıt HEM
`WhatsAppChannel`'ın kendi docblock'unda HEM DE
`templates/whatsapp-settings-admin.php`'deki ekran üstü ipucu metninde
açıkça belgelendi - sessizce çalışıyormuş gibi görünüp sık sık teslim
edilmeyen bir özellik olarak bırakılmadı.

**WhatsApp ayar sayfası (tema)**: `sms-ayarlari` deseninin birebir
kopyası - `inc/zones.php`'ye yeni `whatsapp-ayarlari` menü sayfası,
`assets/js/whatsapp-settings-panel.js` (`notifications-settings-panel.js`
ile aynı yapı, `/notifications/whatsapp-settings` hedefli),
`inc/sidebar.php`'de "SMS Ayarları" ile "E-posta Ayarları" arasına link.
Toplu duyuru formuna (`broadcast-admin.php`) da `channel_whatsapp`
onay kutusu eklendi (`broadcast-panel.js`'in `selectedChannels()`'ı
`'whatsapp'`'ı da gönderiyor).

**Ürün etiketi (tags) filtre çipleri**: `scp_render_shop_filters()`'a
ikinci bir `<nav>` bloğu eklendi - kategori çiplerinin AYNI deseni,
ama `product_tag` taksonomisi için. `get_terms()` çağrısı
`hide_empty => true` VE `number => 20` sınırıyla korunuyor (mağazada
onlarca etiket varsa filtre satırının sonsuza taşmaması için); hiç
etiket yoksa blok hiç basılmıyor.

**Sipariş listesi CSV dışa aktarma**: yeni bir REST endpoint'i YOK -
"CSV İndir" düğmesi, `admin-orders-panel.js`'in `loadOrders()`'ının EN
SON başarılı çağrıda zaten aldığı diziyi (`lastLoadedOrders`) doğrudan
CSV'ye çeviriyor. Yani dışa aktarılan veri her zaman filtre formunun o
anki sonucudur - ayrı bir "tüm siparişleri getir" isteği yok. UTF-8 BOM
(`'﻿'`) önekiyle başlıyor, çünkü bu dosyanın en olası açılacağı araç
Excel ve BOM olmadan Türkçe karakterler (ş, ğ, ü, ö, ç, ı, İ) bozuk
görünür. İndirme, `Blob` + `URL.createObjectURL` + geçici bir
`<a download>` tıklaması ile tetikleniyor (sunucuya gitmeyen, tamamen
istemci tarafı bir işlem).

**Doğrulama**: `php -l` + `vendor/bin/phpcs` (tüm değişen dosyalar, hem
`seviye-notifications` hem tema) temiz - kalan uyarılar (`inc/assets.php`
satır uzunluğu, `inc/woocommerce.php`'nin GET tabanlı mağaza filtreleri
için nonce uyarıları) önceki turlardan, yeni değil. `node --check`
(`whatsapp-settings-panel.js`, `broadcast-panel.js`,
`admin-orders-panel.js`) temiz. `seviye-notifications`'ta PHPUnit: 55/55
geçti (yeni `WhatsAppChannelTest.php` dahil, önceki tur 48 testti). Gerçek
bir WordPress/WooCommerce + gerçek bir Meta İşletme Hesabı kurulumunda
uçtan uca test EDİLEMEDİ (aynı ortam kısıtı) - özellikle WhatsApp
mesajının gerçekten teslim edilip edilmediği, 24 saatlik pencerenin
gerçek davranışı ve CSV'nin gerçek Excel'de Türkçe karakterlerle açılışı
kullanıcının kendi ortamında doğrulanmalı.

### 78. Görsel/UX Tur 7: Yazdırılabilir sipariş görünümü, son görüntülenen ürünler, admin sipariş listesinde toplu işlem

Yedinci tur - tekrar tema-only.

**Yazdırılabilir sipariş görünümü**: `window.scpPrintOrder()`
(`scp-ui-kit.js`, paylaşılan tek implementasyon) hem `orders-panel.js`
(veli) hem `admin-orders-panel.js` (admin/şube) tarafından çağrılıyor.
Sayfanın geri kalanını (header, sidebar, filtre formu, DİĞER sipariş
kartları) `@media print` içinde gizlemeye/göstermeye çalışmak YERİNE -
ki bu iki sayfanın farklı DOM yerleşimleri için ayrı mantık gerektirirdi
ve yanlış giderse header/sidebar da yazdırılabilirdi - ham sipariş
verisinden TEMİZ, sayfa yerleşiminden tamamen bağımsız bir fiş DOM
parçası inşa edip `#scp-print-order-root`'a yazıyor; bu kök normalde
`display: none`, yalnızca `body.scp-printing-order` sınıfı VARKEN
(`window.print()` çağrısının süresi boyunca) görünür oluyor. Temizlik
hem `afterprint` olayıyla HEM DE 2 saniyelik bir zaman aşımı yedeğiyle
yapılıyor - `afterprint` her tarayıcı/print-preview akışında güvenilir
ateşlenmiyor, ama yazdırma diyalogları modal olduğundan zaman aşımı
ateşlendiğinde kullanıcı zaten yazdırmış ya da vazgeçmiş oluyor.

**Son görüntülenen ürünler**: tamamen istemci tarafı - yeni bir REST
endpoint'i veya sunucu tarafı oturum verisi YOK.
`scp_render_recently_viewed_marker()` (yalnızca `woocommerce_single_product_summary`
kancasında, `global $product` zaten hazırken) o anki ürünün verisini
(id, ad, url, görsel, fiyat) gizli bir işaretçinin data-* öznitelikleri
olarak basıyor; `scp-ui-kit.js`'in `initRecentlyViewed()`'i bunu
`localStorage.scpRecentlyViewed`'e yazıyor (en yeni önde, en fazla 8
kayıt, id'ye göre tekilleştirilmiş) VE AYNI fonksiyon
`scp_render_recently_viewed_strip()`'in boş kabını (WC'nin kendi ilgili
ürünler bölümünün hemen ardından, `woocommerce_after_single_product_summary`
priority 25) o anki ürün HARİÇ en fazla 6 kayıtla dolduruyor. Hiç kayıt
yoksa veya listede o anki üründen başka ürün yoksa kap boş kalıyor -
diğer generic `initXxx()` fonksiyonlarıyla (`initResponsiveTables()` vb.)
aynı "veri yoksa sessizce hiçbir şey render etme" deseni.

**Admin sipariş listesinde toplu işlem**: her sipariş kartına (yalnızca
`scpPanelData.canUpdateFulfillment` varsa) bir seçim kutusu eklendi; bir
veya daha fazla sipariş seçildiğinde filtre formunun altında bir toplu
işlem çubuğu beliriyor. Toplu eylem olarak YALNIZCA "Teslim Edildi Olarak
İşaretle" sunuluyor - "Kargoya Ver" (bulk) BİLİNÇLİ OLARAK eklenmedi,
çünkü `shipOrder()` her sipariş için AYRI bir kargo takip numarası
istiyor (`window.prompt`); bunu toplu bir akışta da yapmaya çalışmak ya
tüm seçili siparişlere AYNI (yanlış) takip numarasını verecek ya da N
kez prompt açacaktı - ikisi de gerçek kargo takibini bozardı. Toplu
teslim işlemi, seçili siparişlerden `FULFILLABLE_STATUSES`/
`fulfillment_status` kontrolüne uymayanları (server'ın da zaten
reddedeceği) isteği hiç göndermeden atlıyor ve kaç siparişin
atlandığını durum mesajında bildiriyor - sunucu tarafı, tek tek
`deliver()` çağrılarının aynısı olduğundan (`Promise.all` ile paralel),
her sipariş için AYRI ayrı capability/durum denetiminden geçiyor; stale
bir seçim asla sunucu tarafı kuralını bypass edemez.

**Doğrulama**: `php -l` + `vendor/bin/phpcs` (tüm değişen dosyalar) temiz
- kalan uyarılar (`inc/assets.php` satır uzunluğu,
`inc/woocommerce.php`'nin GET tabanlı mağaza filtreleri için nonce
uyarıları) önceki turlardan, yeni değil. `node --check`
(`scp-ui-kit.js`, `orders-panel.js`, `admin-orders-panel.js`) temiz. Bu
turda hiçbir eklenti dosyasına dokunulmadığından PHPUnit çalıştırılmadı.
Gerçek bir WordPress/WooCommerce kurulumunda uçtan uca test EDİLEMEDİ
(aynı ortam kısıtı) - özellikle yazdırma çıktısının gerçek bir yazıcıda/
PDF'e kaydet akışında nasıl göründüğü, "son görüntülenen ürünler"in
gerçek Türkçe ürün adları+fiyatlarıyla davranışı ve toplu teslim
işleminin çok sayıda (örn. 50+) sipariş seçiliyken performansı
kullanıcının kendi ortamında doğrulanmalı.

### 79. Stok gelince haber ver (back-in-stock bildirimi)

Görsel/UX turlarının ötesine geçen, gerçek bir yeni özellik - hem Seviye
Commerce hem Seviye Notifications'a dokunuyor.

**Abonelik verisi (Commerce)**: yeni `scp_stock_subscriptions` tablosu -
`(product_id, user_id)` üzerinde UNIQUE, FK YOK (`product_id`/`user_id`
WooCommerce'in/WordPress'in kendi çekirdek tablolarına işaret ediyor - bu
platform çekirdek tablolara asla FK koymuyor). BİR KEZLİK abonelik: bir
üründe stok geldiğinde bildirim gönderildikten SONRA o ürünün TÜM
abonelik kayıtları siliniyor (`WpdbStockSubscriptionRepository::deleteAllFor()`)
- kalıcı bir izleme değil, "bir dahaki sefere haber ver" niyeti; ürün
tekrar stoksuz kalıp tekrar gelirse veli yeniden abone olmalı.

**Stok geçişini yakalama**: `BackInStockNotificationHooks`,
`LowStockNotificationHooks`'un (düşük stok) AYNI deseni ama TERS yönde -
WooCommerce'in kendi `woocommerce_product_object_updated_props` kancasını
kullanıyor (bir `WC_Product::save()` sonrası GERÇEKTEN DEĞİŞEN alanların
listesiyle çağrılıyor). `in_array('stock_status', $updatedProps, true)`
kontrolü, bu kod'un yalnızca stok durumu GERÇEKTEN değiştiğinde
tetiklenmesini sağlıyor - `$product->get_stock_status() === 'instock'`i
TEK BAŞINA kontrol etmek, zaten stoktaki bir ürünün ilgisiz her
kaydedilişinde (fiyat güncellemesi gibi) yanlışlıkla tetiklenirdi.

**Tek olay, tek alıcı deseni**: `commerce.product_low_stock` (platform
geneli, TEK olay, Genel Merkez'in TAMAMI okur) aksine,
`commerce.stock_subscription_fulfilled` HER ABONE İÇİN AYRI AYRI
fırlatılıyor - `OrderPlacedNotificationListener`'ın tek-müşteri-tek-olay
şekliyle aynı. `BackInStockNotificationListener` bu event'i dinleyip
PANEL+EMAIL bildirimi gönderiyor (Notifications, Commerce'in sınıflarına
değil yalnızca bu event adı/payload şekline bağımlı - platformun her
yerinde tekrarlanan kural).

**REST + tema UI**: `seviye/v1/commerce/stock-subscriptions` - POST
(abone ol), DELETE `{product_id}` (aboneliği iptal et), GET `{product_id}`
(o anki durumu sorgula) - üçü de `get_current_user_id()`'ye göre
kapsamlı, istemcinin verdiği bir id'ye göre DEĞİL (her `*/mine` şeklindeki
endpoint'in aynı kuralı). `scp_view_own_children` (ProductReviewGate'in
kullandığı AYNI veli-only capability) ile korunuyor. Tema tarafında
`scp_render_stock_subscription()` yalnızca STOKTA OLMAYAN bir üründe boş
bir kap basıyor; `scp-ui-kit.js`'in `initStockSubscription()`'ı GET ile
o anki durumu okuyup abone-ol/aboneliği-iptal-et arasında geçiş yapan
TEK bir düğme render ediyor. Bu sayfada ayrı bir `scpPanel`/`scpPanelText`
yerelleştirmesi GEREKMEDİ - `notifications-bell.js` her girişli
kullanıcı için HER sayfada (ürün sayfaları dahil) koşulsuz enqueue
edildiğinden ve AYNI `$localized`/`$text` PHP dizilerini yerelleştirdiğinden,
bu globaller ürün sayfalarında zaten mevcut.

**Doğrulama**: `php -l` + `vendor/bin/phpcs` (tüm değişen dosyalar, hem
iki eklenti hem tema) temiz - kalan uyarılar önceki turlardan, yeni
değil. `node --check` (`scp-ui-kit.js`) temiz. PHPUnit: `seviye-commerce`
23/23 (yeni `WpdbStockSubscriptionRepositoryTest` dahil, önceki tur
23'tü - fark yok, çünkü bu turda BAŞKA bir Commerce testi
eklenmemişti), `seviye-notifications` 57/57 (yeni
`BackInStockNotificationListenerTest` dahil, önceki tur 55'ti).
`BackInStockNotificationHooks` (WC_Product'a doğrudan dokunuyor)
KASITLI OLARAK unit test EDİLMEDİ - `LowStockNotificationHooks` için de
aynı kural geçerli (bkz. bu dosyanın "Test stratejisi" bölümü, WC/WP'ye
doğrudan dokunan adaptörler yalnızca fake/guard üzerinden test edilir,
doğrudan değil). Gerçek bir WordPress/WooCommerce kurulumunda uçtan uca
test EDİLEMEDİ (aynı ortam kısıtı) - özellikle
`woocommerce_product_object_updated_props`'un gerçek bir stok
güncellemesinde (manuel admin düzenlemesi, sipariş sonrası otomatik
stok artırma/azaltma, toplu içe aktarma) beklenen `updated_props`
listesini gerçekten içerip içermediği ve e-postanın gerçekten teslim
edilip edilmediği kullanıcının kendi ortamında doğrulanmalı.

### 80. Güvenlik: native wp-login.php 2FA atlatması + CSV formula injection

Bu tur, sandbox'ın dışa erişimi izin verdiği bir yoldan (WordPress
çekirdeğini wordpress.org yerine kendi GitHub aynasından, WooCommerce'i
GitHub release'inden çekerek) geçici bir yerel WordPress + MariaDB
kurulumu yapılıp bu platform ilk kez GERÇEK bir çalışma zamanına karşı
test edilerek gerçekleşti - bölüm 79 ve öncesindeki "aynı ortam kısıtı"
notlarının işaret ettiği sınırlama bu tur için aşıldı (kalıcı bir
altyapı değişikliği değil, tek seferlik bir doğrulama ortamıydı). Bu,
sandbox'ta önceden hiç test edilemeyen bir sınıf hatayı ortaya çıkardı.

**Native login 2FA atlatması (Kritik, doğrulandı)**: Platformun T.C.
Kimlik No + Şifre + iki adımlı doğrulama + IP izin listesi giriş akışı,
WordPress'in kendi `wp-login.php`'sinden TAMAMEN bağımsız kuruldu -
kod tabanında `wp-login`, `wp_login`, `login_init`, `authenticate` için
yapılan aramalar SIFIR sonuç verdi. Şifreler `wp_set_password()` ile
gerçek, standart WordPress hash'leri olarak saklandığından (bkz.
`AuthRestController::resetPassword()`, `UserListPage`,
`UserAuthorizationAdminPage`), her veli/personel hesabının şifresi
`wp-login.php` için de geçerliydi - iki adımlı doğrulamayı
etkinleştiren bir hesap bile, WordPress'in kendi giriş formu üzerinden
YALNIZCA şifresiyle (2FA kodu SORULMADAN) tam bir oturum açabiliyordu.
Canlı kurulumda hem açığın var olduğu (2FA açık bir hesapla
`wp-login.php` üzerinden tam `wp-admin` erişimi elde edildi) hem de
düzeltmenin çalıştığı (aynı deneme artık reddediliyor, 2FA kapalı bir
hesap normal çalışmaya devam ediyor) doğrulandı.

Yeni `NativeLoginGate` (`seviye-security`), WordPress'in kendi
`wp_authenticate_username_password`/`wp_authenticate_email_password`
filtrelerinin çalıştığı önceliğin (20) HEMEN sonrasında (30) `authenticate`
filtresine bağlanıyor: eğer o ana kadar oluşan sonuç geçerli bir
`WP_User` ise VE bu kullanıcının `TwoFactorService::isEnabledForUser()`'ı
true dönüyorsa, sonucu bir `WP_Error`'a çeviriyor - `wp-login.php`'nin
2FA kodu sorma yeteneği olmadığından, bu hesap için native giriş
tamamen reddediliyor. Native `administrator` rolünün kendisi
BİLİNÇLİ OLARAK dokunulmadı bırakıldı - `SecurityModule::boot()`'un
`get_role('administrator')->add_cap(...)` çağrısı, sitenin gerçek
WordPress yöneticisinin de "Seviye Kullanıcılar" menüsüne erişebilmesi
için kasıtlı bir birlikte-var-olma yolu; bu düzeltme yalnızca "2FA
açık bir hesap 2FA'sız giriş yapabiliyor" boşluğunu kapatıyor, native
girişi toptan KAPATMIYOR. IP izin listesine de dokunulmadı -
`inc/ip-restriction.php`'nin kendi docblock'u bu kontrolün BİLİNÇLİ
OLARAK yalnızca sayfa render'ına (`template_redirect`) kapsandığını,
REST çağrılarını/zaten-var-olan-oturumları kapsamadığını zaten
belgeliyor ("çalınmış bir oturum çerezi bugün de her iki geçidi eşit
şekilde atlıyor") - bu, yeni bir boşluk değil, kayıtlı bir kapsam
kararı.

**CSV formula injection (Orta)**: bölüm 77'nin admin sipariş CSV dışa
aktarma özelliği, `order.customer_name`'in WooCommerce checkout'ta
velinin serbestçe girdiği fatura adı/soyadından geldiğini (bkz.
`OrderPresenter::present()`) hesaba katmıyordu - `=`, `+`, `-`, `@` ile
başlayan bir fatura adı, dışa aktarılan CSV Excel/Sheets/LibreOffice'te
açıldığında bir formül/DDE payload'ı olarak çalışabilirdi (OWASP CSV
Injection). `csvCell()` artık bu dört karakterden biriyle başlayan her
hücrenin önüne bir tek tırnak ekleyip hücreyi metne sabitliyor - bu
düzeltme `csvCell()`'in KENDİSİNDE olduğundan dışa aktarılan TÜM
sütunları (veli e-postası dahil) kapsıyor, yalnızca ada özel bir
istisna değil.

**Doğrulama**: `php -l` + `vendor/bin/phpcs` (değişen dosyalar) temiz.
`seviye-security`'de PHPUnit: 78/78 (yeni `NativeLoginGate` sınıfı WC/WP
hook'larına doğrudan dokunan diğer adaptörlerle (`LowStockNotificationHooks`,
`BackInStockNotificationHooks`) AYNI kuralla unit test EDİLMEDİ - yalnızca
`WP_User`/`WP_Error` tip ipuçları ve `add_filter()`/`__()` çağrıları
içeriyor, test bootstrap'ında bu sınıflar için stub yok). `node --check`
(`admin-orders-panel.js`) temiz. Her iki düzeltme de bu kez gerçek bir
WordPress + MariaDB kurulumunda UÇTAN UCA doğrulandı (yalnızca kod
okuması/statik analiz değil) - 2FA'yı etkinleştirip aynı native giriş
denemesini tekrarlayarak açığın kapandığı, 2FA'sız bir hesabın hâlâ
normal giriş yapabildiği ve CSV hücrelerinin artık tek tırnakla
sabitlendiği doğrudan gözlemlendi.

### 81. Üç kullanıcı raporu: sınıf bazlı ürün görünürlüğü (WooCommerce Coming Soon), kargo/teslimat e-posta karışıklığı, veli self-servis iade

Kullanıcının bildirdiği üç sorun ayrı ayrı incelendi; ikisi zaten farklı
kök nedenlere sahip çıktı, biri de gerçekten eksik bir özellikti.

**1. "Şube ürün eklediğinde şubenin öğrencisi ürünü göremiyor (aynı sınıfta
olsa bile)"** - `ProductVisibilityHooks::isActiveForCurrentUser()`/
`isVisibleForCurrentUsersGradeLevel()` kodu (bölüm 187'nin ürünü) satır
satır okunup gerçek bir WordPress+MariaDB+WooCommerce kurulumunda uçtan
uca test edildi: bir şube, bir veli, o şubede/sınıfta bir öğrenci ve o
sınıfa kısıtlı bir ürün oluşturulup veli girişiyle mağaza sayfası
çekildiğinde ürün GÖRÜNÜYORDU - kod tarafında bir hata YOK. Asıl neden:
WooCommerce'in "Coming Soon" (Yakında Açılıyor) modu `woocommerce_coming_soon`
seçeneği `yes` olarak (WooCommerce'in kendi kurulum sihirbazının varsayılanı)
kalmıştı - bu, sınıf/şube filtresinden BAĞIMSIZ olarak TÜM ürünleri HER
ziyaretçiden (veli dahil) gizleyip yerine "Mağazamız yakında açılıyor"
placeholder'ı gösteriyor; sınıf eşleşse de eşleşmese de aynı sonucu
üretiyor, bu yüzden "sınıf filtresi bozuk" gibi görünüyordu. Bu platformun
hiçbir zaman gerçek bir "mağaza lansmanı" anı yok (veli hesapları zaten
okul tarafından önceden oluşturuluyor) - `inc/woocommerce.php`'ye
`scp_ensure_shop_page_exists()`/`scp_ensure_cart_page_exists()` ile aynı
desende (`admin_init`'te kendi kendini onaran) bir
`scp_ensure_store_not_coming_soon()` eklendi; her wp-admin yüklemesinde
seçenek `yes` ise `no`'ya çekiliyor. **Doğrulama**: seçenek elle `yes`'e
geri alınıp fonksiyon doğrudan çağrıldı, `no`'ya döndüğü gözlemlendi.

**2. "Ürün kargoya verilme butonuna tıklandığında e-posta gitmiyor, onun
yerine teslim edilince 'kargoya verildi' maili gidiyor"** -
`AdminOrdersRestController::ship()`/`deliver()`'ın olay isimleri
(`commerce.order_shipped`/`commerce.order_delivered`) ve
`OrderStatusNotificationListener`'ın bu olaylara karşılık gelen
konu/metin çiftleri satır satır izlendi: kodda bir TAKAS yok, her olay
kendi doğru metnini üretiyor (bu, 2026-08-05 tarihli, oldukça yakın bir
düzeltmeden beri böyle). Bulunan gerçek, tekrarlanabilir hata: kargoya
verme butonu (`admin-orders-panel.js`'deki `shipOrder()`), tıklanınca
kargo takip numarası için bir `window.prompt()` açıyordu; takip numarası
olmayan biri (ki bu alan zaten "(isteğe bağlı)" diye işaretli) doğal
olarak İptal'e bassa, kod bunu "işlemi tamamen iptal et" olarak
yorumluyor, hiçbir API çağrısı yapmadan sessizce çıkıyordu - "butona
bastım, hiçbir şey olmadı" hissi tam olarak buradan geliyor.
`trackingNumber === null` (İptal) artık boş string ile AYNI şekilde
("takip numarası yok, yine de kargoya ver") ele alınıyor; sunucu
tarafında zaten opsiyonel olan bu alanın davranışı böylece istemcide de
tutarlı hale geldi.

**3. "Velinin siparişlerim bölümünde ürünü iade et diye bir özellik yok"**
- gerçekten eksikti; `AdminOrdersRestController::refund()`'ün
personel-tetikli akışının yanına, veliye kendi tamamlanmış siparişini
KENDİSİ iade edebileceği bir self-servis uç nokta eklendi:
`OrdersRestController::returnOrder()` (`POST
/commerce/orders/mine/{id}/return`), sahiplik kontrolü (sipariş
`customer_id`'si oturum sahibiyle eşleşmiyorsa 404, varlığı sızdırmadan),
`OrderPresenter::canReturn()`'ün merkezi kuralına göre 14 gün penceresi
ve iade edilecek tutar kalıp kalmadığı kontrolü, ardından
`AdminOrdersRestController::refund()` ile TAMAMEN AYNI `wc_create_refund()`
çağrısı (gateway'e gerçek bir iade talebi göndermiyor - bu platformun her
ödeme yöntemi zaten WooCommerce dışında elle yürütülüyor, bkz. `refund()`'ün
kendi docblock'u). Bu tasarımın kazandırdığı: WooCommerce'in native
`woocommerce_order_refunded` hook'u zaten hem hakediş ters kaydını hem de
velinin "iade yapıldı" e-postasını `OrderPersistenceHooks`/Notifications
üzerinden OTOMATİK tetikliyor - hangi controller'ın `wc_create_refund()`'ü
çağırdığından bağımsız - bu yüzden self-servis iade, admin iadesiyle
BİREBİR aynı defter tutma davranışını sıfır tekrar kodla elde ediyor.
14 günlük pencere `OrderPresenter::RETURN_WINDOW_DAYS` tek bir sabitte
tanımlı ve hem `can_return` bayrağını (butonun etkin/pasif durumu) hem de
`returnOrder()`'ın sunucu tarafı reddini besliyor - ikisi asla
birbirinden sapamaz. Tema tarafında `orders-panel.js`'e her tamamlanmış
siparişte bir "İade Et" düğmesi eklendi; `can_return === false` iken
düğme pasif ama GÖRÜNÜR kalıyor (neden pasif olduğunu açıklayan bir
`title` tooltip'iyle), sessizce kaybolmuyor.

**Doğrulama**: `php -l`/`node --check` (değişen tüm dosyalar) temiz,
`seviye-commerce`'de PHPUnit 23/23. Üçü de gerçek bir
WordPress+MariaDB+WooCommerce kurulumunda uçtan uca doğrulandı: (1) için
mağaza sayfası gerçek bir HTTP isteğiyle veli oturumuyla çekilip ürünün
göründüğü gözlemlendi; (3) için `rest_do_request()` ile dört senaryo
test edildi - kendi tamamlanmış siparişini iade etme (200, durum
`refunded`), aynı siparişi ikinci kez iade etmeye çalışma (422, iade
edilecek tutar kalmadı), 14 günden eski bir sipariş (422, pencere
dolmuş) ve BAŞKA bir velinin siparişi (404, sahiplik sızdırılmıyor) -
dördü de beklenen sonucu üretti.

### 82. "Sipariş alındı" (order-received) sayfası: görsel gözden geçirme

Kullanıcı "Sipariş alındı bölümü daha güzel olsun" dedi - bu sayfa hiç
template override edilmemişti (bkz. `inc/woocommerce.php`'nin kendi
docblock'u, "no template overrides") ve WooCommerce'in kendi
`checkout/thankyou.php`'sinin ürettiği ham HTML'e bugüne kadar HİÇBİR
CSS uygulanmamıştı - `.woocommerce-order`/`.woocommerce-order-overview`/
`.woocommerce-table--order-details`/`.woocommerce-customer-details`
sınıflarının hiçbiri `woocommerce.css`'te yoktu, bu yüzden tarayıcının
çıplak varsayılan stiliyle (siyah metin, süssüz madde işaretli liste,
kenarlıksız tablo) render oluyordu.

`woocommerce.css`'e, dosyanın kendi kuralına uygun şekilde (template
override yok, yalnızca WooCommerce'in stabil sınıf adları hedefleniyor)
yeni bir bölüm eklendi: "Teşekkür ederiz..." metni yeşil, işaretli bir
`.scp-card`-tarzı kutuya alındı; sipariş no/tarih/e-posta/toplam/ödeme
yöntemi artık düz bir liste değil, platformun her yerinde kullandığı kart
görünümünde bir özet şeridi; sipariş detay tablosu `.scp-table`'ın aynı
görsel dilini (gri başlık satırı, hücre kenarlıkları, toplam satırı
vurgusu) kullanıyor; fatura/gönderim adresleri iki ayrı kart olarak yan
yana diziliyor.

Özet şeridi ilk denemede CSS Grid (`auto-fit, minmax(160px,1fr)`) ile
yazılmıştı - 5 öğeli (sipariş no/tarih/e-posta/toplam/ödeme yöntemi) bir
listede bazı tarayıcı genişliklerinde son sütunun daralıp "Toplam"
değerinin bir önceki hücrenin üstüne bindiği (metin taşması) gerçek bir
görsel hata üretti; bu, gerçek bir tarayıcıda (Playwright/Chromium,
gerçek bir WordPress kurulumuna karşı) ekran görüntüsü alınarak
YAKALANDI. `flex-wrap` tabanlı bir düzene (`flex: 1 1 160px` her öğede)
geçilerek düzeltildi - grid'in `auto-fit` sütun sayısı hesaplamasındaki
köşe durumlarına hiç girmiyor.

**Doğrulama**: değişiklik `/var/www/seviyestore` test kurulumuna
dağıtılıp gerçek bir sipariş (`WC_Order`, tamamlanmış, fatura/gönderim
adresli) oluşturuldu, veli oturumuyla `checkout/order-received/{id}/`
sayfası hem masaüstü (1000px) hem mobil (390px) genişlikte Playwright ile
ekran görüntüsü alınarak doğrulandı - kart/tablo/adres düzeni her iki
genişlikte de taşmadan, üst üste binmeden render oluyor.

### 83. Site geneli mobil uyumluluk denetimi

Kullanıcı "Tüm website yapısının tamamı mobil uyumlu olsun. Tüm menüler
yapıların tamamı" dedi - bu, tekil bir sayfa değil bütün siteyi kapsayan
bir denetim isteği. Sol menü (bölüm 183, hover flyout + dokunma
fallback'i), header, komut paleti (Cmd+K), mini sepet/hızlı önizleme/
toast/modal bileşenleri, ~26 panelin TAMAMINDAKİ tablolar (`initResponsiveTables()`'ın
`MutationObserver`'ı DOM'da beliren her `.scp-table`'ı otomatik
etiketliyor - panel bazında ayrı bir "mobil kart görünümü" entegrasyonu
hiç gerekmiyormuş), formlar, auth/kurulum sayfaları tek tek incelendi
(kod okuması + gerçek bir WordPress kurulumunda Playwright ile ekran
görüntüsü). Sonuç: önceki turların ("responsive/mobil gözden geçirme",
"duyarlı 3-katman", "Mobil kart görünümlü tablolar") kapsamı gerçekten
genişti - yalnızca iki gerçek eksik bulundu:

1. **Bildirim zili paneli** (`.scp-notif-bell__panel`, `theme.css:444`) -
   sabit `360px` genişlik, viewport'un sağ kenarına değil zil
   düğmesinin KENDİ konumuna göre (`right:0`, zil elemanına göre)
   konumlanıyordu; zil header'ın en sağındaki eleman DEĞİL (tema
   anahtarı/kullanıcı adı/çıkış onu takip ediyor), bu yüzden dar bir
   ekranda panel sol kenardan taşabiliyordu. `max-width:640px`'te
   `position:fixed; left/right:16px` ile viewport'a sabitlenen bir
   şeride çevrildi - artık zilin konumundan bağımsız, her zaman ekrana
   sığıyor.
2. **Toplu işlem araç çubuğu** (`.scp-bulk-actions`, `panel.css:176`,
   "Admin sipariş listesinde toplu işlem" bölümünün, mobil pasajından
   SONRA eklenmiş bir bileşeni) - kardeş bileşenlerinin (`.scp-status-tabs`
   vb.) hepsinde olan `flex-wrap: wrap` bu birinde unutulmuştu; sayım
   metni + "Seçilenleri Teslim Edildi İşaretle" (uzun buton etiketi) +
   "Seçimi Temizle" üç öğesi dar bir ekranda taşıyordu. `flex-wrap`
   eklendi, artık öğeler kart içinde alt satıra kayıyor.

**Doğrulama**: her iki düzeltme gerçek bir WordPress kurulumuna
dağıtılıp Playwright ile 390px genişlikte ekran görüntüsü alınarak
doğrulandı (bildirim paneli: gerçek bell tıklaması ile; toplu işlem
çubuğu: gerçek veri/RBAC bağımlılığı olmadan izole bir HTML+CSS
doğrulamasıyla, aynı sınıf adları/gerçek buton metinleriyle). Sol menü de
ayrıca gerçek bir panelde (Genel Merkez oturumuyla /admin/) dokunmatik
alt menü açma davranışı doğrulanarak kontrol edildi - zaten sağlam
çıktı, değişiklik gerekmedi.

### 84. Seviye Depo Faz 4: "Tek bir depo vardır" kararının tersine çevrilmesi - Genel Merkez + şube bazlı depolar

Bölüm 39'da kullanıcının kendisi onayladığı "Tek bir depo vardır" kararı
bu turda AÇIKÇA tersine çevrildi: "şimdi bir tane depo olacak... genel
merkezin deposu, diğer ürün ekleyen şubeler ise kendi deposu olsa bile
genel merkez deposu devam edecek, şube kendi ürününü eklemiş ise şubenin
kendi deposundan görünecek, genel merkez deposundan görünmeyecek. Sipariş
takibini genel merkez ürünleri genel merkez deposundan, şube ürünlerini
şube deposundan takip edilecek." Sonuç iki katmanlı bir model: Genel
Merkez'in KENDİ deposu hep var olmaya devam ediyor; bir şube kendi ürününü
eklediyse (bkz. bölüm 33.5/34'ün `_scp_owner_branch_id` mekanizması), o
ürüne dair her şey (satın alma siparişi, stok sayımı, düşük stok önerisi,
stok hareketi) YALNIZCA o şubenin kendi deposunda görünüyor - Genel
Merkez'in deposunda hiç görünmüyor, ve o şube başka bir şubenin/Genel
Merkez'in deposunu hiç göremiyor.

**Tek doğruluk kaynağı yeniden kullanıldı, icat edilmedi.** "Hangi
ürün hangi şubeye ait" sorusunun cevabı zaten Commerce'te vardı
(`Commerce\Support\ProductOwnership`, `_scp_owner_branch_id` post-meta -
meta yoksa Genel Merkez ürünü, "opt-out" deseni). Depo'ya Commerce'e sert
bir composer bağımlılığı eklemek yerine, Pricing'in `violatesBasePriceFloor()`'da
zaten kullandığı GEVŞEK filtre köprüsü (`Commerce\Http\ProductOwnershipBridge`'in
yayınladığı `scp_commerce_product_owner_branch_id` filtresi) aynen tekrar
kullanıldı - Depo, WC aktif değilken/Commerce aktif değilken de çalışmaya
devam ediyor (filtre no-op döner, `null` = Genel Merkez).

**Şema: dört tabloya da `branch_id BIGINT UNSIGNED NULL` eklendi**
(`scp_purchase_orders`, `scp_stock_counts`, `scp_purchase_suggestions`,
`scp_stock_movements`) - `NULL` = Genel Merkez deposu, bir değer = o
şubenin deposu, ProductOwnership'in aynı opt-out deseni. Yeni migration
dosyası YOK: bu kod tabanının migration'ları dbDelta-idempotent ve her
plugin yüklemesinde koşulsuz yeniden çalışıyor (`MigrationRunner::run()`,
versiyon kontrolü yalnızca audit log kaydını etkiliyor, çalıştırmayı
değil) - var olan `CREATE TABLE` migration'ları YERİNDE düzenlendi, dbDelta
yeni sütunu bir sonraki yüklemede kendisi ekliyor. Değer her zaman YAZMA
anında donduruluyor (bir ürünün sahibi sonradan değişse bile geçmiş
kayıtlar o anki depoyu yansıtmaya devam ediyor - `scp_order_line_items`'ın
kendi `branch_id`'siyle aynı ilke).

**`int|false|null` üç durumlu sentinel.** Her repository'nin
`all()`/`list()` metodunda ve her REST controller'ın
`resolveBranchScope()`'unda aynı kodlama: `false` (varsayılan) = filtre
yok/her depo, `null` = yalnızca Genel Merkez deposu, `int` = yalnızca o
şubenin deposu. Sıradan `?int` yetmiyor çünkü burada Genel Merkez'in
KENDİSİ de ayrı, seçilebilir bir kapsam - "filtre yok" ile "yalnızca Genel
Merkez" iki farklı boş durum. REST tarafında aynı üç durum `branch_id`
istek parametresinde kodlanıyor: yok/boş = filtre yok, `'hq'` = Genel
Merkez, bir sayı = o şube.

**RBAC: platform-wide + own-branch iki katman, Products/Orders'la aynı
desen.** `WarehouseCapability`'ye dört yeni own-branch case eklendi
(`MANAGE_OWN_BRANCH_PURCHASE_ORDERS`, `VIEW_OWN_BRANCH_STOCK_MOVEMENTS`,
`MANAGE_OWN_BRANCH_STOCK_COUNTS`, `MANAGE_OWN_BRANCH_PURCHASE_SUGGESTIONS`),
yalnızca Şube Müdürü'ne veriliyor - Genel Merkez/Bölge Müdürü/Depo rolü
platform-wide capability'leriyle her depoyu görmeye devam ediyor. Mal
kabul için AYRI bir own-branch capability yok - kasıtlı bir sadeleştirme,
`MANAGE_OWN_BRANCH_PURCHASE_ORDERS` zaten mal kabulü de kapsıyor
(`canReceiveStock()`, `RECEIVE_STOCK` VEYA bu capability'yi kontrol
ediyor). Tedarikçi listesi bilinçli olarak İKİ KATMANLI DEĞİL - platform
genelinde tek bir tedarikçi dizini var (bir şube kendi tedarikçisini
eklemiyor, yalnızca var olan tedarikçilere sipariş açıyor); yazma uçları
hâlâ yalnızca `scp_manage_suppliers`, ama GET own-branch satın alma/öneri
capability'lerine de salt-okunur açık - Şube Müdürü'nün kendi siparişi
için tedarikçi seçebilmesi gerekiyor.

**"Karma depo" siparişi engellendi.** Bir satın alma siparişi fiziksel
olarak tek bir depoya teslim alınır, iki depo arasında bölünemez.
`PurchaseOrdersRestController::store()`, her kalemin sahip şubesini filtre
köprüsüyle çözüp TÜM kalemlerin aynı depoya (hepsi Genel Merkez ya da
hepsi TEK bir şube) ait olduğunu doğruluyor, uyuşmazlıkta 422 dönüyor.
Own-branch bir kullanıcının başka bir şubenin ürünü için sipariş açmaya
çalışması da aynı yoldan (403) engelleniyor.

**Nesne seviyesi erişim: "varlığı sızdırma" 404 deseni yeniden
kullanıldı.** Own-branch bir kullanıcı başka bir depoya ait bir siparişe/
sayıma/öneriye ID ile erişmeye çalışırsa 403 değil 404 dönüyor -
`AdminOrdersRestController`'ın branch-scoped sipariş erişiminde zaten
kurulu olan aynı "başka bir kaydın var olup olmadığını bile sızdırma"
ilkesi.

**Düşük stok → satın alma önerisi ve dönüşüm zinciri.**
`LowStockPurchaseSuggestionListener`, önerinin `branch_id`'sini
`event`'in HER ZAMAN üst ürünü taşıyan `product_id` alanından (varyasyon
ID'sinden DEĞİL - sahiplik meta'sı yalnızca üst üründe tutuluyor) filtre
köprüsüyle çözüp donduruyor;
`PurchaseSuggestionsRestController::convert()` bu değeri aynen yeni
açılan satın alma siparişine taşıyor - bir şubenin önerisi asla Genel
Merkez'in siparişine dönüşmüyor.

**Depo Raporları'na şube boyutu eklendi.** `WarehouseReportsRestController`,
artık `ReportsRestController::canViewReports()`/`effectiveBranchId()`
ile aynı desen: HQ herhangi bir depoyu (ya da hiçbirini seçmezse hepsini)
raporlayabilir, Şube Müdürü her zaman kendi şubesine kilitleniyor - bölüm
39/40'taki "Depo'nun şube kavramı yok, raporlanacak boyut yok" gerekçesi
artık geçersiz. `WarehouseReportBuilder`, tedarikçi bazında değil
(tedarikçi/şube) ÇİFTİ bazında gruplanıyor - bir tedarikçi hem Genel
Merkez'den hem bir şubeden sipariş almış olabilir, ikisinin toplamını
karıştırmak yanıltıcı olurdu. `PurchaseOrderReportFilter`/`Record`
`branchId` alanı kazandı (aynı üç durumlu sentinel), CSV/XLSX
exporter'lara "Depo" sütunu eklendi.

**Tema paneli.** Depo panelinde platform-wide kullanıcıya bir depo seçici
(Tüm depolar/Genel Merkez/bir şube) gösteriliyor, own-branch kullanıcı
hiç görmüyor - REST tarafı zaten onu kilitliyor, bu yalnızca HQ'nun kendi
görünümünü daraltması için. Tedarikçi CRUD (Yeni/Düzenle) yalnızca
`canManageSuppliers`'a; own-branch kullanıcı listeyi salt-okunur görüyor.
Raporlar panelindeki "Depo Raporları" alt bölümü artık `scp_view_reports`
VEYA `scp_view_own_reports`'a açık (öncekinden farklı olarak Şube
Müdürü'ne de).

**Yapılamayan.** Bu oturumda (Docker/wp-env erişimi olmayan bir sandbox,
bkz. "Test stratejisi") gerçek bir WordPress+MariaDB kurulumunda uçtan
uca doğrulama YAPILAMADI - doğrulama `php -l`, tüm birim test paketleri
(seviye-depo: 58/58, seviye-reports: 24/24, ayrıca tüm diğer 7 eklenti),
`phpcs` (0 hata) ve `composer validate`/`composer update` (yeni
`seviye/branches` bağımlılığının lock dosyasına doğru kilitlendiğinin
teyidi) ile sınırlı kaldı.

### 85. Şubeler arası stok transferi (Depo Faz 4'ün doğal devamı)

Kullanıcıya "Depo Faz 4 bittiğine göre sırada ne olsun?" diye soruldu,
"Şubeler arası stok transferi" seçildi - bir şube fazla stoğunu başka bir
depoya (Genel Merkez'e ya da başka bir şubeye) aktarabilsin.

**WooCommerce'in tek stok alanı, iki farklı ürün kaydı gerektiriyor.**
Depo başına ayrı bir stok havuzu yok - her SKU'nun WC'de tek bir
`stock_quantity`'si var (bkz. "Kural"). Bu yüzden bir transfer, aynı
ürünün iki FARKLI kaydı arasında çalışıyor: `from_product_id` (kaynak
depodaki kayıt) ve `to_product_id` (hedef depodaki kayıt - genelde hedef
şubenin kendi kataloğuna daha önce eklediği "aynı ürün"ün kendi kaydı).
`from_branch_id`/`to_branch_id` kullanıcıdan İSTENMİYOR - her iki ürünün
kendi sahiplik meta'sından (`scp_commerce_product_owner_branch_id` filtre
köprüsü) yazma anında çözülüp donduruluyor, satın alma siparişinin
`branch_id`'siyle aynı desen.

**Yeni tablo: `scp_stock_transfers`** (`from_product_id`, `to_product_id`,
`quantity`, `from_branch_id`/`to_branch_id` nullable, `status`
pending/completed/cancelled, `note`, `requested_by`/`completed_by`).
Ayrı bir `completed_at` sütunu yok - `scp_purchase_orders`'ın "durum
geçişine özel zaman damgası yok" ilkesi burada da geçerli.

**PENDING -> COMPLETED/CANCELLED, PurchaseOrder'ın send()/receive()
ayrımıyla aynı gerekçe.** `store()` hiçbir stok değiştirmez - fiziksel
mal henüz yola çıkmamıştır, yalnızca bir kayıt açılır. `complete()`
(hedef depo tarafının "teslim aldım" onayı) hem kaynaktan
`wc_update_product_stock(..., 'decrease')` ile düşürür hem hedefe
`'increase'` ile ekler, VE `StockMovementRepositoryInterface`'e biri
`TRANSFER_OUT` biri `TRANSFER_IN` olmak üzere iki defter satırı yazar -
her iki depo da kendi tarafında "neden değiştiğini" görebilsin. Kaynakta
yeterli stok yoksa (kontrol `complete()` anında yapılır, `store()`
anında değil - miktar iki adım arasında değişmiş olabilir) 422 döner.

**RBAC: üç farklı yetki sınırı, üç farklı eylem.** Platform-wide
`MANAGE_STOCK_TRANSFERS` her şeyi yapabilir. Own-branch
`MANAGE_OWN_BRANCH_STOCK_TRANSFERS`'a sahip bir Şube Müdürü için üç ayrı
kural var: `store()`'da yalnızca KENDİ şubesi kaynak olacak şekilde
transfer açabilir (hedef herhangi bir depo olabilir - vermek her zaman
serbest); `complete()`'te yalnızca KENDİ şubesi HEDEF olduğunda
tamamlayabilir (başkasının deposuna izinsiz stok itilmesin diye -
`canCompleteTransfer()`); görüntüleme/iptal etmede ise HER İKİ taraf da
"kendi" transferi sayılır (`canAccessTransfer()`) - bir depo hem giden
hem gelen transferle ilgilenir.
`StockTransferRepositoryInterface::all()`'ın `branch_id` filtresi de aynı
"her iki taraf" mantığını SQL'e taşıyor (`from_branch_id = ? OR
to_branch_id = ?`) - Depo'nun diğer tüm `all()` metotlarının aksine
(onlarda tek bir taraf var).

**Tema paneli.** Depo panelinde yeni "Depo Transferleri" alt bölümü:
liste (kaynak/hedef ürün+depo, miktar, durum), "Yeni Transfer" formu
(kaynak/hedef ürün ID + miktar + not), bekleyen her satırda Tamamla/İptal
Et düğmeleri. Var olan depo seçici (branchQueryString()) buraya da
uygulanıyor - HQ bir depo seçtiğinde transfer listesi de filtreleniyor.

**Doğrulama.** 9 yeni birim testi (`WpdbStockTransferRepositoryTest`) +
tüm mevcut 58 testin yeşil kalması (toplam 67/67), `phpcs` 0 hata. Gerçek
WordPress+MariaDB üzerinde uçtan uca test bu sandboxta (Docker erişimi
yok) yine yapılamadı.

### 86. Beden Rehberi (yaş/boy → beden tablosu)

Kullanıcıya arka arkaya üç kez "daha ne yapılabilir" soruldu, üçünde de
somut bir seçenek yerine "başka" cevabı geldi - dördüncü bir seçenek turu
sormak yerine kendi önerim olan Beden Rehberi'ne (kullanıcı deneyimini
yükseltecek, en önceki turda kullanıcının kendi belirttiği yön) geçildi.
Amaç: velinin ürün sayfasında çocuğunun yaşına/boyuna göre doğru bedeni
seçmesine yardımcı olup yanlış beden alımı/iade oranını azaltmak.

**Tek bir `SettingsRepositoryInterface` anahtarında JSON, yeni tablo
yok.** İçerik küçük, ilişkisel bir ihtiyacı yok, mağaza geneli TEK bir
liste (ürün başına değil) - Security'nin `/admin` IP allowlist'i için
zaten kullandığı "yapı çağıranın kendi encode/decode'una ait" ilkesiyle
aynı (bkz. `IpAllowlist`'in kendi docblock'u), farkı satır başına birden
çok alan olduğu için newline-separated yerine JSON kullanılması.
`Support\SizeGuideRow` (label, age_min/max, height_min/max_cm - hepsi
opsiyonel boy/yaş dışında) ve `Support\SizeGuide` (parse/serialize,
`array_is_list()` ile geçerli bir JSON DİZİSİ mi yoksa nesne mi ayrımı)
bu encode/decode'u tek bir yerde topluyor - hem yazan REST controller hem
okuyan tema hook'u aynı şekle sürüklenemez.

**RBAC + REST: `TaxRatesRestController`/`SecuritySettingsRestController`
ile aynı kalıp.** `ProductCapability::MANAGE_SIZE_GUIDE` -
`MANAGE_TAX_RATES` ile aynı "mağaza geneli ayar, yalnızca HQ, Şube
Müdürü katmanı yok" şekli. `Http\SizeGuideRestController` -
`SecuritySettingsRestController`'ın GET/PUT tek-ayar kalıbı: GET tüm
listeyi döner, PUT TÜM listeyi DEĞİŞTİRİR (satırların vergi oranlarının
aksine doğal bir id/slug'ı yok, bu yüzden tek tek POST/PUT/DELETE değil).

**Tema, DI container'a bağımlı olmadan filtre köprüsüyle okuyor.**
`CommerceModule::boot()` `scp_commerce_size_guide_rows` filtresini
kaydediyor (Depo'nun `scp_depo_supplier_id_for_user`'ı ve
`ProductOwnershipBridge`'in `scp_commerce_product_owner_branch_id`'siyle
AYNI gevşek köprü ilkesi). Tekil ürün sayfasında
`scp_render_size_guide_trigger()` (öncelik 5, öğrenci seçicisinden -
öncelik 10 - önce) bu filtreyi çağırıp bir "Beden Rehberi" düğmesi + Hızlı
Bakış'la (bölüm 214) AYNI `.scp-quick-view__*`/`data-scp-quick-view-*`
markup kalıbını taşıyan gizli bir `<template>` basıyor - REST çağrısı YOK,
`initQuickView()` zaten var olan genel delege tıklama dinleyicisi hiçbir
yeni JS gerektirmeden çalışıyor. İçerik boşsa (HQ henüz hiç satır
girmemişse) ya da üründe `pa_beden` global özniteliği yoksa (defter, kalem
gibi bedensiz ürünlerde rehber anlamsız) hiçbir şey basılmıyor.

**Tema paneli.** Yeni `/admin/beden-rehberi` sayfası
(`scp_manage_size_guide`, kataloğ grubu). Vergi Oranları'ndan farklı
olarak satırların id'si olmadığından liste tamamen istemci tarafında
eklenir/silinir ("Yeni Satır"/her satırda "Kaldır"), "Kaydet" tüm tabloyu
tek bir `PUT commerce/size-guide` ile gönderir.

**Doğrulama.** `SizeGuideTest`'in 5 testi dahil seviye-commerce'in 28
testi yeşil, `phpcs` 0 hata (yalnızca bu diff'ten önce de var olan
line-length/nonce-verification uyarıları kaldı). Gerçek
WordPress+MariaDB üzerinde uçtan uca test bu sandboxta (Docker erişimi
yok) yine yapılamadı.

### 87. Yıllık harcama özeti (PDF)

"Daha ne yapılabilir" turunda kullanıcı önce iki kez daha fazla öneri
istedi (üç ayrı 4'lü seçenek seti sunuldu), üçüncü sette "Yıllık harcama
özeti (PDF)"'yi seçti - velinin bir eğitim yılı boyunca yaptığı tüm
harcamaların özetini tek bir belge olarak indirebilmesi, okul harcaması
belgesi ihtiyacını karşılıyor.

**Yeni REST endpoint YOK - `commerce/orders/mine` zaten yeterli.**
`OrdersRestController::mine()` bir velinin TÜM sipariş geçmişini tek
seferde döndürüyor (`limit => -1`, bkz. o dosyanın kendi docblock'u);
`/siparislerim` sayfası bunu zaten belleğe yüklüyor. Yıl bazlı özet bu
yüzden tamamen istemci tarafında hesaplanıyor
(`orders-panel.js`'in `buildSpendingSummary()`'si) - `order.date`'in
(`Y-m-d H:i`) ilk 4 karakterinden yıl çıkarıp filtreliyor, `İptal Edildi`
durumundaki siparişleri hariç tutuyor (gerçekleşmemiş bir harcama
"yıllık harcama"nın parçası değil), kalanları ara toplam/KDV/genel toplam
+ öğrenci bazında kırılım olarak topluyor.

**"PDF" = tarayıcının kendi yazdırma diyalogu, yeni bir kütüphane
DEĞİL.** Bu platformdaki her "yazdırılabilir" özellik (bölüm 237,
`scpPrintOrder`) aynı ilkeyi izliyor: sunucu tarafında Dompdf/TCPDF gibi
bir PDF kütüphanesi eklemek yerine, temiz bir DOM parçası
`#scp-print-order-root`'a yazılıp `window.print()` çağrılıyor - kullanıcı
yazdırma diyaloğunda "PDF olarak kaydet"i seçtiğinde gerçek bir PDF
dosyası elde ediyor. `window.scpPrintSpendingSummary()` (`scp-ui-kit.js`)
`scpPrintOrder()`'ın AYNI kalıbını (aynı kök element, aynı
`body.scp-printing-order` CSS sınıfı - bkz. panel.css) tek bir siparişin
fişi yerine bir yılın toplu özetine uyguluyor; sıfır yeni bağımlılık.

**Tema.** `/siparislerim` sayfasındaki kart başlığına yeni bir araç
çubuğu (yıl seçici + "Özeti İndir (Yazdır)" düğmesi) eklendi - markup'ta
`hidden`, `orders-panel.js` siparişler yüklendikten sonra (en az bir
sipariş varsa) siparişlerin tarihinden çıkardığı yılları dolduruyor ve
araç çubuğunu gösteriyor; hiç sipariş yoksa gizli kalıyor.

**Doğrulama.** `php -l`/`phpcs` (0 yeni hata, yalnızca bu diff'ten önce
de var olan iki line-length uyarısı) ve `node --check` ile sözdizimi
doğrulandı; toplama mantığı (iptal hariç tutma, yıl filtresi, öğrenci
kırılımı) küçük bir Node betiğiyle izole test edildi. Bu tur yalnızca
tema dosyalarını değiştirdiği için hiçbir eklenti PHPUnit paketi
etkilenmedi.

## Test stratejisi

- **Birim testleri** (`plugin/*/tests/Unit`): WordPress'e bağımlı olmayan iş
  mantığı (Container, EventBus, RateLimiter, MigrationRunner, RoleDefinitions)
  saf PHPUnit ile test edilir; WP fonksiyonlarına ihtiyaç duyan adapter'lar
  (`WpdbConnection`, `WpRoleGateway`, `TransientCache`) sahte (fake) port
  implementasyonlarıyla dolaylı olarak doğrulanır.
- **Entegrasyon testleri** (`tests/integration`, bölüm 44): `wp-env` +
  `WP_UnitTestCase` tabanlı, gerçek WordPress/MySQL üzerinde çalışan
  testler - iskelet kuruldu, ama bu oturumda (Docker/wordpress.org erişimi
  olmayan bir sandbox) HENÜZ ÇALIŞTIRILAMADI/doğrulanamadı; bkz.
  `tests/README.md`.

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
