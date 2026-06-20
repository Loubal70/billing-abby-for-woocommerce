# Rule — Abby API integration

> ⚠️ **Guardrail**: exact endpoint names, fields and formats to be confirmed on
> **https://docs.abby.fr** (and the Make module, which mirrors the available actions). Never
> invent a route: otherwise `// TODO: confirm endpoint on docs.abby.fr` and ask before implementing.

## Authentication

- API via an **API key** generated from the Abby account.
- API access restricted to **paid plans (Pro and up)**: state it in the readme, distinguish
  in the UI "invalid key" vs "insufficient plan".
- Validate the key on save (lightweight call). Store as an option, masked.

## Data model (to map)

WooCommerce order → Abby **billing document**:

- **Contact / organization**: the customer. Match by email/identifier to **avoid
  duplicates** (friction #1 on the forum).
- **Document**: invoice (or quote depending on the setting), created as a **draft** by default.
- **Lines**: products + quantities + prices, **+ a shipping line** with its VAT rate.
- **Credit note**: for a WooCommerce refund (partial/full).
- **Income book**: fed when the document is **marked as paid**.

## Document language (Abby is multilingual)

- Distinct from the plugin i18n: here it is the language of the **invoice sent to the customer**.
- Detect the order/customer language in this priority order:
  1. order Polylang language (`pll_get_post_language` / order meta),
  2. WPML language (order `wpml_language` meta),
  3. WooCommerce customer locale (`get_user_locale` / order `_wp_locale`),
  4. fallback: the Abby account's default language.
- Pass this language to Abby **if the API exposes a language/locale field** on the document
  or the contact — `// TODO: confirm the field on docs.abby.fr`.
- Goal: do not invoice an English-speaking customer in French (or the reverse). Many
  target shops are multilingual (Polylang/WPML).

## Reference flow

1. Trigger status → enqueue async (Action Scheduler).
2. Match/create the contact (idempotent).
3. `InvoiceMapper`: products + shipping + VAT + **detected language**.
4. Create the **draft invoice**; store the ID in `_bafw_abby_invoice_id`.
5. (Merchant option) mark paid → income book.
6. WC refund → credit note.

## Business rules

- **Draft by default, always.** Auto-finalization only if the merchant chose it
  (a finalized invoice can no longer be deleted: it requires a credit note). Accounting guardrail.
- **VAT**: handle the **VAT-exempt regime (franchise en base)** (amounts without VAT, proper mentions); cover with tests.
- **Idempotency**: never two invoices for one order (check the meta before any call).
- **Errors/rate limit**: retries + backoff via Action Scheduler; log the failure (without the
  API key); manual retry from the order screen.

## Single external recipient

No data passes through any third-party server (neither Rankea nor cloud): the only external
recipient = the Abby API, declared in the readme. This is the differentiating promise vs Make/Order Invoicer.
