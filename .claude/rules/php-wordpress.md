# Rule — PHP / WordPress / WooCommerce

## Code conventions

- **WPCS** via PHP_CodeSniffer; `phpcbf` for autofix.
- **PHPCompatibility** on `testVersion 8.2-` in `.phpcs.xml.dist`.
- PHP 8.2+ allowed (enums, readonly, unions, named args); do not go above 8.2 without a reason.
- Prefix everywhere: namespace `Rankea\BillingAbby\` (PSR-4); procedural `bafw_`.

## Security (blocking — Plugin Check Security category)

- **Sanitize** every input (`sanitize_text_field`, `sanitize_email`, `absint`…).
- **Escape** every output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- **Nonces** + `current_user_can( 'manage_woocommerce' )` on every admin action.
- HTTP via `wp_remote_get` / `wp_remote_post` (never raw cURL); check the HTTP code + `is_wp_error()`.
- `$wpdb->prepare` if direct SQL (to avoid — prefer the WC/WP API).
- Incoming webhooks: verify the signature/secret before processing, reject otherwise.
- Never `eval`, remote code execution, or base64 obfuscation.

## Internationalization — English by default

- **All source strings in English (`en_US`)**, via `__()`, `esc_html__()`… with the text
  domain `billing-abby-for-woocommerce` (= slug → auto-loaded by WP.org).
- No variable in a translatable string: `printf` + placeholders.
- Generate the `.pot` in `languages/`, ship `fr_FR.po/.mo`. French is a **translation**,
  not the source. Other locales come from translate.wordpress.org once on WP.org.
- ⚠️ Do not confuse with the **Abby document language** (invoice sent to the customer):
  see `@.claude/rules/abby-integration.md`.

## WP.org repository compliance (Plugin Repo category)

- Header version = `Stable tag` of `readme.txt`.
- `readme.txt` in WP.org format, with an **Abby external service** section: which data
  is sent, where, link to Abby's terms/policy (Guidelines requirement for any third-party service).
- GPL-2.0-or-later (`License` + `License URI`).
- No "phone home"/tracking without explicit opt-in + disclosure. Bundled assets.

## Performance

- No synchronous Abby API call (checkout/page load): Action Scheduler.
- No network request on `init`/`admin_init`. Lazy loading. Short cache on the read side.

## Before each PR

```bash
./vendor/bin/phpcs
./vendor/bin/phpunit
# Plugin Check on what actually ships (mirrors .distignore) -> green on Plugin Repo + Security
wp plugin check billing-abby-for-woocommerce --categories=plugin_repo,security \
  --exclude-directories=tests,bin,.github,.claude \
  --exclude-files=.phpcs.xml.dist,.editorconfig,.gitignore,.distignore,phpunit.xml.dist,CLAUDE.md,ROADMAP.md,README.md
```

> Without these exclusions, Plugin Check flags **dev** files (hidden configs, `tests/`,
> `bin/`, `.github/`, `.claude/`, `CLAUDE.md`, `ROADMAP.md`) that are **not** in the shipped zip.
> The actually distributed build is green (verifiable via a pruned copy + `--slug=...`).
