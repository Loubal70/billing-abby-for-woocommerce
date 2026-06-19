# CLAUDE.md — Billing Abby for WooCommerce

> Project memory for Claude Code. Read on every session (CLI + Desktop). Keep it short
> (< 200 lines); the detail lives in `@.claude/rules/*`.

## The project in one sentence

A **self-hosted** WordPress plugin that syncs **WooCommerce** orders to
**Abby** (invoicing/accounting for freelancers, https://abby.fr) through the Abby API,
**without Make/Zapier or any third-party cloud** — data goes straight from the merchant's
server to the Abby API.

Author: **Rankea** (https://rankea.agency) · Product page: https://rankea.agency/tools/billing-abby-for-woocommerce

## Language (WordPress convention)

- **English by default (`en_US`)**: code, names, and every source string in English.
- **French** is a translation (`languages/`), not the default language.
- Consequence: the **slug is in English**. French SEO goes through `readme.txt`
  (short title, description, tags) and the `rankea.agency` landing page, not the slug.

## Non-negotiable facts

- **Slug / folder / main file / text domain**: `billing-abby-for-woocommerce`
  (identical — WP.org requirement for loading translations).
- **Prefix**: namespace `Rankea\BillingAbby\` (PSR-4); procedural `bafw_`. Nothing unprefixed.
- **Floor**: `Requires PHP: 8.2`, `Requires at least: 6.4`, `Requires Plugins: woocommerce`.
  Dev/test target: 8.2 (floor) + 8.4.
- **Trademarks**: starting a name/slug with "billing" is fine (generic) but
  never start with "Woo", "WooCommerce", "WordPress" or "Abby". These marks are used only for
  compatibility, in plain text, never as a logo. "Not affiliated with Abby or
  Automattic" mention in the header + readme. Use of the "Abby" name to be secured by written agreement.
- **WP.org compliance**: every commit stays "Plugin Check clean" (Plugin Repo
  + Security categories with no error, otherwise the submission is blocked).

## Stack & dependencies

- WooCommerce = hard dependency, **HPOS** enabled (compatibility mandatory).
- Composer + PSR-4 autoload. Action Scheduler (shipped by WooCommerce) for async work.
- WPCS via PHP_CodeSniffer; PHPCompatibility `testVersion 8.2-`. PHPUnit. CI: GitHub Actions.

## Commands (prefix with `ddev` locally)

```bash
composer install
./vendor/bin/phpcs
./vendor/bin/phpcbf
./vendor/bin/phpunit
# Plugin Check: target what actually ships (mirrors .distignore); must be green before each PR
wp plugin check billing-abby-for-woocommerce --categories=plugin_repo,security \
  --exclude-directories=tests,bin,.github,.claude \
  --exclude-files=.phpcs.xml.dist,.editorconfig,.gitignore,.distignore,phpunit.xml.dist,CLAUDE.md,ROADMAP.md,README.md
```

## Expected agent workflow

1. Read the relevant rule in `@.claude/rules/` before coding a feature.
2. WooCommerce order → **draft** invoice in Abby by default (never auto-finalized).
3. Abby API calls **always async** (Action Scheduler), never in checkout/page load.
4. Security: sanitize on input, escape on output, nonce + `current_user_can('manage_woocommerce')`,
   signature check on incoming webhooks.
5. `phpcs` + `wp plugin check` before proposing a commit. Conventional Commits + SemVer.

## DO NOT

- Log/store the Abby API key in clear text. Synchronous or repeated API calls. Invented endpoints
  (confirm on https://docs.abby.fr). Tracking without a declared opt-in. Lower the PHP floor
  or rename the slug/text domain.

## Imports

- @.claude/rules/architecture.md — file tree, classes, hooks, HPOS, secrets, trademarks
- @.claude/rules/php-wordpress.md — WP/Woo conventions, security, i18n (English by default), Plugin Check
- @.claude/rules/abby-integration.md — Abby API: auth, drafts, contacts, credit notes, income book, document language
- Product roadmap: `ROADMAP.md` (root)
