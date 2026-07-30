# Tema

`Seviye Storefront` teması `theme/seviye-storefront/` altındadır.

Kurulu olan kapsam:

- **Giriş kilidi**: oturum açılmadan hiçbir sayfa/ürün görüntülenmez.
  `inc/access-gate.php`, her oturumsuz front-end isteğinde normal WordPress
  şablon hiyerarşisini atlayıp doğrudan `templates/login.php`'yi render eder.
- **Giriş ekranı**: TC Kimlik No, Şifre, Giriş Yap, Şifremi Unuttum, İlk
  Şifre Oluştur — `Seviye Security`'nin `seviye/v1/auth/*` REST uçlarını
  `assets/js/auth.js` üzerinden çağırır. Backend doğrulama/rate-limit/token
  mantığının tamamı Security'de yaşar; tema yalnızca arayüzdür. Hesabında
  2FA açık bir kullanıcı için `login`, cookie set etmeden
  `requires_2fa: true` + kısa ömürlü bir `pending_token` döner; ekran
  otomatik olarak bir "Doğrulama Kodu" adımına geçer ve
  `seviye/v1/auth/login/2fa`'yı çağırır.
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
- **Cari bakiye + tahsilat paneli** (`/sube` VE `/admin`): `templates/zone.php`'ye
  eklenen "Cari Bakiye" bölümü + `assets/js/hakedis-panel.js`,
  `seviye/v1/finance/hakedis/*`'a bağlı görünüm. `scp_view_hakedis` (Genel
  Merkez, Bölge Müdürü) tüm şubelerin alacak/ödenen/bakiye kırılımını
  gösteren bir tablo görür; `scp_view_own_hakedis` (yalnızca Şube Müdürü +
  Muhasebe) yalnızca kendi şubesinin kırılımını gösteren bir kart görür.
  Tüm şubelerin bakiyesini listeleyen ayrı bir REST uç noktası yoktur — HQ
  görünümü genel `GET /branches` ile her şube için bir
  `GET /finance/hakedis/balance/{id}` çağrısını istemci tarafında birleştirir.
  Panel ayrıca bir "Tahsilat" alt bölümü render eder: şube seçici + tahsilat
  geçmişi (`GET /finance/hakedis/settlements/{branch_id}`) görüntüleme
  kapasiteye göre herkese açık (HQ görünümünde bir şube seçici, şube
  görünümünde örtük olarak kendi şubesi), ama tahsilat kaydetme formu
  (`POST /finance/hakedis/settlements`) yalnızca `scp_record_hakedis_settlement`
  (Genel Merkez/Muhasebe) taşıyanlarda görünür.
- **Hesap Güvenliği (2FA) paneli** (`/`, `/sube` VE `/admin` — her rol için):
  `templates/partials/account-security.php`, hem `templates/zone.php` hem
  `templates/parent-dashboard.php` tarafından include edilen tek bir
  paylaşılan partial (temanın ilk `include` deseni — her rol kendi 2FA'sını
  aynı şekilde yönettiğinden markup'ı iki template'te kopyalamaya gerek
  yoktu) + `assets/js/account-security.js`, `seviye/v1/security/2fa/*`'a
  bağlı. Üç durum: kapalı → kurulum (anahtar/otpauth URI gösterilir, kod
  ister) → açık (devre dışı bırakmak için şifre ister). Hiçbir yetki
  kontrolüne bağlı değildir — yalnızca oturum açık olması yeterlidir.
- **IP Kısıtlaması ayarı** (yalnızca `/admin`): `templates/zone.php`'ye
  eklenen bölüm + `assets/js/ip-allowlist-panel.js`,
  `seviye/v1/security/ip-allowlist`'e bağlı. Yalnızca
  `scp_manage_security_settings` (Genel Merkez) yetkisi olanlara görünür;
  her satıra bir IP/CIDR olacak şekilde düz bir metin alanı — sunucu
  tarafındaki ayrıştırma/doğrulama mantığı tek bir yerde
  (`Seviye\Security\Routing\IpAllowlist`) yaşar.
- **Raporlar paneli** (`/sube` VE `/admin`): `templates/zone.php`'ye eklenen
  "Raporlar" bölümü + `assets/js/reports-panel.js`, `seviye/v1/reports/sales`'a
  bağlı. Cari bakiye paneliyle aynı HQ/kendi-şube ayrımı: `scp_view_reports`
  (Genel Merkez, Bölge Müdürü) bir şube filtresi (boş = tüm şubeler, seçenekler
  `GET /branches`'tan doldurulur) görür; `scp_view_own_reports` (Şube Müdürü)
  şube alanını hiç görmez, sunucu kendi şubesine sabitler. Ürün ID/kategori
  ID/tarih aralığı filtreleriyle "Getir" düğmesi raporu JSON olarak tablo
  içinde yükler; "CSV İndir"/"Excel İndir" düğmeleri ise tarayıcıyı doğrudan
  `GET /reports/sales?format=csv|xlsx&_wpnonce=...`'a yönlendirir — gerçek bir
  dosya indirmesi `fetch()` üzerinden sürülemediğinden, REST nonce'ı
  `X-WP-Nonce` başlığı yerine bir sorgu parametresi olarak taşınır (bkz.
  `docs/ARCHITECTURE.md` bölüm 17).
- **Panel-içi bildirim çanı** (`header.php` - HER kimliği doğrulanmış
  sayfada, WooCommerce mağaza/ürün sayfaları dahil, tek istisnasız bileşen):
  `assets/js/notifications-bell.js`, `seviye/v1/notifications/mine/*`'a
  bağlı. `templates/zone.php`'deki hiçbir bölümün aksine bu bileşen
  `header.php`'de yaşar - panelin kendisi gibi tek bir sayfada değil, her
  yerde görünmesi gerektiğinden. Zil bir okunmamış sayısı rozeti gösterir;
  tıklanınca son bildirimleri açılır bir panelde listeler, her bildirime
  tıklamak onu okundu olarak işaretler (`POST /notifications/mine/{id}/read`,
  sunucu tarafında çağıran kullanıcıya sabitlenir).
- **SMS ayarları (NetGSM) formu** (yalnızca `/admin`): `templates/zone.php`'ye
  eklenen bölüm + `assets/js/notifications-settings-panel.js`,
  `seviye/v1/notifications/sms-settings`'e bağlı. Yalnızca
  `scp_manage_notification_settings` (Genel Merkez) yetkisi olanlara
  görünür. Şifre alanı sunucudan asla geri dönmez (yalnızca kullanıcı
  kodu/başlık); boş bırakılan bir şifre mevcut şifreyi değiştirmez -
  platformun üçüncü taraf bir kimlik bilgisi sakladığı ilk form.
- **API Anahtarları paneli** (yalnızca `/admin`): `templates/zone.php`'ye
  eklenen bölüm + `assets/js/api-keys-panel.js`, `seviye/v1/api-keys`'e
  bağlı. Yalnızca `scp_manage_api_keys` (Genel Merkez) yetkisi olanlara
  görünür; platform genelindeki HER anahtarı listeler (2FA/Bildirimler'in
  "yalnızca kendi kaynağın" desenini izlemez — bir anahtarın sahibi
  genellikle anahtarı oluşturan Genel Merkez kullanıcısı değil, belirli
  bir entegrasyon için ayrı bir hesaptır). Yeni bir anahtar oluşturulduğunda
  düz değeri yalnızca `POST` yanıtından, bir kez gösterilir — sayfa
  yenilendiğinde veya panel kapatıldığında bir daha asla görüntülenemez.
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

- **Genel görsel tasarım geçişi**: Site başlığı sabitlenmiş (sticky) hale
  getirildi ve marka rozeti eklendi; marka bağlantısı
  `inc/zones.php::scp_current_user_landing_path()` ile kullanıcının kendi
  rolüne ait bölgeye götürür (`Seviye\Security\Routing\RoleRouter`'ın aynı
  politikasını tekrar kullanır, tekrar uygulamaz) — böylece WooCommerce
  mağaza/ürün sayfası gibi bölge dışı ekranlardan da panele dönüş mümkün
  olur. `templates/zone.php`'nin üstüne, o sayfada gerçekten render edilen
  bölümlerle (aynı yetki kontrolleri) birebir eşleşen bir kısayol
  navigasyonu (`.scp-quicknav`) eklendi — birden fazla bölüm olmadıkça
  gösterilmez. Öğrenci/şube/fiyat kuralı tablolarındaki "Durum" sütunu artık
  düz metin yerine renkli bir rozet (`.scp-badge--active`/`--inactive`)
  olarak render ediliyor. Tablolar küçük ekranlarda yatay kaydırılabilir
  sarmalayıcıya (`.scp-table-wrapper`) alındı, kartlara ince bir gölge ve
  hover durumu, form alanlarına odak (focus) durumu, düğmelere hover/active
  durumu eklendi; 640px altı için duyarlı (responsive) ayarlamalar yapıldı.
  Bunların hiçbiri yeni bir REST uç noktası veya iş mantığı eklemez — salt
  var olan verinin sunumunu iyileştirir.

Kapsam dışı (henüz kurulmadı, ilgili modüller geldiğinde eklenecek):

- İade akışı (Seviye Finance'ın işi).

Tema, diğer tüm bileşenler gibi yalnızca Seviye Core'un (ve ilgili modüllerin)
yayınladığı public API'ler / REST uç noktaları üzerinden veri okur; doğrudan
`scp_*` tablolarını sorgulamaz.

**Not**: Bu tema headless bir oturumda (canlı WordPress/MySQL olmadan)
geliştirildi; PHP syntax ve statik kod standardı kontrolleri geçti, ancak
gerçek bir tarayıcıda görsel olarak doğrulanmadı. Kuruluma dair adımlar için
kök `README.md`'ye bakın.
