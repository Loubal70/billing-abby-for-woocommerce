=== Billing Abby for WooCommerce ===
Contributors: rankea
Tags: woocommerce, invoicing, abby, accounting, billing
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.2
Requires Plugins: woocommerce
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync your WooCommerce orders to Abby invoicing directly from your own server. Native connector, no third-party cloud or subscription.

== Description ==

Billing Abby for WooCommerce is a native connector that sends your WooCommerce orders to Abby (invoicing and accounting for freelancers, https://abby.fr) through the Abby API.

Unlike automation platforms such as Make or Zapier, your order data goes **directly from your own server to the Abby API**. No third-party cloud relays your customers' information.

**Not affiliated with Abby or Automattic.** "WooCommerce" and "Abby" are mentioned only to describe compatibility.

= Key principles =

* Orders are turned into **draft** invoices in Abby by default (never auto-finalized).
* API calls run **asynchronously** (via Action Scheduler), never during checkout or page load.
* Full compatibility with WooCommerce **High-Performance Order Storage (HPOS)**.

= Requirements =

* WooCommerce (active).
* An Abby account on a **paid plan (Pro or higher)**, which is required to access the Abby API.

== External services ==

This plugin connects to the **Abby API** (https://abby.fr) to create and manage invoicing documents that correspond to your WooCommerce orders. It is the only external service this plugin communicates with, and no other third party receives your data.

When an order reaches the configured status, the plugin sends the data needed to create the matching invoicing document, which may include: customer details (name, email, billing/shipping address), order line items (products, quantities, prices), shipping costs, tax amounts, and order metadata.

This data is sent only to Abby, and only when an order triggers a synchronization. Your Abby API key is used to authenticate these requests.

* Service provider: Abby — https://abby.fr
* Terms of service: https://abby.fr/cgu
* Privacy policy: https://abby.fr/politique-de-confidentialite

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/billing-abby-for-woocommerce` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Make sure WooCommerce is installed and active.
4. Enter your Abby API key in the plugin settings.

== Frequently Asked Questions ==

= Do I need a paid Abby plan? =

Yes. Access to the Abby API is available on Abby's paid plans (Pro or higher).

= Is my data sent to any third-party cloud? =

No. Order data goes directly from your server to the Abby API. No Make, Zapier, or other relay is involved.

= Is this plugin affiliated with Abby or WooCommerce? =

No. It is an independent connector developed by Rankea. The names "Abby" and "WooCommerce" are used only to describe compatibility.

== Changelog ==

= 0.1.0 =
* Sync paid WooCommerce orders to Abby as draft invoices, sent directly to the Abby API.
* Settings panel to store and validate the Abby API key and pick the default income category.
* Line mapping with VAT, a dedicated shipping line, and coupon discounts; amounts match the order to the cent.
* Records paid orders in the Abby income book, with a per-product income category override.
* Order-screen panel: Abby sync status, manual retry, and invoice PDF download.
* Error log with manual retry; idempotent sync (no duplicate invoices).
* First-run setup wizard. HPOS compatible.
