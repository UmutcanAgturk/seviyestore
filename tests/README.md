# Tests

Her plugin kendi birim testlerini kendi `tests/` klasöründe barındırır (ör.
`plugin/seviye-core/tests/`) ve WordPress'e bağımlı olmayan iş mantığını
PHPUnit ile doğrudan test eder — bkz. `docs/ARCHITECTURE.md` → "Test
stratejisi".

Çalıştırmak için (her plugin dizininde):

```bash
cd plugin/seviye-core
composer install
composer test
```

## Entegrasyon testleri (`tests/integration`)

Bu klasör, birden fazla plugin'i birlikte tetikleyen entegrasyon testlerini
barındırır — gerçek bir WordPress + MySQL üzerinde 12 eklentinin birlikte
gerçekten boot olduğunu, migration'ların gerçek tablo oluşturduğunu, bir REST
uç noktasının uçtan uca gerçekten yanıt verdiğini doğrular. Her plugin'in
kendi birim testleri WordPress'i tamamen taklit ettiğinden (fake
`ConnectionInterface`, WP fonksiyonu yok) bu katmanı YAPISAL olarak asla
kapsayamaz — gerçek bir çalışma zamanı gerektirir.

**Bu ortamda (Claude Code'un bu oturumu) çalıştırılamıyor**: `wp-env` bir
Docker container'ı gerektiriyor ve bu sandbox'ta Docker daemon'u çalışmıyor;
ayrıca `wordpress.org`'a proxy üzerinden erişim de engelli. Aşağıdaki adımlar
Docker + internet erişimi olan bir ortamda (yerel makine ya da GitHub
Actions) çalıştırılmak üzere hazırlandı ve doğrulanmayı bekliyor.

### Yerel makinede çalıştırma

Gerekenler: Docker, Node.js (`npx` için).

```bash
# Kök dizinde, her seferinde:
npx wp-env start

# Her plugin'in kendi vendor/'ı (autoloader) olmalı - wp-env bu dizinleri
# olduğu gibi mount ediyor, composer install'ı KENDİSİ ÇALIŞTIRMIYOR:
for d in plugin/*/; do (cd "$d" && composer install --no-interaction); done

# Kök composer.json'ın kendi dev bağımlılıkları (phpunit, yoast/phpunit-polyfills):
composer install

# Testleri çalıştır - wp-env'in kendi test ortamı konteynerinde:
npx wp-env run tests-cli --env-cwd=. -- vendor/bin/phpunit -c phpunit-integration.xml.dist
```

`.wp-env.json`, WooCommerce'i doğrudan wordpress.org'dan indirip kuruyor
(`plugins` dizisindeki ilk giriş) - tüm modüllerin tek gerçek dış
bağımlılığı. `tests/integration/bootstrap.php`, WP'nin kendi çekirdek test
paketini (`WP_TESTS_DIR`) yükleyip her Seviye eklentisini
`plugin-installer.php`'nin belgelediği bağımlılık sırasıyla (alfabetik değil)
`muplugins_loaded`'a manuel olarak require ediyor.

### CI'da çalıştırma

`.github/workflows/integration-tests.yml`, yalnızca elle tetiklenir
(`workflow_dispatch`) - `ci.yml`'nin push/PR'da otomatik çalışan
denetimlerinin AKSİNE, bu iskelet gerçek bir ortamda hiç doğrulanmadığından
her push'ta kırmızı bir kontrol olarak görünmesin diye bilinçli olarak
otomatik tetiklenmiyor. Yerel makinede yukarıdaki adımlarla bir kez
doğrulandıktan sonra `push`/`pull_request` tetikleyicilerine taşınabilir.

### Yeni bir entegrasyon testi eklemek

`tests/integration/Tests/` altına `*Test.php` uzantılı, `WP_UnitTestCase`'den
türeyen bir sınıf ekle - bkz. `PluginActivationTest.php` (tüm eklentilerin
aktif olduğunu, Core container'ının kullanılabilir olduğunu, ve birkaç
kritik `scp_*` tablosunun migration'dan sonra gerçekten var olduğunu
doğruluyor).
