# CLAUDE.md — Billing Abby for WooCommerce

> Mémoire projet pour Claude Code. Lue à chaque session (CLI + Desktop). Garder court
> (< 200 lignes) ; le détail vit dans `@.claude/rules/*`.

## Le projet en une phrase

Plugin WordPress **auto-hébergé** qui synchronise les commandes **WooCommerce** vers
**Abby** (facturation/compta pour indépendants, https://abby.fr) via l'API Abby,
**sans Make/Zapier ni cloud tiers** — les données vont directement du serveur du marchand
à l'API Abby.

Auteur : **Rankea** (https://rankea.agency) · Page produit : https://rankea.agency/tools/billing-abby-for-woocommerce

## Langue (convention WordPress)

- **Anglais par défaut (`en_US`)** : code, noms, et toutes les chaînes source en anglais.
- Le **français** est une traduction (`languages/`), pas la langue par défaut.
- Conséquence : le **slug est en anglais**. Le SEO français passe par le `readme.txt`
  (titre court, description, tags) et la landing `rankea.agency`, pas par le slug.

## Faits non négociables

- **Slug / dossier / fichier principal / text domain** : `billing-abby-for-woocommerce`
  (identiques — obligation WP.org pour le chargement des traductions).
- **Préfixe** : namespace `Rankea\BillingAbby\` (PSR-4) ; procédural `bafw_`. Rien sans préfixe.
- **Plancher** : `Requires PHP: 8.2`, `Requires at least: 6.4`, `Requires Plugins: woocommerce`.
  Cible de dev/test : 8.2 (plancher) + 8.4.
- **Marques** : ne jamais commencer un nom/slug par « billing » est OK (générique) mais
  jamais par « Woo », « WooCommerce », « WordPress » ou « Abby ». Ces marques ne servent qu'à
  la compatibilité, en texte brut, jamais en logo. Mention « Not affiliated with Abby or
  Automattic » dans le header + readme. Usage du nom « Abby » à sécuriser par accord écrit.
- **Conformité WP.org** : chaque commit reste « Plugin Check clean » (catégories Plugin Repo
  + Security sans erreur, sinon la soumission est bloquée).

## Stack & dépendances

- WooCommerce = dépendance dure, **HPOS** activé (compat obligatoire).
- Composer + autoload PSR-4. Action Scheduler (fourni par WooCommerce) pour l'asynchrone.
- WPCS via PHP_CodeSniffer ; PHPCompatibility `testVersion 8.2-`. PHPUnit. CI : GitHub Actions.

## Commandes (préfixer par `ddev` en local)

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

## Workflow attendu de l'agent

1. Lire la règle pertinente dans `@.claude/rules/` avant de coder une feature.
2. Commande WooCommerce → facture **brouillon** dans Abby par défaut (jamais finalisée auto).
3. Appels API Abby **toujours asynchrones** (Action Scheduler), jamais dans le checkout/page load.
4. Sécurité : sanitize en entrée, escape en sortie, nonce + `current_user_can('manage_woocommerce')`,
   vérif signature sur webhook entrant.
5. `phpcs` + `wp plugin check` avant de proposer un commit. Conventional Commits + SemVer.

## À NE PAS faire

- Logger/stocker la clé API Abby en clair. Appels API synchrones ou répétés. Endpoints inventés
  (confirmer sur https://docs.abby.fr). Tracking sans opt-in déclaré. Baisser le plancher PHP
  ou renommer le slug/text domain.

## Imports

- @.claude/rules/architecture.md — arborescence, classes, hooks, HPOS, secrets, marques
- @.claude/rules/php-wordpress.md — conventions WP/Woo, sécurité, i18n (anglais par défaut), Plugin Check
- @.claude/rules/abby-integration.md — API Abby : auth, brouillons, contacts, avoirs, recettes, langue du document
- Roadmap produit : `ROADMAP.md` (racine)
