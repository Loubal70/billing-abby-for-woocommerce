# Billing Abby for WooCommerce

[![Testing](https://github.com/Loubal70/billing-abby-for-woocommerce/actions/workflows/testing.yml/badge.svg)](https://github.com/Loubal70/billing-abby-for-woocommerce/actions/workflows/testing.yml)

A native WordPress connector that syncs **WooCommerce** orders to **Abby** invoicing
([abby.fr](https://abby.fr)) through the Abby API — directly from your own server, with
no Make/Zapier and no third-party cloud.

> **Not affiliated with Abby or Automattic.** "WooCommerce" and "Abby" are used only to
> describe compatibility.

## Why

Order data goes straight from the merchant's server to the Abby API. Nothing transits through
a third-party relay — that is the differentiator versus automation platforms and SaaS
connectors.

## Principles

- WooCommerce orders become **draft** invoices in Abby by default (never auto-finalized).
- Abby API calls run **asynchronously** (Action Scheduler), never during checkout or page load.
- Compatible with WooCommerce **High-Performance Order Storage (HPOS)**.
- English is the source locale; French ships as a translation in `languages/`.

## Requirements

- WordPress 6.4+
- PHP 8.2+
- WooCommerce (active)
- An Abby account on a paid plan (Pro or higher) for API access

## Status

Phase 0 (foundations) is in place. See [`ROADMAP.md`](ROADMAP.md) for the plan.

## Development

The project is developed under [DDEV](https://ddev.com/).

```bash
ddev exec -d /var/www/html/wp-content/plugins/billing-abby-for-woocommerce composer install

# Coding standards (WPCS + PHPCompatibility 8.2-)
ddev exec -d /var/www/html/wp-content/plugins/billing-abby-for-woocommerce composer phpcs

# Unit tests (requires bin/install-wp-tests.sh, run in CI)
ddev exec -d /var/www/html/wp-content/plugins/billing-abby-for-woocommerce composer test

# Plugin Check on the distributable build (mirrors .distignore)
ddev exec -d /var/www/html/wp-content/plugins/billing-abby-for-woocommerce composer plugin-check
```

`composer plugin-check` is the source of truth for WordPress.org compliance; the bare
`wp plugin check` scans dev/tooling files that are excluded from the distributed build.

## Distribution

The WordPress.org plugin page is generated from [`readme.txt`](readme.txt) (not this file,
which is excluded from the build via `.distignore`).

## Contributing

Commits follow [Conventional Commits](https://www.conventionalcommits.org/) and SemVer. Keep
each commit Plugin Check clean. All source strings stay in English.

## License

[GPL-2.0-or-later](LICENSE) — Copyright Rankea ([rankea.agency](https://rankea.agency)).
