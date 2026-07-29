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

### 12. Panel entegrasyonu (Students + Parents + Branches REST'ine bağlanma)

`templates/zone.php` (öğrenci yönetimi + şube yönetimi, `/admin` + `/sube`)
ve `templates/parent-dashboard.php` (Veli ana sayfası, `/`) sırasıyla
`assets/js/students-panel.js`, `assets/js/branches-panel.js` ve
`assets/js/parent-dashboard.js` ile ilgili REST uçlarını çağırır.

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
