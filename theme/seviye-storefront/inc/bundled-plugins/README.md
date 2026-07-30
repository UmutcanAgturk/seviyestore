# Bundled plugins

This directory holds the 11 Seviye plugin zips `inc/plugin-installer.php`
installs from during the "Seviye Kurulum" wizard - a packaging-time build
artifact, gitignored (`*.zip`) the same way `/plugin/*/vendor/` is, and
rebuilt fresh for each delivered theme package rather than committed.

To rebuild them from the monorepo's `plugin/` sources:

```bash
for slug in seviye-core seviye-security seviye-branches seviye-students \
            seviye-parents seviye-pricing seviye-commerce seviye-finance \
            seviye-reports seviye-notifications seviye-api; do
  rm -rf "/tmp/$slug"
  cp -a "plugin/$slug" "/tmp/$slug"
  rm -rf "/tmp/$slug/vendor" "/tmp/$slug/.git" "/tmp/$slug/tests"
  (cd "/tmp/$slug" && composer install --no-dev --optimize-autoloader)
  (cd /tmp && zip -rq "seviyestore-repo-root/theme/seviye-storefront/inc/bundled-plugins/$slug.zip" "$slug")
done
```

Each zip's top-level folder name must stay exactly `<slug>` (matching
`scp_setup_steps()` in `plugin-installer.php`) so it lands at
`wp-content/plugins/<slug>/` when installed.
