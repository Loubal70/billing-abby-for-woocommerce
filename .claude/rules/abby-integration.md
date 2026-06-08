# Règle — Intégration API Abby

> ⚠️ **Garde-fou** : noms d'endpoints, champs et formats exacts à confirmer sur
> **https://docs.abby.fr** (et le module Make, qui reflète les actions disponibles). Ne jamais
> inventer une route : sinon `// TODO: confirmer endpoint sur docs.abby.fr` et demander avant d'implémenter.

## Authentification

- API par **clé API** générée depuis le compte Abby.
- Accès API réservé aux **forfaits payants (Pro et +)** : le dire dans le readme, distinguer
  dans l'UI « clé invalide » vs « forfait insuffisant ».
- Valider la clé à l'enregistrement (appel léger). Stocker en option, masquée.

## Modèle de données (à mapper)

Commande WooCommerce → **document de facturation** Abby :

- **Contact / organisation** : le client. Rapprocher par email/identifiant pour **éviter les
  doublons** (friction n°1 du forum).
- **Document** : facture (ou devis selon réglage), créé en **brouillon** par défaut.
- **Lignes** : produits + quantités + prix, **+ ligne de frais de port** avec son taux de TVA.
- **Avoir** : pour un remboursement WooCommerce (partiel/total).
- **Livre de recettes** : alimenté quand le document est **marqué comme payé**.

## Langue du document (Abby est multilingue)

- Distinct de l'i18n du plugin : ici c'est la langue de la **facture envoyée au client**.
- Détecter la langue de la commande/du client dans cet ordre de priorité :
  1. langue Polylang de la commande (`pll_get_post_language` / meta de commande),
  2. langue WPML (meta `wpml_language` de la commande),
  3. locale client WooCommerce (`get_user_locale` / `_wp_locale` de la commande),
  4. repli : langue par défaut du compte Abby.
- Transmettre cette langue à Abby **si l'API expose un champ langue/locale** sur le document
  ou le contact — `// TODO: confirmer le champ sur docs.abby.fr`.
- Objectif : ne pas facturer en français un client anglophone (ou l'inverse). Beaucoup de
  boutiques cibles sont multilingues (Polylang/WPML).

## Flux de référence

1. Statut déclencheur → enqueue async (Action Scheduler).
2. Rapprocher/créer le contact (idempotent).
3. `InvoiceMapper` : produits + port + TVA + **langue détectée**.
4. Créer la **facture brouillon** ; stocker l'ID dans `_bafw_abby_invoice_id`.
5. (Option marchand) marquer payée → livre de recettes.
6. Remboursement WC → avoir.

## Règles métier

- **Brouillon par défaut, toujours.** Finalisation auto seulement si le marchand l'a choisi
  (une facture finalisée ne se supprime plus : il faut un avoir). Garde-fou comptable.
- **TVA** : gérer la **franchise en base** (montants sans TVA, mentions adéquates) ; couvrir par tests.
- **Idempotence** : jamais deux factures pour une commande (vérifier le méta avant tout appel).
- **Erreurs/rate limit** : retries + backoff via Action Scheduler ; journaliser l'échec (sans la
  clé API) ; ré-essai manuel depuis la fiche commande.

## Destinataire externe unique

Aucune donnée ne transite par un serveur tiers (ni Rankea, ni cloud) : seul destinataire
externe = l'API Abby, déclaré dans le readme. C'est la promesse différenciante vs Make/Order Invoicer.
