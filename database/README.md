# Veritabanı

Bu klasör, `scp_*` özel tablolarının insan-okunabilir referansını ve
adlandırma/tasarım kurallarını içerir. **Kaynak-doğruluk (source of truth)
migration sınıflarıdır** (`plugin/*/src/Database/Migrations/*.php`); buradaki
`.sql` dosyaları yalnızca dokümantasyon amaçlıdır ve doğrudan çalıştırılmaz.

## Adlandırma kuralları

- Tablo adı: `{$wpdb->prefix}scp_{çoğul_varlık}` (ör. `wp_scp_students`).
  Bu, tek bir yerde merkezileştirilmiştir: `ConnectionInterface::table()`.
- Sütun adları: `snake_case`, İngilizce (ör. `branch_id`, `created_at`).
- Her tablo bir `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` içerir.
- Zaman damgaları: `created_at` / `updated_at` (`DATETIME`, `current_time('mysql')`
  ile UTC değil WordPress site saatiyle yazılır — WP'nin kendi konvansiyonuyla
  tutarlı).
- Foreign key'ler: `scp_*` tabloları arasında **gerçek InnoDB FK kısıtlamaları**
  kullanılır (WordPress çekirdek tablolarının aksine — bu platform bir
  ERP/CRM'dir, referans bütünlüğü uygulama katmanına bırakılmaz).

## Migration sistemi

1. Yeni bir tablo/değişiklik gerektiğinde, ilgili modülün
   `src/Database/Migrations/` altına `MigrationInterface` implemente eden bir
   sınıf eklenir. Versiyon formatı: `YYYY_MM_DD_NNNNNN` (aynı gün birden
   fazla migration için artan sayaç).
2. Modül, kendi aktivasyon hook'unda Core'un container'ından
   `MigrationRunner`'ı çözümler ve migration'ı `register()` eder, ardından
   `run()` çağırır:

   ```php
   $runner = \Seviye\Core\Plugin::instance()->container()->get(MigrationRunner::class);
   $runner->register(new CreateStudentsTable());
   $runner->run();
   ```

3. `MigrationRunner`, `scp_migrations` tablosunda hangi versiyonların
   çalıştığını takip eder; `run()` tekrar tekrar çağrılsa bile yalnızca
   bekleyen migration'ları uygular.
4. `dbDelta()` idempotenttir; `CREATE TABLE IF NOT EXISTS` yerine WordPress'in
   önerdiği `dbDelta` formatı kullanılır (bkz. mevcut migration örnekleri).

## Şu ana kadar tanımlı tablolar

| Tablo | Plugin | Migration | Amaç |
|---|---|---|---|
| `scp_migrations` | Core | `MigrationRunner::ensureMigrationsTableExists()` (dahili) | Uygulanan migration versiyonlarını takip eder |
| `scp_logs` | Core | `CreateLogsTable` | Merkezi audit log (KVKK/güvenlik) |
| `scp_settings` | Core | `CreateSettingsTable` | Platform genelinde anahtar/değer ayar deposu |
| `scp_user_identities` | Security | `CreateUserIdentitiesTable` | TC Kimlik No → WordPress kullanıcı eşlemesi (indeksli, `wp_usermeta` yerine) |
| `scp_password_tokens` | Security | `CreatePasswordTokensTable` | Tek kullanımlık, hash'lenmiş şifre/ilk-kurulum token'ları |
| `scp_branches` | Branches | `CreateBranchesTable` | Şube varlığı (IBAN, komisyon, telefon, adres, durum) |
| `scp_branch_users` | Branches | `CreateBranchUsersTable` | Şube "Yetkilileri" — hangi WP kullanıcısının hangi şubeye atandığı |
| `scp_students` | Students | `CreateStudentsTable` | Öğrenci varlığı (şube, eğitim yılı, sınıf, durum) |
| `scp_student_parents` | Students | `CreateStudentParentsTable` | Öğrenci ↔ veli (WP kullanıcı) çoktan-çoğa ilişkisi |
| `scp_parent_profiles` | Parents | `CreateParentProfilesTable` | Veli'ye özgü profil (telefon, bildirim tercihi, KVKK onay zaman damgası) |
| `scp_price_rules` | Pricing | `CreatePriceRulesTable` | Öğrenci/şube/genel kapsamlı özel fiyat kuralları (öncelik: öğrenci > şube > genel) |
| `scp_order_line_items` | Commerce | `CreateOrderLineItemsTable` | Sipariş kalemi başına öğrenci/şube/komisyon oranı/fiyat anlık görüntüsü (hakediş hesaplaması için), WC sipariş durumuyla senkron |
| `scp_hakedis_entries` | Finance | `CreateHakedisEntriesTable` | Değişmez, yalnızca-ekleme hakediş defteri — Commerce'in event'lerinden üretilen işaretli (EARNED pozitif, REVERSED negatif) kayıtlar |
| `scp_notifications` | Notifications | `CreateNotificationsTable` | E-posta/SMS/panel-içi bildirim günlüğü — durumu (`status`, `sent_at`, `read_at`) yerinde güncellenen tek istisna tablo, bkz. `docs/ARCHITECTURE.md` bölüm 18 |
| `scp_api_keys` | API | `CreateApiKeysTable` | API anahtarı kimlik doğrulaması — yalnızca SHA-256 özeti saklanır, düz metin asla; `revoked_at` yumuşak silme (denetim izi), bkz. `docs/ARCHITECTURE.md` bölüm 19 |

WooCommerce hâlâ sepet/sipariş verisinin sahibi — `scp_order_line_items`
onun yerini almaz, yalnızca hakediş hesaplaması için gereken bilgiyi
sipariş anında bir anlık görüntü olarak saklar (bkz. aşağıdaki FK
paragrafı). `scp_hakedis_entries` de benzer şekilde WooCommerce'in
sipariş/ödeme verisini tekrar etmez — yalnızca Commerce'in event'lerinden
türetilen muhasebe kayıtlarını tutar. Tahsilat/ödeme takibi için
gerekecek ek tablolar Finance'ın sonraki bölümünde eklenecek — bkz.
`docs/ROADMAP.md`.

`scp_user_identities`, `scp_password_tokens` ve `scp_parent_profiles`,
`wp_users`'a **kasıtlı olarak FK kısıtlaması içermez**: WordPress çekirdek
tabloları için motor/charset garantisi yoktur, bu yüzden referans
bütünlüğü uygulama katmanında sağlanır. Aynı sebeple
`scp_student_parents.parent_user_id` de `wp_users`'a FK içermez.

`scp_branch_users.branch_id → scp_branches.id`, `scp_students.branch_id →
scp_branches.id`, `scp_student_parents.student_id → scp_students.id`,
`scp_price_rules.student_id → scp_students.id` / `scp_price_rules.branch_id
→ scp_branches.id`, `scp_order_line_items.student_id → scp_students.id` /
`scp_order_line_items.branch_id → scp_branches.id` ve
`scp_hakedis_entries.student_id → scp_students.id` /
`scp_hakedis_entries.branch_id → scp_branches.id` ise **gerçek InnoDB FK
kısıtlamaları** ile bağlıdır — her seferinde bizim kendi tablolarımız
olduğu için WordPress-çekirdek-tablosu riski yok. `dbDelta()`
`FOREIGN KEY` cümlelerini güvenilir şekilde ayrıştırmadığından, kısıtlama
`dbDelta()`'dan sonra ayrı, idempotent bir `ALTER TABLE` adımıyla eklenir —
artık paylaşılan bir Core yardımcısı olarak:
`Seviye\Core\Database\ForeignKeyInstaller::ensure()`. Yeni bir modül-arası
FK eklerken bunu kullanın, `ensureForeignKey()`'i kendi migration'ınıza
kopyalamayın.

`scp_students.branch_id`, `scp_order_line_items`'ın ve
`scp_hakedis_entries`'in her iki FK'sı üzerinde **kasıtlı olarak
`ON DELETE CASCADE` kullanılmaz** (varsayılan `RESTRICT` uygulanır):
ilki bir şube silindiğinde tüm öğrencilerinin sessizce silinmesini,
diğer ikisi bir öğrenci/şube silindiğinde sipariş/finansal geçmişin
(hakediş kayıtlarının) sessizce kaybolmasını önler — bu platformun
tasarım gereği kaçındığı türden geri döndürülemez veri kayıpları.
`scp_student_parents.student_id` ve `scp_price_rules`'un her iki FK'sı
ise `ON DELETE CASCADE` kullanır — bir öğrenci/şube silindiğinde ona
bağlı tek bir veli-bağlantısının veya fiyat kuralının da silinmesi
beklenen, güvenli, düşük-hacimli bir temizliktir (bir şubenin *tüm
öğrencilerinin* silinmesiyle veya finansal geçmişin kaybolmasıyla aynı
büyüklükte bir risk değil). `scp_price_rules.product_id` ve
`scp_order_line_items.order_id`/`.order_item_id`'nin FK'sı yoktur — WC'nin
kendi ürün/sipariş tablolarına işaret ederler ve bu platform hiçbir zaman
WordPress/WooCommerce çekirdek tablolarına FK koymaz.

Diğer tüm tablolar (`scp_stock`, `scp_shipments`, `scp_campaigns`, ...)
ilgili modül geliştirildiğinde, o modülün kendi migration'ları olarak
eklenecek — bkz. `docs/ROADMAP.md`.

Referans DDL: [`schema/core.sql`](schema/core.sql), [`schema/security.sql`](schema/security.sql), [`schema/branches.sql`](schema/branches.sql), [`schema/students.sql`](schema/students.sql), [`schema/parents.sql`](schema/parents.sql), [`schema/pricing.sql`](schema/pricing.sql), [`schema/commerce.sql`](schema/commerce.sql), [`schema/finance.sql`](schema/finance.sql), [`schema/notifications.sql`](schema/notifications.sql), [`schema/api.sql`](schema/api.sql).
