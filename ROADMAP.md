# ROADMAP — Billing Abby for WooCommerce

Priorités calées sur (a) les frictions réelles du forum Abby autour de l'intégration
WooCommerce, (b) la barre posée par le concurrent SaaS *Order Invoicer*, (c) la réforme
française de la facturation électronique (septembre 2026). Différenciation : **plugin natif,
sans abonnement tiers, données qui ne transitent pas par un cloud externe**.

## Phase 0 — Fondations (infra)

- [ ] Scaffolding via `wp scaffold plugin billing-abby-for-woocommerce … --ci=github`
- [ ] En-tête complet : `Requires PHP: 8.2`, `Requires at least: 6.4`, `Requires Plugins: woocommerce`, GPL-2.0-or-later
- [ ] Composer + autoload PSR-4 (`Rankea\BillingAbby\`)
- [ ] WPCS + PHPCompatibility (`testVersion 8.2-`) + PHPUnit
- [ ] GitHub Actions : matrice `php: ['8.2', '8.4']`, phpcs + phpunit + Plugin Check
- [ ] Déclaration de compatibilité HPOS
- [ ] `readme.txt` conforme (section service externe Abby + non-affiliation)

## Phase 1 — MVP : commande → facture brouillon

- [ ] Page de réglages (onglet WooCommerce) : saisie + validation de la clé API Abby
- [ ] Client API Abby (`wp_remote_*`), gestion d'erreurs + retries
- [ ] Mapping commande → document : lignes produits, quantités, montants
- [ ] Création/rapprochement du contact client dans Abby
- [ ] Déclenchement async (Action Scheduler) sur statut configurable (`completed` par défaut)
- [ ] Statut de synchro visible sur la fiche commande

## Phase 2 — Robustesse (pièges connus du forum)

- [ ] **Détection d'un contact déjà existant** (éviter les doublons)
- [ ] Ligne de **frais de port** avec le bon taux de TVA
- [ ] **Remboursements (partiels/total) → avoirs** Abby
- [ ] Option **« marquer comme payée »** → **livre de recettes**
- [ ] **Franchise en base de TVA** (auto-entrepreneurs non assujettis)
- [ ] **Langue du document** : détecter la langue de la commande (Polylang / WPML / locale
      client WooCommerce) et la transmettre à Abby (Abby est multilingue) — champ à confirmer sur docs.abby.fr
- [ ] Journal d'événements + ré-essai manuel ; idempotence (pas de double facture sur retry/webhook)

## Phase 3 — Conformité & réforme 2026

- [ ] Alignement facturation électronique obligatoire (Abby = Plateforme Agréée)
- [ ] Mentions légales obligatoires sur les documents
- [ ] Multi-statuts (micro, EI, EURL…) selon profil Abby
- [ ] Doc utilisateur (prérequis : API sur forfait Abby Pro+)

## Phase 4 — Distribution & acquisition

- [ ] Accord écrit Abby sur l'usage du nom
- [ ] Soumission WP.org (2FA, Plugin Check vert, slugs réservés vérifiés)
- [ ] Landing SEO `rankea.agency/tools/...` (français — le slug étant en anglais)
- [ ] Freemium : gratuit (MVP) + PRO (avoirs, multi-statut, TVA avancée, multilingue)
- [ ] FAQ / comparatif honnête vs Make et vs Order Invoicer

## Phase 5 — Pérennité

- [ ] i18n complète : `en_US` source + `fr_FR` livré, autres langues via translate.wordpress.org
- [ ] Tests sur le mapping TVA/montants (zone à risque)
- [ ] Télémétrie strictement opt-in (si utile)
- [ ] Veille API Abby + compat WooCommerce (HPOS, blocs checkout) à chaque release

---

### Hors périmètre (pour l'instant)

Synchro stock bidirectionnelle, multi-comptes Abby, autres plateformes e-commerce. À
reconsidérer seulement si la traction le justifie (marché étroit : intersection « utilise
Abby » × « sous WooCommerce » × « prêt à installer un plugin »).
