# Règle — Architecture & arborescence

## Arborescence cible

```
billing-abby-for-woocommerce/
├── billing-abby-for-woocommerce.php    # bootstrap : header, garde ABSPATH, check WC, init
├── uninstall.php                       # nettoyage options/scheduled actions
├── composer.json                       # autoload PSR-4 Rankea\BillingAbby\
├── readme.txt                          # format WP.org (≠ README.md)
├── src/
│   ├── Plugin.php                      # singleton, enregistre les hooks
│   ├── Admin/Settings.php              # onglet réglages WooCommerce + clé API
│   ├── Abby/Client.php                 # client HTTP (wp_remote_*), auth, erreurs
│   ├── Abby/InvoiceMapper.php          # commande WC → payload document Abby
│   ├── Sync/OrderSync.php              # hooks commande → enqueue async
│   └── Sync/Webhooks.php               # réception + vérif signature (si utilisé)
├── languages/                          # en_US source + fr_FR
└── tests/
```

## Bootstrap (fichier principal)

- `defined('ABSPATH') || exit;` en tête.
- En-tête complet (voir CLAUDE.md), dont `Requires Plugins: woocommerce`, `Text Domain: billing-abby-for-woocommerce`.
- Vérifier WooCommerce avant init ; sinon admin notice + arrêt propre.
- Charger l'autoload Composer puis `Plugin::instance()`.

## Compatibilité HPOS (obligatoire)

```php
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables', __FILE__, true
        );
    }
} );
```

Ne jamais lire/écrire une commande via post meta en dur : `wc_get_order()`, méthodes de
`WC_Order`, `OrderUtil` pour les tests de contexte.

## Stratégie de hooks

- Déclencheur sur statut : `woocommerce_order_status_completed` (configurable) ; sinon
  `woocommerce_order_status_changed`.
- **Ne pas** appeler Abby dans le hook : enfiler une action async (`as_enqueue_async_action()`)
  avec l'`order_id`.
- **Idempotence** : méta `_bafw_abby_invoice_id` sur la commande ; si présent, ne pas recréer
  (indispensable pour retries + webhooks).

## Secrets

- Clé API Abby en option WordPress, lecture réservée aux capacités admin. Jamais exposée
  côté front, ni en URL, ni en log. Masquée dans l'UI.
- Aucune donnée client envoyée à un tiers autre qu'Abby (cœur de la promesse).

## Marques (rappel dur)

- Slug/dossier/fichier/text domain `billing-abby-for-woocommerce` — « billing » en tête
  (générique), jamais « woo »/« abby »/« wordpress » au début.
- « WooCommerce » / « Abby » seulement en mention de compatibilité, texte brut, pas de logo.
- Mention « Not affiliated with Abby or Automattic » (header + readme).
- Nom « Abby » dans le titre : à sécuriser par accord écrit Abby (ROADMAP P4).
