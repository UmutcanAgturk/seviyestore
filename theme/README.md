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

Kapsam dışı (henüz kurulmadı, ilgili modüller geldiğinde eklenecek):

- Gerçek şube/öğrenci/sipariş panel içerikleri (`/sube`, `/admin` şu an
  minimal, dürüst bir "hoş geldiniz" ekranı gösterir — sahte veri/placeholder
  bileşen içermez).
- WooCommerce mağaza görünümü (Seviye Commerce modülü kurulduğunda).

Tema, diğer tüm bileşenler gibi yalnızca Seviye Core'un (ve ilgili modüllerin)
yayınladığı public API'ler / REST uç noktaları üzerinden veri okur; doğrudan
`scp_*` tablolarını sorgulamaz.

**Not**: Bu tema headless bir oturumda (canlı WordPress/MySQL olmadan)
geliştirildi; PHP syntax ve statik kod standardı kontrolleri geçti, ancak
gerçek bir tarayıcıda görsel olarak doğrulanmadı. Kuruluma dair adımlar için
kök `README.md`'ye bakın.
