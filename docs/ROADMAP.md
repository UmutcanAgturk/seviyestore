# Yol Haritası

Spesifikasyondaki 11 plugin ve durumları. Her modül Core'a bağımlıdır,
başka hiçbir modüle bağımlı değildir.

| # | Plugin | Sorumluluk | Durum |
|---|---|---|---|
| 1 | Seviye Core | DI container, event bus, RBAC, migration runner, audit log, REST altyapısı | **Kuruldu** (bu milestone) |
| 2 | Seviye Students | Öğrenci yönetimi (şube/eğitim yılı/sınıf/veli ilişkisi) | Planlandı |
| 3 | Seviye Parents | Veli yönetimi (çoklu öğrenci görünürlüğü) | Planlandı |
| 4 | Seviye Branches | Şube yönetimi (IBAN, komisyon, logo, yetkililer) | Planlandı |
| 5 | Seviye Pricing | Özel fiyatlandırma motoru (öğrenci→şube→bölge→genel→WC varsayılan önceliği) | Planlandı |
| 6 | Seviye Commerce | WooCommerce entegrasyonu, sipariş akışı, split payment | Planlandı |
| 7 | Seviye Finance | Cari, hakediş, komisyon, KDV, iade, tahsilat | Planlandı |
| 8 | Seviye Reports | Excel/CSV/PDF raporlama (şube/ürün/kategori/dönem bazlı) | Planlandı |
| 9 | Seviye Notifications | SMS/e-posta/panel içi bildirimler | Planlandı |
| 10 | Seviye API | `seviye/v1` REST uç noktaları (ERP/CRM/muhasebe/mobil entegrasyonu) | Planlandı |
| 11 | Seviye Security | 2FA, IP kısıtlama, gelişmiş audit/izleme | Planlandı |

## Tema ve giriş akışı

| Bileşen | Durum |
|---|---|
| TC Kimlik No + şifre giriş ekranı, ilk şifre oluşturma, şifremi unuttum | Planlandı (sıradaki aday milestone) |
| `store.seviye.com.tr` (Veli), `/sube` (Şube Paneli), `/admin` (Genel Merkez) yönlendirmesi | Planlandı |

## Milestone sırası önerisi

1. ~~Repo iskeleti + Seviye Core~~ ✅
2. Giriş ekranı + TC Kimlik No auth akışı (Core'un RBAC/RateLimiter'ını kullanır)
3. Seviye Branches + Seviye Students + Seviye Parents (temel varlık modelleri, FK'lı şema)
4. Seviye Pricing (fiyat motoru, henüz sipariş yok)
5. Seviye Commerce (WooCommerce entegrasyonu, sipariş akışı, hakediş tetikleme)
6. Seviye Finance + Seviye Reports
7. Seviye Notifications + Seviye API + Seviye Security (sertleştirme)

Bu sıralamanın gerekçesi: her modül yalnızca Core'a bağımlı olsa da, veri
modeli olarak Commerce'in Branches/Students/Pricing olmadan anlamı yoktur;
bu yüzden geliştirme sırası veri bağımlılık grafiğini takip eder, kod
bağımlılığını değil.
