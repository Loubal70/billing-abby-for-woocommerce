# Rule — Architecture & file tree

## Target file tree

```
billing-abby-for-woocommerce/
├── billing-abby-for-woocommerce.php    # entry point: header, ABSPATH guard, autoloader, register()
├── uninstall.php                       # cleanup of options/scheduled actions
├── composer.json                       # PSR-4 + dev tooling (WPCS, PHPUnit) — no runtime dependency
├── readme.txt                          # WP.org format (≠ README.md)
├── src/
│   ├── Autoloader.php                  # home-grown PSR-4 autoloader (Rankea\BillingAbby\ → src/)
│   ├── Bootstrap.php                   # final class: HPOS, WC guard, startup
│   ├── Plugin.php                      # singleton, registers the hooks
│   ├── Admin/Settings.php              # WooCommerce settings tab + API key
│   ├── Abby/Client.php                 # HTTP client (wp_remote_*), auth, errors
│   ├── Abby/InvoiceMapper.php          # WC order → Abby document payload
│   ├── Sync/OrderSync.php              # order hooks → enqueue async
│   └── Sync/Webhooks.php               # reception + signature check (if used)
├── languages/                          # en_US source + fr_FR
└── tests/
```

## Bootstrap (main file)

- `defined('ABSPATH') || exit;` at the top.
- Full header (see CLAUDE.md), including `Requires Plugins: woocommerce`, `Text Domain: billing-abby-for-woocommerce`.
- The entry file stays minimal: load the autoloader (`require src/Autoloader.php` +
  `Autoloader::register()`) then delegate to `Bootstrap`. No global symbol.
- Check WooCommerce before init; otherwise admin notice + clean stop (in `Bootstrap`).

### Autoload — WP.org convention

- **Composer in dev** (PSR-4, WPCS, PHPUnit); at runtime, **home-grown autoloader** (`src/Autoloader.php`).
- As long as there is **no runtime dependency**, do not ship `vendor/` in the SVN build
  (`.distignore` excludes it): bundling the Composer autoloader just to load our own classes is
  pointless bundling that Plugin Check/reviewers flag.
- The day a real runtime lib is added: switch to `composer install --no-dev
  --optimize-autoloader` and include `vendor/` in the build.

## HPOS compatibility (mandatory)

```php
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables', __FILE__, true
        );
    }
} );
```

Never read/write an order via raw post meta: `wc_get_order()`, `WC_Order` methods,
`OrderUtil` for context checks.

## Hook strategy

- Status trigger: `woocommerce_order_status_completed` (configurable); otherwise
  `woocommerce_order_status_changed`.
- **Do not** call Abby in the hook: enqueue an async action (`as_enqueue_async_action()`)
  with the `order_id`.
- **Idempotency**: `_bafw_abby_invoice_id` meta on the order; if present, do not recreate
  (essential for retries + webhooks).

## Secrets

- Abby API key as a WordPress option, read restricted to admin capabilities. Never exposed
  on the front end, in a URL, or in a log. Masked in the UI.
- No customer data sent to any third party other than Abby (core of the promise).

## Trademarks (hard reminder)

- Slug/folder/file/text domain `billing-abby-for-woocommerce` — "billing" leading
  (generic), never "woo"/"abby"/"wordpress" at the start.
- "WooCommerce" / "Abby" only as a compatibility mention, plain text, no logo.
- "Not affiliated with Abby or Automattic" mention (header + readme).
- "Abby" name in the title: to be secured by a written agreement with Abby (ROADMAP P4).
