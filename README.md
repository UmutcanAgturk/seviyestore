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
| Seviye Security | 🟡 TC Kimlik No auth, rate limiting, şifre token'ları, rol→bölge yönlendirme politikası kuruldu; 2FA/IP kısıtlama planlandı |
| Seviye Branches | ✅ Şube entity, Yetkililer (personel ataması), Contracts, REST, RBAC kuruldu; logo yükleme planlandı |
| Seviye Students | ✅ Öğrenci entity (şube/eğitim yılı/sınıf), veli ile çoktan-çoğa ilişki, REST, RBAC kuruldu |
| Seviye Parents | ✅ Veli profili (telefon, bildirim tercihi, KVKK onayı), REST, RBAC kuruldu |
| Seviye Pricing | ✅ Öğrenci/şube/genel kapsamlı özel fiyat kuralları, öncelik-bazlı `PriceResolverInterface`, REST, RBAC kuruldu |
| Seviye Commerce | 🟡 Sepet fiyatlandırma (öğrenci seçimi doğrulaması, `PriceResolverInterface` entegrasyonu, sipariş kalemi meta'sına kalıcı kayıt) kuruldu; ürün sayfası/öğrenci seçici arayüzü, sipariş kalıcılığı, split payment, hakediş tetikleme planlandı |
| Seviye Storefront (tema) | 🟡 Giriş ekranı, içerik kilidi, rol yönlendirmesi, öğrenci yönetim paneli (`/sube`, `/admin`), şube yönetim paneli (`/admin`), fiyat kuralları paneli (`/sube`, `/admin`) ve Veli ana sayfası (kendi öğrencileri + profil) kuruldu; WooCommerce mağaza görünümü/sipariş/finans panelleri planlandı |
| Seviye Finance, Reports, Notifications, API | Planlandı |

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
```

`plugin/seviye-core`, `plugin/seviye-security`, `plugin/seviye-branches`,
`plugin/seviye-students`, `plugin/seviye-parents`, `plugin/seviye-pricing`
ve `plugin/seviye-commerce` klasörlerini WordPress'in `wp-content/plugins/`
altına, `theme/seviye-storefront`'u ise `wp-content/themes/` altına
sembolik link ile bağlayın. Ardından WooCommerce'i, **Seviye Core'u**,
**Seviye Security'yi**, **Seviye Branches'ı**, **Seviye Students'ı**,
**Seviye Parents'ı**, **Seviye Pricing'i** ve **Seviye Commerce'i** (bu
sırayla — Students, Branches'ın `scp_branches` tablosunun ve Contracts'ının
zaten var olmasını gerektirir; Pricing hem Branches'ın hem Students'ın
Contracts'ını tükettiğinden ikisi de zaten aktif olmalıdır; Commerce
Students'ın ve Pricing'in Contracts'ını tükettiğinden ikisi de zaten aktif
olmalıdır; Parents'ın böyle bir bağımlılığı yoktur, ama tutarlılık için aynı
sırada aktive edilmesi önerilir) aktive edin, son olarak **Seviye
Storefront** temasını etkinleştirin. Core aktivasyonu; PHP sürümünü ve
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
Commerce aktivasyonu Core'u, WooCommerce'in aktif olduğunu, **Students'ın**
ve **Pricing'in aktif olduğunu** doğrular; kendi migration'u yoktur (bu
milestone'da hiçbir `scp_*` tablosu eklemez — bkz. `database/README.md`).
Tema etkinleştirildiğinde `/admin` ve `/sube` rotalarını tanımlayan rewrite
kuralları eklenir (`after_switch_theme` üzerinden otomatik `flush`).

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
```

Kök dizinde kod standardı denetimi:

```bash
composer install
vendor/bin/phpcs
```

## Kod standartları

Bkz. [`docs/CODING_STANDARDS.md`](docs/CODING_STANDARDS.md) — PHP 8.2+ tür
bildirimleri, PSR-4/PSR-11/PSR-3 uyumluluğu, SOLID, güvenlik kontrol listesi.
