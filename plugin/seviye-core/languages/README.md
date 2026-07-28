# Languages

Translation files (`.pot`/`.po`/`.mo`) for the `seviye-core` text domain live here.

Generate the `.pot` template once there are translatable strings worth shipping:

```bash
wp i18n make-pot plugin/seviye-core plugin/seviye-core/languages/seviye-core.pot --domain=seviye-core
```

All user-facing strings in this plugin are wrapped in `__()` / `_e()` / `esc_html__()`
with the `seviye-core` text domain, per the project's i18n convention: interface text
is managed through translation files, not hard-coded Turkish or English strings.
