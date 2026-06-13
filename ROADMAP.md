# ROADMAP — Billing Abby for WooCommerce

Priorities driven by (a) the real friction points raised on the Abby forum around the
WooCommerce integration, (b) the bar set by the SaaS competitor *Order Invoicer*, and (c) the
French e-invoicing reform (September 2026). Differentiator: **native plugin, no third-party
subscription, data that never transits through an external cloud**.

> **Status (2026-06-13):** Phase 0 done; Phase 1 (MVP) done; Phase 2 largely done (refunds and
> document language remain). Order → invoice/income amounts are verified to match the WooCommerce
> order to the cent (HT, TTC + VAT, percentage coupons).

## Phase 0 — Foundations (infra)

- [x] Scaffolding (`billing-abby-for-woocommerce`, `--ci=github`)
- [x] Full header: `Requires PHP: 8.2`, `Requires at least: 6.4`, `Requires Plugins: woocommerce`, GPL-2.0-or-later
- [x] Composer + PSR-4 autoload (`Rankea\BillingAbby\`)
- [x] WPCS + PHPCompatibility (`testVersion 8.2-`) + PHPUnit
- [x] GitHub Actions: `php: ['8.2', '8.4']` matrix, phpcs + phpunit + Plugin Check
- [x] HPOS compatibility declaration
- [x] WP.org-compliant `readme.txt` (external-service Abby section + non-affiliation)

## Phase 1 — MVP: order → draft invoice

- [x] Settings page (React panel): enter + validate the Abby API key
- [x] Abby API client (`wp_remote_*`), error handling
- [x] Order → document mapping: product lines, quantities, amounts (amount conformity verified)
- [x] Create / reconcile the customer contact in Abby (dedup by stored id, no email lookup)
- [x] Async trigger (Action Scheduler) — two-flow: draft per order on placement, mark paid on payment
- [x] Sync status on the order screen (status + manual retry + view-PDF meta box)
- [x] First-run setup wizard (onboarding) — *bonus, not in the original plan*

## Phase 2 — Robustness (known forum pitfalls)

- [x] **Detect an already-existing contact** (avoid duplicates)
- [x] **Shipping line** with the correct VAT rate
- [ ] **Refunds (partial/total) → Abby credit notes** *(not started)*
- [x] Option **"mark as paid"** → **income book** (livre de recettes)
- [~] **VAT franchise en base** (non-VAT-registered sole traders): the mapper handles franchise
      accounts (no VAT applied); see the planned VAT-declaration alert below
- [ ] **Document language**: detect the order language (Polylang / WPML / WooCommerce customer
      locale) and pass it to Abby — field to confirm on docs.abby.fr *(not started)*
- [x] Event log + manual retry; idempotency (no duplicate invoice on retry)
- [ ] Settings UI for the trigger status / require-paid options (currently option defaults only)

## Phase 3 — Compliance & 2026 reform

- [ ] **VAT-declaration alert**: ask Abby whether the shop must declare VAT, cache the result,
      and notify the merchant when it changes *(planned — see project memory)*
- [ ] Mandatory e-invoicing alignment (Abby = approved platform / Plateforme Agréée)
- [ ] Mandatory legal mentions on the documents
- [ ] Multi-status support (micro, EI, EURL…) per the Abby profile
- [ ] User documentation (prerequisite: API on an Abby Pro+ plan)

## Phase 4 — Distribution & acquisition

- [ ] Written Abby agreement on using the name
- [ ] WP.org submission (2FA, green Plugin Check, reserved slugs verified)
- [ ] SEO landing `rankea.agency/tools/...` (French — the slug being English)
- [ ] Freemium: free (MVP) + PRO (credit notes, multi-status, advanced VAT, multilingual)
- [ ] Honest FAQ / comparison vs Make and vs Order Invoicer

## Phase 5 — Sustainability

- [ ] Full i18n: `en_US` source + `fr_FR` shipped, other locales via translate.wordpress.org
- [~] Tests on VAT/amount mapping (risk area): proven live; PHPUnit cases still skipped (WC not
      loaded in the test harness)
- [ ] Strictly opt-in telemetry (if useful)
- [ ] Watch the Abby API + WooCommerce compatibility (HPOS, checkout blocks) at each release

---

### Out of scope (for now)

Two-way stock sync, multiple Abby accounts, other e-commerce platforms. To reconsider only if
traction justifies it (narrow market: the intersection of "uses Abby" × "runs WooCommerce" ×
"willing to install a plugin").
