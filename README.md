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

| Plugin/Tema | Durum |
|---|---|
| Seviye Core | ✅ Kuruldu |
| Seviye Security | ✅ TC Kimlik No auth, rate limiting, şifre token'ları, rol→bölge yönlendirme politikası, 2FA (TOTP, RFC 6238) ve `/admin` IP kısıtlaması kuruldu |
| Seviye Branches | ✅ Şube entity, Yetkililer (personel ataması), Contracts, REST, RBAC kuruldu; logo yükleme planlandı |
| Seviye Students | ✅ Öğrenci entity (şube/eğitim yılı/sınıf), veli ile çoktan-çoğa ilişki, REST, RBAC kuruldu |
| Seviye Parents | ✅ Veli profili (telefon, bildirim tercihi, KVKK onayı), REST, RBAC kuruldu |
| Seviye Pricing | ✅ Öğrenci/şube/genel kapsamlı özel fiyat kuralları, öncelik-bazlı `PriceResolverInterface`, REST, RBAC kuruldu |
| Seviye Commerce | ✅ Sepet fiyatlandırma, tema tarafı (ürün sayfası öğrenci seçici, mağaza girişi), sipariş kalıcılığı (`scp_order_line_items`, KDV tutarı dahil) ve split payment + hakediş event tetikleme kuruldu (asıl hakediş/cari kaydı Seviye Finance'ın işi) |
| Seviye Finance | 🟡 Hakediş defteri (`scp_hakedis_entries`, Commerce'in event'lerini dinleyen değişmez kayıtlar, KDV tutarı dahil), cari bakiye REST'i, tahsilat (settlement) defteri + REST'i ve tema paneli kuruldu; iade akışı planlandı |
| Seviye Reports | ✅ Şube/ürün/kategori/dönem bazlı satış raporu (`GET seviye/v1/reports/sales`, JSON/CSV/XLSX), Commerce'in `OrderLineItemQueryInterface` Contract'ı üzerinden, RBAC, tema paneli kuruldu; PDF çıktısı planlandı |
| Seviye Notifications | ✅ `scp_notifications` günlüğü, e-posta (`wp_mail()`), SMS (NetGSM, Parents'ın `ParentContactLookupInterface`'i üzerinden veli telefon numarası) ve panel-içi (tema bildirim çanı) kanalları, `security.password_reset_requested` dinleyicisi, RBAC, tema paneli kuruldu |
| Seviye API | ✅ API anahtarı tabanlı kimlik doğrulama (`rest_authentication_errors`, `Authorization: Bearer`, SHA-256 özetlenmiş anahtarlar, IP başına throttle), `seviye/v1/api-keys` (yalnızca Genel Merkez), tema paneli kuruldu; yeni iş mantığı uç noktası eklemez, mevcut `seviye/v1/*` uçlarını API anahtarıyla erişilebilir kılar |
| Seviye Storefront (tema) | 🟡 Giriş ekranı (2FA kod adımı dahil), içerik kilidi, rol yönlendirmesi, öğrenci yönetim paneli (`/sube`, `/admin`), şube yönetim paneli (`/admin`), fiyat kuralları paneli (`/sube`, `/admin`), cari bakiye + tahsilat paneli (`/sube`, `/admin`), Hesap Güvenliği (2FA) paneli (her bölgede), IP kısıtlaması ayarı (`/admin`), Raporlar paneli (`/sube`, `/admin`), panel-içi bildirim çanı (her sayfada) + SMS ayarları (`/admin`), API Anahtarları paneli (`/admin`), Veli ana sayfası (kendi öğrencileri + profil + mağaza girişi) ve WooCommerce ürün sayfası öğrenci seçici kuruldu |

Tam yol haritası: [`docs/ROADMAP.md`](docs/ROADMAP.md).

## Geliştirme ortamı kurulumu

Gereksinimler: PHP 8.2+, Composer, WordPress 6.5+, WooCommerce (aktif).

```bash
# Kod standardı araçları (kök seviye)
composer install

# Her eklenti kendi bağımlılıklarını kendi klasöründe kurar
cd plugin/seviye-core && composer install && cd -
cd plugin/seviye-security && composer install && cd -
cd plugin/seviye-branches && composer install && cd -
cd plugin/seviye-students && composer install && cd -
cd plugin/seviye-parents && composer install && cd -
cd plugin/seviye-pricing && composer install && cd -
cd plugin/seviye-commerce && composer install && cd -
cd plugin/seviye-finance && composer install && cd -
cd plugin/seviye-reports && composer install && cd -
cd plugin/seviye-notifications && composer install && cd -
cd plugin/seviye-api && composer install && cd -
```

`plugin/seviye-core`, `plugin/seviye-security`, `plugin/seviye-branches`,
`plugin/seviye-students`, `plugin/seviye-parents`, `plugin/seviye-pricing`,
`plugin/seviye-commerce`, `plugin/seviye-finance`, `plugin/seviye-reports`,
`plugin/seviye-notifications` ve `plugin/seviye-api` klasörlerini
WordPress'in `wp-content/plugins/` altına, `theme/seviye-storefront`'u ise
`wp-content/themes/` altına sembolik link ile bağlayın. Ardından
WooCommerce'i, **Seviye Core'u**, **Seviye Security'yi**, **Seviye
Branches'ı**, **Seviye Students'ı**, **Seviye Parents'ı**, **Seviye
Pricing'i**, **Seviye Commerce'i**, **Seviye Finance'ı**, **Seviye
Reports'u**, **Seviye Notifications'ı** ve **Seviye API'yi** (bu sırayla —
Students, Branches'ın `scp_branches` tablosunun ve Contracts'ının zaten var
olmasını gerektirir; Pricing hem Branches'ın hem Students'ın Contracts'ını
tükettiğinden ikisi de zaten aktif olmalıdır; Commerce Branches'ın,
Students'ın ve Pricing'in Contracts'ını tükettiğinden üçü de zaten aktif
olmalıdır; Finance'ın hakediş defteri Commerce'in event'lerini yalnızca
Core'un EventBus'ı üzerinden dinler (Commerce'in kendisine bağımlı
değildir), ama cari bakiye REST'i Branches'ın Contracts'ını tükettiğinden
ve migration'ı `scp_students`'a bir FK kurduğundan Branches'ın ve
Students'ın zaten aktif olmasını gerektirir; Parents'ın böyle bir
bağımlılığı yoktur, ama tutarlılık için aynı sırada aktive edilmesi
önerilir; Reports kendi tablosunu/migration'ını kurmaz ama Commerce'in
`OrderLineItemQueryInterface` Contract'ını ve Branches'ın Contracts'ını
tükettiğinden ikisinin de zaten aktif olmasını gerektirir) aktive
edin, son olarak **Seviye Storefront** temasını etkinleştirin. Core
aktivasyonu; PHP sürümünü ve
WooCommerce'in aktif olduğunu doğrular, 9 platform rolünü kaydeder ve kendi
migration'larını (`scp_logs`, `scp_settings`, `scp_migrations`) çalıştırır.
Security aktivasyonu Core'un aktif olduğunu doğrular ve kendi
migration'larını (`scp_user_identities`, `scp_password_tokens`) çalıştırır.
Branches aktivasyonu da aynı şekilde Core'u doğrular ve kendi
migration'larını (`scp_branches`, `scp_branch_users`) çalıştırır. Students
aktivasyonu Core'u **ve Branches'ın aktif olduğunu** doğrular (aksi halde
`scp_students`'ın `scp_branches`'a FK kurması başarısız olur) ve kendi
migration'larını (`scp_students`, `scp_student_parents`) çalıştırır. Parents
aktivasyonu yalnızca Core'u doğrular ve kendi migration'unu
(`scp_parent_profiles`) çalıştırır. Pricing aktivasyonu Core'u, **Branches'ın**
ve **Students'ın aktif olduğunu** doğrular (aksi halde `scp_price_rules`'ın
FK'ları kurulamaz) ve kendi migration'unu (`scp_price_rules`) çalıştırır.
Commerce aktivasyonu Core'u, WooCommerce'in aktif olduğunu, **Branches'ın**,
**Students'ın** ve **Pricing'in aktif olduğunu** doğrular (aksi halde
`scp_order_line_items`'ın FK'ları kurulamaz) ve kendi migration'unu
(`scp_order_line_items`) çalıştırır. Finance aktivasyonu Core'u, **Branches'ın** ve **Students'ın aktif
olduğunu** doğrular (Commerce'in kendisine değil — hakediş defteri
event'leri yalnızca Core'un EventBus'ı üzerinden dinler; ama cari bakiye
REST'i Branches'ın Contracts'ını tüketir, ve migration'ı `scp_students`'a
FK kurar) ve kendi migration'unu (`scp_hakedis_entries`) çalıştırır. Reports
aktivasyonu Core'u, WooCommerce'in aktif olduğunu, **Branches'ın** ve
**Commerce'in aktif olduğunu** doğrular (satış raporu Commerce'in
`OrderLineItemQueryInterface` Contract'ını tüketir) — kendi tablosu/migration'ı
yoktur, tamamen diğer modüllerin Contracts'ı üzerine kurulu salt okunur bir
katmandır. Notifications aktivasyonu Core'u ve **Parents'ın aktif olduğunu**
doğrular (SMS kanalı Parents'ın `ParentContactLookupInterface`'ini
tüketir — Branches/Students/Pricing/Commerce/Finance/Reports'a bağımlı
değildir, listede en sona konması yalnızca tutarlılık içindir) ve kendi
migration'unu (`scp_notifications`) çalıştırır. API aktivasyonu yalnızca
Core'u doğrular (başka hiçbir modüle bağımlı değildir — yeni iş mantığı uç
noktası eklemez, mevcut `seviye/v1/*` uçlarını bir API anahtarıyla
erişilebilir kılan bir kimlik doğrulama katmanıdır) ve kendi migration'unu
(`scp_api_keys`) çalıştırır. Tema etkinleştirildiğinde
`/admin` ve `/sube` rotalarını tanımlayan rewrite kuralları eklenir
(`after_switch_theme` üzerinden otomatik `flush`).

> **Not**: Bu depo headless bir CI/CLI oturumunda geliştirildi; PHP sözdizimi,
> statik kod standardı (PHPCS) ve tüm birim testleri doğrulandı, ancak canlı
> bir WordPress + MySQL ortamında tarayıcıda görsel olarak test edilmedi.
> Yayına almadan önce gerçek bir WordPress kurulumunda uçtan uca (giriş,
> şifremi unuttum, ilk şifre oluştur, rol yönlendirmesi, `/sube`/`/admin`
> öğrenci paneli, Veli ana sayfası) test edilmesi önerilir.

## Test

```bash
cd plugin/seviye-core && composer test
cd plugin/seviye-security && composer test
cd plugin/seviye-branches && composer test
cd plugin/seviye-students && composer test
cd plugin/seviye-parents && composer test
cd plugin/seviye-pricing && composer test
cd plugin/seviye-commerce && composer test
cd plugin/seviye-finance && composer test
cd plugin/seviye-reports && composer test
cd plugin/seviye-notifications && composer test
cd plugin/seviye-api && composer test
```

Kök dizinde kod standardı denetimi:

```bash
composer install
vendor/bin/phpcs
```

## Kod standartları

Bkz. [`docs/CODING_STANDARDS.md`](docs/CODING_STANDARDS.md) — PHP 8.2+ tür
bildirimleri, PSR-4/PSR-11/PSR-3 uyumluluğu, SOLID, güvenlik kontrol listesi.
