# Yol Haritası

Spesifikasyondaki 11 plugin ve durumları. Her modül Core'a bağımlıdır;
ayrıca gerçek bir alan-modeli ilişkisi olduğunda başka bir modülün açıkça
yayınladığı `Contracts` arayüzüne de bağımlı olabilir (bkz.
`docs/ARCHITECTURE.md`, "Kural") — internal sınıflarına asla.

| # | Plugin | Sorumluluk | Durum |
|---|---|---|---|
| 1 | Seviye Core | DI container, event bus, RBAC, migration runner, audit log, ayarlar (key/value), REST altyapısı | ✅ **Kuruldu** |
| 2 | Seviye Students | Öğrenci entity (şube/eğitim yılı/sınıf), veli (WP kullanıcı) ile çoktan-çoğa ilişki, REST | ✅ **Kuruldu** (bu milestone) |
| 3 | Seviye Parents | Veli'ye özgü profil alanları (telefon, bildirim tercihi, KVKK onayı), REST | ✅ **Kuruldu** (bu milestone) |
| 4 | Seviye Branches | Şube entity (IBAN, komisyon, telefon, adres), Yetkililer (personel-şube ataması), Contracts, REST, native wp-admin "Seviye Şubeler" sayfası | ✅ **Kuruldu** (logo yükleme henüz yok), bkz. `docs/ARCHITECTURE.md` bölüm 21 |
| 5 | Seviye Pricing | Özel fiyatlandırma motoru (öğrenci→şube→genel→WC varsayılan önceliği; "bölge" katmanı Branches'ta resmi bir Region entity'si olmadığından bu milestone'da bilinçli olarak ayrı bir katman değil — bkz. `docs/ARCHITECTURE.md` bölüm 13), Contracts (`PriceResolverInterface`), REST | ✅ **Kuruldu** |
| 6 | Seviye Commerce | WooCommerce entegrasyonu, sipariş akışı, split payment | ✅ **Kuruldu** — sepet fiyatlandırma, tema tarafı, sipariş kalıcılığı, split payment hesaplaması ve hakediş event tetikleme (tamamlama + iade/iptal ters çevirme) kuruldu; asıl hakediş/cari kaydını tutmak Seviye Finance'ın işi (henüz kurulmadı), bkz. `docs/ARCHITECTURE.md` bölüm 14 |
| 7 | Seviye Finance | Cari, hakediş, komisyon, KDV, iade, tahsilat | 🟡 **Kısmen kuruldu** — hakediş defteri, cari bakiye REST'i, tahsilat (settlement) defteri + REST'i (`POST`/`GET /finance/hakedis/settlements/*`, RBAC) ve tema paneli kuruldu; KDV tutarı Commerce'ten uçtan uca yakalanıp ledger'a yazılıyor ve artık Seviye Reports üzerinden raporlanıyor; iade akışı planlandı, bkz. `docs/ARCHITECTURE.md` bölüm 15 |
| 8 | Seviye Reports | Excel/CSV raporlama (şube/ürün/kategori/dönem bazlı satış) | ✅ **Kuruldu** — `GET seviye/v1/reports/sales` (JSON/CSV/XLSX), Commerce'in `OrderLineItemQueryInterface` Contract'ı üzerinden; PDF raporlama planlandı, bkz. `docs/ARCHITECTURE.md` bölüm 17 |
| 9 | Seviye Notifications | SMS/e-posta/panel içi bildirimler | ✅ **Kuruldu** — `scp_notifications` günlüğü, `security.password_reset_requested` dinleyicisi, e-posta (`wp_mail()`) + SMS (NetGSM, `seviye/v1/notifications/sms-settings`) + panel-içi (`seviye/v1/notifications/mine/*`, tema bildirim çanı) kanalları; SMS bugün yalnızca telefon numarası kayıtlı veli hesapları için çalışır (Parents'ın yayınladığı `ParentContactLookupInterface`), bkz. `docs/ARCHITECTURE.md` bölüm 18 |
| 10 | Seviye API | `seviye/v1` REST uç noktaları (ERP/CRM/muhasebe/mobil entegrasyonu) | ✅ **Kuruldu** — API anahtarı tabanlı kimlik doğrulama (`rest_authentication_errors`, `Authorization: Bearer`), `seviye/v1/api-keys` (yalnızca Genel Merkez, oluştur/listele/iptal et); yeni iş mantığı uç noktası eklemez, mevcut her modülün `seviye/v1/*` uçlarını cookie+nonce dışında da erişilebilir kılar, bkz. `docs/ARCHITECTURE.md` bölüm 19 |
| 11 | Seviye Security | TC Kimlik No auth, rate limiting, şifre/ilk-kurulum token'ları, rol→bölge politikası, 2FA (TOTP), IP kısıtlaması, native wp-admin kullanıcı yetkilendirme | ✅ **Kuruldu**, bkz. `docs/ARCHITECTURE.md` bölüm 16 ve 20 |

## Tema ve giriş akışı

| Bileşen | Durum |
|---|---|
| Backend: TC Kimlik No doğrulama, `AuthService` (rate-limitli giriş), şifre/ilk-şifre token sistemi, `seviye/v1/auth/*` REST uçları | **Kuruldu** (`Seviye Security`) |
| Frontend: giriş ekranı (HTML/JS), içerik kilitleme, rol bazlı `/`, `/sube`, `/admin` yönlendirmesi | **Kuruldu** (`Seviye Storefront` teması) |
| `/sube` ve `/admin` panelleri: öğrenci listesi/formu, veli bağlama | **Kuruldu** — `seviye/v1/students`'a bağlı, gerçek CRUD ekranı |
| `/admin`'de şube yönetimi (liste/oluştur/düzenle) | **Kuruldu** — `seviye/v1/branches`'a bağlı, gerçek CRUD ekranı |
| `/` (Veli ana sayfası): kendi öğrencileri (salt okunur liste) + profil formu | **Kuruldu** — `seviye/v1/students/mine` ve `seviye/v1/parents/me`'ye bağlı |
| `/admin` ve `/sube`'de fiyat kuralları yönetim ekranı | **Kuruldu** — `seviye/v1/pricing/rules`'a bağlı, gerçek CRUD ekranı |
| Sipariş/finans panel içeriği | Planlandı (Seviye Commerce/Finance'ın sorumluluğu) |
| E-posta ile token teslimi (`security.password_reset_requested` olayının dinlenmesi) | **Kuruldu** — `Seviye Notifications` |
| Sepette öğrenci seçimi + öğrenciye göre fiyat çözümü | **Kuruldu** — `Seviye Commerce` |
| WooCommerce ürün sayfası: öğrenci seçici, vitrin "hızlı sepete ekle" düğmesinin ürün sayfasına yönlendirilmesi, Veli ana sayfasından mağazaya giriş bağlantısı | **Kuruldu** — özel bir WC şablonu gerekmedi, mevcut `add_theme_support('woocommerce')` + hook'lar yeterli |
| Sipariş kalıcılığı (`scp_order_line_items`: öğrenci/şube/komisyon/fiyat anlık görüntüsü + WC durum senkronu) | **Kuruldu** — `Seviye Commerce` |
| Split payment hesaplaması + hakediş event tetikleme (tamamlama + iade/iptal ters çevirme) | **Kuruldu** — `Seviye Commerce`; asıl hakediş/cari kaydı Seviye Finance'ın sorumluluğu |
| Hakediş defteri (`scp_hakedis_entries`, event tüketimi) + cari bakiye REST'i | **Kuruldu** — `Seviye Finance` |
| `/admin` ve `/sube`'de cari bakiye görüntüleme paneli + tahsilat geçmişi/kaydı | **Kuruldu** — `Seviye Storefront` teması |
| KDV tutarının sipariş kaleminden hakediş defterine kadar yakalanması | **Kuruldu** — `Seviye Commerce` + `Seviye Finance` + `Seviye Reports` (artık raporlanıyor) |
| İade akışı | Planlandı (Seviye Finance'ın sorumluluğu) |
| Hesap Güvenliği paneli (2FA kurulum/onay/devre dışı bırakma, `/`, `/sube`, `/admin`'de ortak partial) + girişte 2FA kod adımı + `/admin`'de IP kısıtlaması ayarı | **Kuruldu** — `Seviye Security` + `Seviye Storefront` teması |
| `/admin` ve `/sube`'de Raporlar paneli (şube/ürün/kategori/dönem filtreleri, JSON görünüm + CSV/Excel indirme) | **Kuruldu** — `seviye/v1/reports/sales`'a bağlı |
| Panel-içi bildirim çanı (her bölgede, `header.php`) + `/admin`'de SMS ayarları (NetGSM) formu | **Kuruldu** — `seviye/v1/notifications/mine/*` + `seviye/v1/notifications/sms-settings`'e bağlı |
| `/admin`'de API anahtarları yönetim paneli (oluştur/listele/iptal et) | **Kuruldu** — `seviye/v1/api-keys`'e bağlı |
| wp-admin → **Seviye Kullanıcılar** üst menüsü: Seviye Kullanıcılar (liste/düzenle/sil), Seviye Yetkilendirme (rol + T.C. Kimlik No + şifre ata), Veli (rol filtreli liste), Öğrenci (salt okunur dizin) + "Kullanıcıyı Düzenle" ekranında T.C. Kimlik No alanı | **Kuruldu** — `Seviye Security`, native WordPress ekranları, `scp_manage_security_settings` yetkisiyle kapılı (Genel Merkez + gerçek WP yöneticisi) |

## Milestone sırası önerisi

1. ~~Repo iskeleti + Seviye Core~~ ✅
2. ~~Giriş akışı backend'i: Seviye Security (TC Kimlik No doğrulama, AuthService, şifre/ilk-kurulum token sistemi, REST uçları)~~ ✅
3. ~~Tema: giriş ekranı, içerik kilitleme, rol bazlı `/`, `/sube`, `/admin` yönlendirmesi~~ ✅
4. ~~Seviye Branches (şube entity, Yetkililer/personel ataması, Contracts, REST, RBAC)~~ ✅
5. ~~Seviye Students (öğrenci entity, veli çoktan-çoğa ilişkisi, Branches'ın Contracts'ını kullanan ilk modül, REST, RBAC)~~ ✅
6. ~~Seviye Parents (veli'ye özgü profil alanları, REST, RBAC — Core-only, Contracts'a ihtiyaç duymayan ilk modül)~~ ✅
7. ~~Tema: `/sube`/`/admin` öğrenci paneli + veli ana sayfası (kendi öğrencileri + profil), gerçek REST'e bağlı~~ ✅
8. ~~Tema: `/admin`'de şube yönetimi ekranı (liste/oluştur/düzenle), `seviye/v1/branches`'a bağlı~~ ✅
9. ~~Seviye Pricing (fiyat motoru: öğrenci/şube/genel kural CRUD'u + `PriceResolverInterface`, henüz sipariş yok)~~ ✅
10. ~~Tema: `/admin` ve `/sube`'de fiyat kuralları yönetim ekranı, `seviye/v1/pricing/rules`'a bağlı~~ ✅
11. ~~Seviye Commerce — `PriceResolverInterface`'i tüketen ilk modül, Veli ana sayfasına mağaza içeriğini kazandırır~~ ✅
    - ~~11a. Sepet fiyatlandırma: öğrenci seçimi doğrulaması (`StudentGuardianCheckInterface`), `woocommerce_before_calculate_totals` üzerinden fiyat çözümü, sipariş kalemi meta'sına kalıcı kayıt~~ ✅
    - ~~11b. Tema: WooCommerce ürün sayfasında öğrenci seçici, vitrin "hızlı sepete ekle"nin ürün sayfasına yönlendirilmesi, Veli ana sayfasından mağazaya giriş~~ ✅
    - ~~11c. Sipariş kalıcılığı ve durum akışı: `scp_order_line_items` (öğrenci/şube/komisyon oranı/fiyat anlık görüntüsü + `woocommerce_order_status_changed` ile senkron durum)~~ ✅
    - ~~11d. Split payment (`Support\SplitPaymentCalculator`, `scp_order_line_items`'ın sakladığı komisyon oranı anlık görüntüsüne dayalı) + hakediş event tetikleme (`commerce.order_line_item_completed`/`_reversed`, Seviye Finance'ın dinleyeceği)~~ ✅
12. Seviye Finance:
    - ~~12a. Hakediş defteri: `scp_hakedis_entries` (değişmez, yalnızca-ekleme), `commerce.order_line_item_completed`/`_reversed`'i EventBus üzerinden dinleyen `HakedisEventListener` — Commerce'in Contracts'ına değil, yalnızca Core'a bağımlı ilk modül~~ ✅
    - ~~12b. Cari bakiye REST: `GET /finance/hakedis/balance/me` + `GET /finance/hakedis/balance/{branch_id}`, RBAC (`scp_view_hakedis`: HQ tüm şubeleri; `scp_view_own_hakedis`: yalnızca Şube Müdürü + Muhasebe, kendi şubesi) — `BranchesRestController`'ın `/branches/me` desenini izler~~ ✅
    - ~~12c. Tema: `/admin` ve `/sube`'de cari bakiye görüntüleme paneli~~ ✅
    - ~~12d. Tahsilat işaretleme (`scp_hakedis_settlements` defteri, hakediş kaydının ne zaman/nasıl ödendiğini takip etme, REST, RBAC, tema paneli) + KDV takibi (Commerce'ten hakediş defterine kadar `vat_amount` yakalama - raporlama Seviye Reports'un işi)~~ ✅
13. ~~Seviye Security'nin geri kalanı: 2FA (`TwoFactor\Totp`/`Base32`/`Encryptor`,
    RFC 6238 test vektörleriyle doğrulandı; girişte iki adımlı akış;
    self-servis `seviye/v1/security/2fa/*`; tema "Hesap Güvenliği" partial'ı)
    + IP kısıtlaması (`Routing\IpAllowlist`, Core'un yeni
    `Settings\SettingsRepositoryInterface`'i üzerinden yapılandırılır,
    `/admin`'de `template_redirect` önceliği 6'da uygulanır) — bkz.
    `docs/ARCHITECTURE.md` bölüm 16~~ ✅
14. ~~Seviye Reports: Commerce'ten yayınlanan `OrderLineItemQueryInterface`
    Contract'ı üzerinden şube/ürün/kategori/dönem bazlı satış raporu
    (`GET seviye/v1/reports/sales`, JSON/CSV/XLSX), RBAC
    (`VIEW_REPORTS`/`VIEW_OWN_REPORTS`, Finance'in hakediş RBAC'ının aynası),
    tema "Raporlar" paneli — bkz. `docs/ARCHITECTURE.md` bölüm 17~~ ✅
15. ~~Seviye Notifications: `scp_notifications` günlüğü (yerinde
    güncellenen tek istisna tablo), `NotificationDispatcher` (record →
    resolve recipient → send → mark-sent/failed, üç kanal için de aynı
    akış), e-posta (`wp_mail()`), SMS (NetGSM REST API'si, Core'un
    Settings'i üzerinden yapılandırılır, Parents'ın yeni yayınladığı
    `ParentContactLookupInterface`'i tüketir) ve panel-içi (self-servis
    REST + tema bildirim çanı, `header.php`) kanalları, ilk gerçek
    `security.password_reset_requested` dinleyicisi — bkz.
    `docs/ARCHITECTURE.md` bölüm 18~~ ✅
16. ~~Seviye API: `scp_api_keys` (SHA-256 özetlenmiş, düz metin asla
    saklanmaz), `ApiKeyAuthenticator` (IP başına throttle edilmiş) +
    `rest_authentication_errors` filtresi (platformun ilk kullanımı,
    mevcut cookie+nonce akışını asla bozmayacak şekilde), `seviye/v1/api-keys`
    (yalnızca Genel Merkez) — yeni iş mantığı uç noktası eklemez, tamamen
    mevcut `seviye/v1/*` uçlarını API anahtarıyla erişilebilir kılan bir
    kimlik doğrulama katmanı, tema "API Anahtarları" paneli — bkz.
    `docs/ARCHITECTURE.md` bölüm 19~~ ✅

Bu sıralamanın gerekçesi: her modül yalnızca Core'a bağımlı olsa da, veri
modeli olarak Commerce'in Branches/Students/Pricing olmadan anlamı yoktur;
bu yüzden geliştirme sırası veri bağımlılık grafiğini takip eder, kod
bağımlılığını değil.
