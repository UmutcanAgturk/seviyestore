# Bundled plugins

This directory holds the 14 Seviye plugin zips `inc/plugin-installer.php`
installs from during the "Seviye Kurulum" wizard - a packaging-time build
artifact, gitignored (`*.zip`) the same way `/plugin/*/vendor/` is, and
rebuilt fresh for each delivered theme package rather than committed.

To rebuild them from the monorepo's `plugin/` sources, for each plugin:

1. Copy `plugin/<slug>` to a scratch dir, excluding `vendor/`, `.git/`,
   `tests/`.
2. Run `composer install --no-dev --optimize-autoloader` inside the copy.
3. For every symlink under `vendor/seviye/*` (composer's path-repo
   dependencies - e.g. Reports' copy of Branches/Students/Pricing/Commerce/
   Core), replace it with a real dereferenced copy and delete that copy's
   OWN nested `vendor/`/`.git/`/`tests/` - composer's generated
   `autoload_psr4.php` references each dependency's `src/` directly, never
   through its own `vendor/autoload.php`, so a dependency's nested vendor/
   is dead weight once dereferenced (confirmed by inspection: skipping this
   step bloated `seviye-reports.zip` from ~700KB to 18MB with copies that
   are never actually loaded).
4. `zip -rq <slug>.zip <slug>` from the scratch dir.

Each zip's top-level folder name must stay exactly `<slug>` (matching
`scp_setup_steps()` in `plugin-installer.php`) so it lands at
`wp-content/plugins/<slug>/` when installed.
