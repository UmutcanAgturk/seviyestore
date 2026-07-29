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
- **Şube yönetim paneli** (yalnızca `/admin`): `templates/zone.php`'ye
  eklenen "Şubeler" bölümü + `assets/js/branches-panel.js`,
  `seviye/v1/branches`'a bağlı gerçek liste/oluştur/düzenle arayüzü.
  Yalnızca `scp_manage_branches` yetkisi olan roller (Genel Merkez, Bölge
  Müdürü) görür; `inc/zones.php`'deki `scp_current_zone()` yardımcısıyla
  bölge açıkça `admin` olarak doğrulanır.
- **Fiyat kuralları paneli** (`/sube` VE `/admin`): `templates/zone.php`'ye
  eklenen "Fiyat Kuralları" bölümü + `assets/js/pricing-panel.js`,
  `seviye/v1/pricing/rules`'a bağlı gerçek liste/oluştur/düzenle/sil
  arayüzü. `scp_manage_pricing` Şube Müdürü'ne de verildiğinden (Branches'ın
  aksine), panel her iki bölgede de görünür; henüz bir ürün kataloğu
  olmadığından kurallar bir ürün ID'si girilerek aranır. Şube-kapsamlı
  roller "Genel" kapsam seçeneğini görmez ve BRANCH kapsamlı bir kural
  oluştururken kendi şube ID'lerini girmeleri gerekmez (sunucu otomatik
  olarak kendi şubelerine sabitler).
- **Cari bakiye paneli** (`/sube` VE `/admin`): `templates/zone.php`'ye
  eklenen "Cari Bakiye" bölümü + `assets/js/hakedis-panel.js`,
  `seviye/v1/finance/hakedis/balance/*`'a bağlı salt okunur görünüm.
  `scp_view_hakedis` (Genel Merkez, Bölge Müdürü) tüm şubelerin bakiyesini
  gösteren bir tablo görür; `scp_view_own_hakedis` (yalnızca Şube Müdürü +
  Muhasebe) yalnızca kendi şubesinin bakiyesini gösteren tek bir kart görür.
  Tüm şubelerin bakiyesini listeleyen ayrı bir REST uç noktası yoktur — HQ
  görünümü genel `GET /branches` ile her şube için bir
  `GET /finance/hakedis/balance/{id}` çağrısını istemci tarafında birleştirir.
- **WooCommerce ürün sayfası — öğrenci seçici** (`inc/woocommerce.php` +
  `assets/js/product-student-picker.js`): `add_theme_support('woocommerce')`
  zaten kuruluydu, bu yüzden özel bir şablon dosyası gerekmedi — yalnızca
  `woocommerce_before_add_to_cart_button` hook'una bir `<select>` eklendi,
  `seviye/v1/students/mine`'dan doldurulur. Seçilen değer WC'nin kendi
  `form.cart` POST'una otomatik dahil olur. Vitrin (arşiv) sayfasındaki
  anlık "sepete ekle" düğmesi, öğrenci seçimini taşıyamadığından ürün
  sayfasına giden düz bir bağlantıya dönüştürüldü
  (`woocommerce_loop_add_to_cart_link`). Veli ana sayfasına
  `wc_get_page_permalink('shop')`'a bağlanan bir "Mağazaya Git" bölümü
  eklendi.

Kapsam dışı (henüz kurulmadı, ilgili modüller geldiğinde eklenecek):

- Tahsilat işaretleme, KDV takibi (Seviye Finance'ın sonraki bölümü).

Tema, diğer tüm bileşenler gibi yalnızca Seviye Core'un (ve ilgili modüllerin)
yayınladığı public API'ler / REST uç noktaları üzerinden veri okur; doğrudan
`scp_*` tablolarını sorgulamaz.

**Not**: Bu tema headless bir oturumda (canlı WordPress/MySQL olmadan)
geliştirildi; PHP syntax ve statik kod standardı kontrolleri geçti, ancak
gerçek bir tarayıcıda görsel olarak doğrulanmadı. Kuruluma dair adımlar için
kök `README.md`'ye bakın.
