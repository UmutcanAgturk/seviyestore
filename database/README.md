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

`scp_user_identities` ve `scp_password_tokens`, `wp_users`'a **kasıtlı olarak
FK kısıtlaması içermez**: WordPress çekirdek tabloları için motor/charset
garantisi yoktur, bu yüzden referans bütünlüğü uygulama katmanında
(`WpdbIdentityGateway`, `WpdbPasswordTokenGateway`) sağlanır.

`scp_branch_users.branch_id` ise `scp_branches.id`'ye **gerçek bir InnoDB FK
kısıtlaması** ile bağlıdır (`ON DELETE CASCADE`) — ikisi de bizim kendi
tablolarımız olduğu için WordPress-çekirdek-tablosu riski yok. `dbDelta()`
`FOREIGN KEY` cümlelerini güvenilir şekilde ayrıştırmadığından, kısıtlama
`dbDelta()`'dan sonra ayrı, idempotent bir `ALTER TABLE` adımıyla eklenir —
bkz. `CreateBranchUsersTable::ensureForeignKey()`. Yeni bir modül-arası FK
eklerken bu deseni izleyin.

Diğer tüm tablolar (`scp_students`, `scp_parents`, `scp_prices`, `scp_orders`,
`scp_order_items`, `scp_commissions`, `scp_stock`, `scp_shipments`,
`scp_campaigns`, ...) ilgili modül geliştirildiğinde, o modülün kendi
migration'ları olarak eklenecek — bkz. `docs/ROADMAP.md`.

Referans DDL: [`schema/core.sql`](schema/core.sql), [`schema/security.sql`](schema/security.sql), [`schema/branches.sql`](schema/branches.sql).
