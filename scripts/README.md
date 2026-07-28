# Scripts

Geliştirme/CI yardımcı script'leri için ayrılmış klasör. Şu an için CI,
her plugin'in kendi `composer test` / `composer lint` script'lerini doğrudan
çalıştırıyor (bkz. `.github/workflows/ci.yml`), bu yüzden burada henüz bir
script yok.

Planlanan adaylar (ilk gerçek ihtiyaç doğduğunda eklenecek):

- Yeni bir modül plugin'i için standart klasör/dosya iskeletini üreten bir
  scaffold script'i (`make-module.sh`).
- `wp-env` tabanlı entegrasyon test ortamını ayağa kaldıran bir script
  (bkz. `tests/README.md`).
