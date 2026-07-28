# Tests

Her plugin kendi birim testlerini kendi `tests/` klasöründe barındırır (ör.
`plugin/seviye-core/tests/`) ve WordPress'e bağımlı olmayan iş mantığını
PHPUnit ile doğrudan test eder — bkz. `docs/ARCHITECTURE.md` → "Test
stratejisi".

Bu kök `/tests` klasörü, birden fazla plugin'i birlikte tetikleyen gelecekteki
**entegrasyon/e2e testleri** için ayrılmıştır (ör. `wp-env` üzerinde gerçek
WordPress + MySQL ile Core + Students + Commerce'in birlikte çalıştığı
senaryolar). Bu milestone'da henüz kapsam dışıdır; ilk gerçek modül-arası
entegrasyon ihtiyacı doğduğunda kurulacaktır.

Çalıştırmak için (her plugin dizininde):

```bash
cd plugin/seviye-core
composer install
composer test
```
