# Règle — Architecture & arborescence

## Arborescence cible

```
billing-abby-for-woocommerce/
├── billing-abby-for-woocommerce.php    # entrée : header, garde ABSPATH, autoloader, register()
├── uninstall.php                       # nettoyage options/scheduled actions
├── composer.json                       # PSR-4 + outillage dev (WPCS, PHPUnit) — pas de dépendance runtime
├── readme.txt                          # format WP.org (≠ README.md)
├── src/
│   ├── Autoloader.php                  # autoloader PSR-4 maison (Rankea\BillingAbby\ → src/)
│   ├── Bootstrap.php                   # final class : HPOS, garde WC, démarrage
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
- Le fichier d'entrée reste minimal : charger l'autoloader (`require src/Autoloader.php` +
  `Autoloader::register()`) puis déléguer à `Bootstrap`. Aucun symbole global.
- Vérifier WooCommerce avant init ; sinon admin notice + arrêt propre (dans `Bootstrap`).

### Autoload — convention WP.org

- **Composer en dev** (PSR-4, WPCS, PHPUnit) ; à l'exécution, **autoloader maison** (`src/Autoloader.php`).
- Tant qu'il n'y a **aucune dépendance runtime**, ne pas livrer `vendor/` dans le build SVN
  (`.distignore` l'exclut) : embarquer l'autoloader Composer pour ne charger que nos classes est
  du bundling inutile que Plugin Check/les relecteurs pointent.
- Le jour où une vraie lib runtime est ajoutée : basculer sur `composer install --no-dev
  --optimize-autoloader` et inclure `vendor/` dans le build.

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
