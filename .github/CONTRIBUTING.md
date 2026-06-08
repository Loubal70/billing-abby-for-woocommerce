# Contributing

Thanks for your interest in improving **Billing Abby for WooCommerce**. This document explains
how to set up the project and the conventions we follow.

## Development setup

The project is developed under [DDEV](https://ddev.com/). From the plugin directory:

```bash
composer install
```

(Prefix commands with `ddev exec -d /var/www/html/wp-content/plugins/billing-abby-for-woocommerce`
when running them from the host.)

## Before opening a pull request

All three must be green:

```bash
composer phpcs          # WordPress Coding Standards + PHPCompatibility 8.2-
composer test           # PHPUnit (requires bin/install-wp-tests.sh)
composer plugin-check   # WordPress.org Plugin Check on the distributable build
```

## Conventions

- **PHP 8.2+**, namespaced under `Rankea\BillingAbby\` (PSR-4). No unprefixed globals.
- **English source strings** only, via `__()`, `esc_html__()`, … with the text domain
  `billing-abby-for-woocommerce`. French ships as a translation in `languages/`.
- **Security**: sanitize input, escape output, use nonces and
  `current_user_can( 'manage_woocommerce' )` for admin actions, and `wp_remote_*` for HTTP.
- **Abby API**: never invent an endpoint or field — confirm it on https://docs.abby.fr first.
- **Async**: never call the Abby API during checkout or page load; enqueue via Action Scheduler.
- **HPOS**: read and write orders through the WooCommerce CRM API, never raw post meta.

## Commits and branches

- Branch from `main`; open a pull request back into `main`.
- Use [Conventional Commits](https://www.conventionalcommits.org/) and SemVer.
- Keep each commit Plugin Check clean.

## Reporting bugs and ideas

Use the issue templates. For security reports, follow [SECURITY.md](SECURITY.md) instead of
opening a public issue.
