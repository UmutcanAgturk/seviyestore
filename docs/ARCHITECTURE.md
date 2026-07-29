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
