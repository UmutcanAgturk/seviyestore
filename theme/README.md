# Tema

`Seviye Storefront` teması `theme/seviye-storefront/` altındadır.

Kurulu olan kapsam:

- **Giriş kilidi**: oturum açılmadan hiçbir sayfa/ürün görüntülenmez.
  `inc/access-gate.php`, her oturumsuz front-end isteğinde normal WordPress
  şablon hiyerarşisini atlayıp doğrudan `templates/login.php`'yi render eder.
- **Giriş ekranı**: TC Kimlik No, Şifre, Giriş Yap, Şifremi Unuttum, İlk
  Şifre Oluştur — `Seviye Security`'nin `seviye/v1/auth/*` REST uçlarını
  `assets/js/auth.js` üzerinden çağırır. Backend doğrulama/rate-limit/token
  mantığının tamamı Security'de yaşar; tema yalnızca arayüzdür.
- **Rol bazlı bölge yönlendirmesi**: `/` (Veli), `/sube` (Şube Paneli),
  `/admin` (Genel Merkez) — bölge kuralı `Seviye\Security\Routing\RoleRouter`
  tarafından belirlenir (test edilebilir, WordPress'ten bağımsız saf PHP);
  tema yalnızca bu kararı `inc/access-gate.php` ve `inc/zones.php` üzerinden
  uygular.
- **Öğrenci yönetim paneli** (`/sube`, `/admin`): `templates/zone.php` +
  `assets/js/students-panel.js`, `seviye/v1/students`'a bağlı gerçek
  liste/oluştur/düzenle ve veli bağlama arayüzü. Şube kapsaması sunucu
  tarafında zaten yapıldığından (`Şube Müdürü` yalnızca kendi şubesini
  görür), aynı ekran her iki bölgede de kullanılır. `scp_manage_students`
  yetkisi olmayan şube rolleri (Muhasebe, Depo, Satış Danışmanı, Rehberlik)
  için dürüst bir "içerik henüz yok" mesajı gösterilir.
- **Veli ana sayfası** (`/`): `templates/parent-dashboard.php` +
  `assets/js/parent-dashboard.js` — kendi öğrencileri (salt okunur,
  `seviye/v1/students/mine`) ve profil formu (`seviye/v1/parents/me`,
  telefon/bildirim tercihi/KVKK onayı).

Kapsam dışı (henüz kurulmadı, ilgili modüller geldiğinde eklenecek):

- Şube yönetim ekranı (`/admin`'de "Şubeler" listesi/formu — `seviye/v1/branches`
  REST'i hazır, tema henüz bağlanmadı).
- Sipariş/finans panel içerikleri.
- WooCommerce mağaza görünümü (Seviye Commerce modülü kurulduğunda).

Tema, diğer tüm bileşenler gibi yalnızca Seviye Core'un (ve ilgili modüllerin)
yayınladığı public API'ler / REST uç noktaları üzerinden veri okur; doğrudan
`scp_*` tablolarını sorgulamaz.

**Not**: Bu tema headless bir oturumda (canlı WordPress/MySQL olmadan)
geliştirildi; PHP syntax ve statik kod standardı kontrolleri geçti, ancak
gerçek bir tarayıcıda görsel olarak doğrulanmadı. Kuruluma dair adımlar için
kök `README.md`'ye bakın.
