# Assets

Bu klasör, birden fazla plugin/temayı ilgilendiren **paylaşılan** tasarım
kaynaklarını barındırmak içindir (örn. ortak SCSS design token'ları - renk,
tipografi, spacing ölçeği; ortak JS yardımcıları). Plugin'e veya temaya özel
varlıklar kendi `assets/` alt klasörlerinde kalır.

Henüz tema/panel UI çalışması başlamadığı için bu klasör şu an boş. İlk
içerik, `store.seviye.com.tr` giriş ekranı ve panel temaları geliştirilirken
eklenecek:

- `assets/scss/_tokens.scss` — renk paleti, tipografi, spacing ölçeği
- `assets/js/` — panel/tema genelinde paylaşılan JS yardımcı fonksiyonlar

Bkz. `docs/ROADMAP.md`.
