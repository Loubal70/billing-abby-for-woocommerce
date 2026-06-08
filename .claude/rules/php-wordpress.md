# Règle — PHP / WordPress / WooCommerce

## Conventions de code

- **WPCS** via PHP_CodeSniffer ; `phpcbf` pour l'autofix.
- **PHPCompatibility** sur `testVersion 8.2-` dans `.phpcs.xml.dist`.
- PHP 8.2+ autorisé (enums, readonly, unions, named args) ; ne pas dépasser 8.2 sans raison.
- Préfixe partout : namespace `Rankea\BillingAbby\` (PSR-4) ; procédural `bafw_`.

## Sécurité (bloquant — catégorie Security de Plugin Check)

- **Sanitize** toute entrée (`sanitize_text_field`, `sanitize_email`, `absint`…).
- **Escape** toute sortie (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- **Nonces** + `current_user_can( 'manage_woocommerce' )` sur toute action admin.
- HTTP via `wp_remote_get` / `wp_remote_post` (jamais cURL brut) ; vérifier le code HTTP + `is_wp_error()`.
- `$wpdb->prepare` si SQL direct (à éviter — privilégier l'API WC/WP).
- Webhooks entrants : vérifier la signature/secret avant traitement, rejeter sinon.
- Jamais d'`eval`, de code distant exécuté, ni d'obfuscation base64.

## Internationalisation — anglais par défaut

- **Toutes les chaînes source en anglais (`en_US`)**, via `__()`, `esc_html__()`… avec le text
  domain `billing-abby-for-woocommerce` (= slug → chargement auto par WP.org).
- Pas de variable dans une chaîne traduisible : `printf` + placeholders.
- Générer le `.pot` dans `languages/`, livrer `fr_FR.po/.mo`. Le français est une **traduction**,
  pas la source. Les autres locales viennent de translate.wordpress.org une fois sur WP.org.
- ⚠️ Ne pas confondre avec la **langue du document Abby** (facture envoyée au client) :
  voir `@.claude/rules/abby-integration.md`.

## Conformité dépôt WP.org (catégorie Plugin Repo)

- Version du header = `Stable tag` du `readme.txt`.
- `readme.txt` au format WP.org, avec une section **service externe Abby** : quelles données
  sont envoyées, où, lien CGU/politique d'Abby (exigence Guidelines pour tout service tiers).
- GPL-2.0-or-later (`License` + `License URI`).
- Pas de « phone home »/tracking sans opt-in explicite + divulgation. Assets embarqués.

## Performance

- Aucun appel API Abby synchrone (checkout/page load) : Action Scheduler.
- Pas de requête réseau sur `init`/`admin_init`. Chargement paresseux. Cache court côté lecture.

## Avant chaque PR

```bash
./vendor/bin/phpcs
./vendor/bin/phpunit
wp plugin check billing-abby-for-woocommerce   # vert sur Plugin Repo + Security
```
