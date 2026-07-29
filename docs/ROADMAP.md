# Yol Haritası

Spesifikasyondaki 11 plugin ve durumları. Her modül Core'a bağımlıdır;
ayrıca gerçek bir alan-modeli ilişkisi olduğunda başka bir modülün açıkça
yayınladığı `Contracts` arayüzüne de bağımlı olabilir (bkz.
`docs/ARCHITECTURE.md`, "Kural") — internal sınıflarına asla.

| # | Plugin | Sorumluluk | Durum |
|---|---|---|---|
| 1 | Seviye Core | DI container, event bus, RBAC, migration runner, audit log, REST altyapısı | ✅ **Kuruldu** |
| 2 | Seviye Students | Öğrenci entity (şube/eğitim yılı/sınıf), veli (WP kullanıcı) ile çoktan-çoğa ilişki, REST | ✅ **Kuruldu** (bu milestone) |
| 3 | Seviye Parents | Veli'ye özgü profil alanları (telefon, bildirim tercihi, KVKK onayı), REST | ✅ **Kuruldu** (bu milestone) |
| 4 | Seviye Branches | Şube entity (IBAN, komisyon, telefon, adres), Yetkililer (personel-şube ataması), Contracts, REST | ✅ **Kuruldu** (logo yükleme henüz yok) |
| 5 | Seviye Pricing | Özel fiyatlandırma motoru (öğrenci→şube→bölge→genel→WC varsayılan önceliği) | Planlandı |
| 6 | Seviye Commerce | WooCommerce entegrasyonu, sipariş akışı, split payment | Planlandı |
| 7 | Seviye Finance | Cari, hakediş, komisyon, KDV, iade, tahsilat | Planlandı |
| 8 | Seviye Reports | Excel/CSV/PDF raporlama (şube/ürün/kategori/dönem bazlı) | Planlandı |
| 9 | Seviye Notifications | SMS/e-posta/panel içi bildirimler | Planlandı |
| 10 | Seviye API | `seviye/v1` REST uç noktaları (ERP/CRM/muhasebe/mobil entegrasyonu) | Planlandı |
| 11 | Seviye Security | TC Kimlik No auth, rate limiting, şifre/ilk-kurulum token'ları, rol→bölge politikası | 🟡 **Kısmen kuruldu**; 2FA, IP kısıtlama planlandı |

## Tema ve giriş akışı

| Bileşen | Durum |
|---|---|
| Backend: TC Kimlik No doğrulama, `AuthService` (rate-limitli giriş), şifre/ilk-şifre token sistemi, `seviye/v1/auth/*` REST uçları | **Kuruldu** (`Seviye Security`) |
| Frontend: giriş ekranı (HTML/JS), içerik kilitleme, rol bazlı `/`, `/sube`, `/admin` yönlendirmesi | **Kuruldu** (`Seviye Storefront` teması) |
| `/sube` ve `/admin` panelleri: öğrenci listesi/formu, veli bağlama | **Kuruldu** — `seviye/v1/students`'a bağlı, gerçek CRUD ekranı |
| `/` (Veli ana sayfası): kendi öğrencileri (salt okunur liste) + profil formu | **Kuruldu** — `seviye/v1/students/mine` ve `seviye/v1/parents/me`'ye bağlı |
| Şube yönetimi ekranı (`/admin`'de "Şubeler" listesi/formu) | Planlandı — `seviye/v1/branches` REST'i hazır, tema henüz bağlanmadı |
| Sipariş/finans panel içeriği | Planlandı (Seviye Commerce/Finance'ın sorumluluğu) |
| E-posta/SMS ile token teslimi (`security.password_reset_requested` olayının dinlenmesi) | Planlandı (Seviye Notifications'ın sorumluluğu) |
| WooCommerce mağaza görünümü (Veli ana sayfası) | Planlandı (Seviye Commerce'in sorumluluğu) |

## Milestone sırası önerisi

1. ~~Repo iskeleti + Seviye Core~~ ✅
2. ~~Giriş akışı backend'i: Seviye Security (TC Kimlik No doğrulama, AuthService, şifre/ilk-kurulum token sistemi, REST uçları)~~ ✅
3. ~~Tema: giriş ekranı, içerik kilitleme, rol bazlı `/`, `/sube`, `/admin` yönlendirmesi~~ ✅
4. ~~Seviye Branches (şube entity, Yetkililer/personel ataması, Contracts, REST, RBAC)~~ ✅
5. ~~Seviye Students (öğrenci entity, veli çoktan-çoğa ilişkisi, Branches'ın Contracts'ını kullanan ilk modül, REST, RBAC)~~ ✅
6. ~~Seviye Parents (veli'ye özgü profil alanları, REST, RBAC — Core-only, Contracts'a ihtiyaç duymayan ilk modül)~~ ✅
7. ~~Tema: `/sube`/`/admin` öğrenci paneli + veli ana sayfası (kendi öğrencileri + profil), gerçek REST'e bağlı~~ ✅
8. Şube yönetimi ekranı (`/admin`'e "Şubeler" listesi/formu — REST zaten hazır)
9. Seviye Pricing (fiyat motoru, henüz sipariş yok)
10. Seviye Commerce (WooCommerce entegrasyonu, sipariş akışı, hakediş tetikleme) — Veli ana sayfasına mağaza içeriğini kazandırır
11. Seviye Finance + Seviye Reports
12. Seviye Notifications + Seviye API + Seviye Security'nin geri kalanı (2FA, IP kısıtlama)

Bu sıralamanın gerekçesi: her modül yalnızca Core'a bağımlı olsa da, veri
modeli olarak Commerce'in Branches/Students/Pricing olmadan anlamı yoktur;
bu yüzden geliştirme sırası veri bağımlılık grafiğini takip eder, kod
bağımlılığını değil.
