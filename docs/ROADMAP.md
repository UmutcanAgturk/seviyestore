# Yol Haritası

Spesifikasyondaki 11 plugin ve durumları. Her modül Core'a bağımlıdır,
başka hiçbir modüle bağımlı değildir.

| # | Plugin | Sorumluluk | Durum |
|---|---|---|---|
| 1 | Seviye Core | DI container, event bus, RBAC, migration runner, audit log, REST altyapısı | ✅ **Kuruldu** |
| 2 | Seviye Students | Öğrenci yönetimi (şube/eğitim yılı/sınıf/veli ilişkisi) | Planlandı |
| 3 | Seviye Parents | Veli yönetimi (çoklu öğrenci görünürlüğü) | Planlandı |
| 4 | Seviye Branches | Şube entity (IBAN, komisyon, telefon, adres), Yetkililer (personel-şube ataması), REST | ✅ **Kuruldu** (bu milestone; logo yükleme henüz yok) |
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
| `/sube` ve `/admin` panellerinin gerçek içeriği (şube/öğrenci/sipariş/finans verileri) | Planlandı — şu an yalnızca dürüst, minimal bir "hoş geldiniz" ekranı var |
| E-posta/SMS ile token teslimi (`security.password_reset_requested` olayının dinlenmesi) | Planlandı (Seviye Notifications'ın sorumluluğu) |
| WooCommerce mağaza görünümü (Veli ana sayfası) | Planlandı (Seviye Commerce'in sorumluluğu) |

## Milestone sırası önerisi

1. ~~Repo iskeleti + Seviye Core~~ ✅
2. ~~Giriş akışı backend'i: Seviye Security (TC Kimlik No doğrulama, AuthService, şifre/ilk-kurulum token sistemi, REST uçları)~~ ✅
3. ~~Tema: giriş ekranı, içerik kilitleme, rol bazlı `/`, `/sube`, `/admin` yönlendirmesi~~ ✅
4. ~~Seviye Branches (şube entity, Yetkililer/personel ataması, REST, RBAC)~~ ✅
5. Seviye Students + Seviye Parents (öğrenci/veli entity'leri, çoklu-veli ilişkisi, Branches'ın `branch_id` FK'sini kullanır) — `/sube` ve `/admin` panellerine ilk gerçek içeriği kazandıracak modüller
6. Seviye Pricing (fiyat motoru, henüz sipariş yok)
7. Seviye Commerce (WooCommerce entegrasyonu, sipariş akışı, hakediş tetikleme) — Veli ana sayfasına mağaza içeriğini kazandırır
8. Seviye Finance + Seviye Reports
9. Seviye Notifications + Seviye API + Seviye Security'nin geri kalanı (2FA, IP kısıtlama)

Bu sıralamanın gerekçesi: her modül yalnızca Core'a bağımlı olsa da, veri
modeli olarak Commerce'in Branches/Students/Pricing olmadan anlamı yoktur;
bu yüzden geliştirme sırası veri bağımlılık grafiğini takip eder, kod
bağımlılığını değil.
