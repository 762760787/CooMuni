# Coopérative du personnel — Commune de Ngoundiane

Application web (PWA, mobile d'abord) de gestion de la tontine du personnel municipal : membres, cotisations mensuelles, paiements et reçus PDF, impayés et retards, caisse, rapports PDF/Excel/CSV, utilisateurs et rôles, paramètres et journal d'audit.

- **Spécifications** : `docs/Cahier_des_charges_Cooperative_Ngoundiane.docx`
- **Décisions à faire valider par la coopérative** : [`ASSUMPTIONS.md`](ASSUMPTIONS.md)
- **Sauvegarde et restauration** : [`docs/SAUVEGARDE.md`](docs/SAUVEGARDE.md)

---

## 1. Stack technique (§15 du cahier des charges)

| Élément | Choix |
|---|---|
| Backend | Laravel 13 (PHP 8.3+) |
| Base de données | MySQL 8 / MariaDB 10.6+ |
| Interface | Blade + Livewire 4 (Alpine.js inclus), Tailwind CSS 4 compilé par Vite |
| Rôles et permissions | spatie/laravel-permission |
| PDF | barryvdh/laravel-dompdf |
| Excel | openspout/openspout (XLSX) ; CSV natif (UTF-8, séparateur `;`) |
| PWA | manifeste dynamique, service worker `public/sw.js`, icônes tirées du logo de la Mairie |

Aucune ressource externe (CDN, polices web) n'est chargée : l'application reste légère sur réseau mobile, fonctionne hors ligne et ne transmet rien à des tiers (seule exception : l'option payante Claude de l'assistante, désactivée par défaut — voir §6).

## 2. Installation en local (Windows / Laragon)

Prérequis : PHP 8.3+ avec les extensions `pdo_mysql`, `gd`, `zip`, `intl`, `mbstring` et `fileinfo` ; Composer 2 ; Node.js 20+ ; MySQL 8. Laragon fournit l'ensemble.

```bash
composer install
```

```bash
npm install
```

```bash
npm run build
```

Configuration :

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

Vérifiez ensuite dans `.env` : `DB_DATABASE=coomuni`, `DB_USERNAME`, `DB_PASSWORD`, et `MYSQLDUMP_PATH` (chemin de `mysqldump`, utilisé pour les sauvegardes).

Créez la base `coomuni` (utf8mb4), par exemple depuis HeidiSQL, ou :

```bash
mysql -uroot -e "CREATE DATABASE coomuni CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

> ⚠️ **La base `coomuni` est désormais la base RÉELLE de la coopérative** (remise à zéro du 25/09/2026). Ne lancez **jamais** `php artisan migrate:fresh --seed` sur cette base : la commande efface tout et recharge les données fictives. Pour une démonstration, utilisez une autre base (`DB_DATABASE` dans `.env`).

Base de **démonstration** (sur une base de test uniquement : membres réels + historique fictif + comptes de test) :

```bash
php artisan migrate:fresh --seed
```

Icônes PWA (déjà générées dans `public/icons` ; à relancer seulement après un changement de logo) :

```bash
php artisan coop:icones
```

## 3. Lancer l'application

**Option A — serveur intégré (le plus simple).** L'application est alors accessible sur <http://127.0.0.1:8000>.

```bash
php artisan serve
```

`localhost` et `127.0.0.1` sont des « contextes sécurisés » : le service worker et l'installation PWA y fonctionnent sans HTTPS.

**Option B — Laragon avec HTTPS.**

1. Dans Laragon : *Menu → Apache → SSL → Enabled*, puis *Start All*. Laragon crée automatiquement l'hôte virtuel `https://coomuni.sn` (format `{name}.sn` configuré sur ce poste), qui pointe vers `public/`.
2. Mettez `APP_URL=https://coomuni.sn` dans `.env`.
3. Faites approuver le certificat Laragon par le navigateur si nécessaire.

### Comptes (base réelle)

Trois comptes : `admin` (Administrateur), `tresorier` (Gestionnaire / Trésorier), `verificateur` (Président / Vérificateur).
Leurs mots de passe ne sont **jamais** publiés : à la mise en ligne, `deploy/installer-serveur.sh` génère des mots de passe
temporaires affichés une seule fois (à tout moment : `php artisan coop:nouveaux-mots-de-passe`), et l'application impose
d'en choisir un nouveau à la première connexion. Les comptes des membres se créent depuis leur fiche (« Créer un accès membre »).

### Comptes de la base de démonstration

| Rôle | Identifiant | Mot de passe | Remarque |
|---|---|---|---|
| Administrateur | `admin` | `Admin@2026` | accès complet |
| Gestionnaire / Trésorier | `tresorier` | `Tresor@2026` | saisie des paiements, demandes d'annulation |
| Président / Vérificateur | `verificateur` | `Verif@2026` | lecture seule étendue, audit |
| Membre | `membre` | `Membre@2026` | lié à la fiche fictive *Awa DÉMO* (NGD-901) |

La connexion accepte l'identifiant, l'email ou le téléphone. Le compte `ancien.tresorier` est **désactivé** : il montre que les actions d'un compte désactivé restent visibles dans l'historique.

> ⚠️ Ces mots de passe sont publics : ils ne doivent **jamais** être utilisés en production.

## 4. Écrans (Section 10 du cahier des charges)

| N° | Écran | Route |
|---|---|---|
| 1 | Connexion (+ mot de passe oublié, changement de mot de passe imposé) | `/connexion`, `/mot-de-passe-oublie`, `/changer-mot-de-passe` |
| 2 | Tableau de bord, selon le rôle | `/` |
| 3 | Liste des membres | `/membres` |
| 4 | Fiche membre | `/membres/{id}` |
| 5 | Ajout / modification de membre | `/membres/nouveau`, `/membres/{id}/modifier` |
| 6 | Cotisations du mois (vue consolidée + saisie rapide) | `/cotisations` |
| 7 | Historique des cotisations | `/mon-historique`, `/membres/{id}/historique` |
| 8 | Enregistrement d'un paiement | `/paiements/nouveau` |
| 9 | Détail d'un paiement (reçu, annulation / correction tracée) | `/paiements/{id}` (liste : `/paiements`) |
| 10 | Reçus | `/recus`, PDF : `/recus/{id}/pdf` |
| 11 | Impayés | `/impayes` |
| 12 | Retards | `/retards` |
| 13 | Opérations financières | `/operations`, `/operations/nouvelle`, `/operations/{id}` |
| 14 | Rapports (aperçu + export PDF / Excel / CSV) | `/rapports` |
| 15 | Utilisateurs | `/utilisateurs` |
| 16 | Rôles et permissions | `/roles` |
| 17 | Paramètres (montant, échéance, règles, catégories, modes de paiement…) | `/parametres` |
| 18 | Journal d'audit | `/audit` |
| 19 | Profil utilisateur (+ notifications internes) | `/profil`, `/notifications` |

**Navigation mobile** : barre inférieure (4 raccourcis selon le rôle + « Plus »), menu hamburger, bouton flottant « Paiement », recherche plein écran. Au-delà de 1024 px de large, un menu latéral remplace la barre inférieure.

## 5. Règles métier principales

- Cotisation de **10 000 FCFA**, échéance le **5** du mois. Payé au plus tard le 5 = « Payé » ; payé après le 5 = « Payé en retard » ; non soldé après le 5 = « Impayé ».
- Montant, échéance, règles d'adhésion, de suspension et de paiement partiel, préfixes, catégories et modes de paiement : **tout est en base de données** et se modifie dans *Paramètres*.
- **Aucune suppression** de données financières ou de membres (interdiction au niveau des modèles). Les corrections passent par une annulation motivée et tracée ; les annulations de paiement suivent un workflow demande → validation.
- Les permissions sont vérifiées **côté serveur** à chaque route et à chaque action Livewire.

Le détail des décisions se trouve dans [`ASSUMPTIONS.md`](ASSUMPTIONS.md).

## 6. Assistante « Fatou » (gratuite, sans Internet)

Fatou permet d'encaisser **en écrivant ou en dictant une phrase**, en français, en wolof ou en mélangeant les deux, sans remplir de formulaire :

- « Fatou, fais un encaissement de Amy Tine aujourd'hui »
- « Amy Tine dafa fey ñaari weer tey ci Wave » → Fatou demande la référence Wave
- « Daouda Sene jox na ñaari junni cash » → 10 000 FCFA (en dërëm) ; 3 homonymes : « lequel ? 1, 2 ou 3 »
- « Combien doit Marie Faye ? » → situation, puis « oui » pour tout encaisser
- « non, plutôt 2 mois par Wave réf 777 » → la proposition est corrigée
- « Qui n'a pas payé ? », « Bilan du mois », « naata lañu dajale weer wii »

**Moteur local, gratuit.** La compréhension se fait dans l'application elle-même (`app/Services/Assistant/Local`) : aucun abonnement, aucune connexion Internet, aucune donnée transmise à l'extérieur. Fatou reconnaît :

- les **noms** malgré les fautes et les deux orthographes (Ndiaye / Njaay, Diouf / Juuf, Ousseynou / Useynu…), ainsi que les homonymes ;
- les **montants** : 10000, 10 000, 10k, « dix mille », « ñaari junni » (dërëm) ;
- les **mois** : « août », « de juillet à septembre », « 2 mois », « weer wi weesu », « tout ce qu'il doit » ;
- les **dates** : aujourd'hui/tey, hier/démb, lundi/altine, « le 3 », 03/09 ;
- les **modes** : cash, Wave, OM, virement, chèque ;
- les **références** de transaction ;
- les réponses **oui / waaw**, **non / déedéet**.

**Toujours une confirmation.** Fatou affiche une carte (montant, membre, mois, mode, date). L'encaissement n'est enregistré qu'après « Confirmer » (ou « oui » / « waaw »), avec les mêmes règles que la saisie manuelle : ventilation, doublons, reçu, journal d'audit. La note du paiement indique « Saisi via l'assistante Fatou » et reprend la phrase d'origine.

**Enrichir la compréhension.**

- Tester une phrase sans rien enregistrer :

  ```bash
  php artisan coop:fatou "Amy Tine dafa fey tey"
  ```

  Ou tout un fichier, une phrase par ligne :

  ```bash
  php artisan coop:fatou --fichier=phrases.txt
  ```

- Ajouter des mots (verbes, variantes de noms, modes…) dans `app/Services/Assistant/Local/Lexique.php`.
- Ajouter la phrase et le résultat attendu dans `tests/Unit/AnalyseurTest.php`, puis lancer `php artisan test` pour vérifier que rien d'autre n'est cassé.

**Réglages** : *Paramètres › Assistante IA* (activation, nom de l'assistante). L'accès est donné par la permission `assistant.ia` (Administrateur et Gestionnaire par défaut).

**Option payante (facultative) : Claude.** Si la coopérative le souhaite un jour, les phrases que le moteur local ne comprend pas peuvent être transmises à Claude (Anthropic). Pour cela, il faut ajouter `ANTHROPIC_API_KEY=...` dans `.env` (clé créée sur <https://platform.claude.com>, facturée à l'usage, indépendante de l'abonnement Claude), puis activer « Utiliser Claude pour les phrases non comprises » dans les Paramètres. Les données de la demande sont alors envoyées à Anthropic. Sans clé, rien ne change : tout fonctionne en local.

**Conversation à la voix** : appuyez sur 🎤 et parlez. La phrase est envoyée toute seule dès que vous vous arrêtez (option « Envoi automatique »), et Fatou **répond à voix haute** avec la synthèse vocale du navigateur (voix française de Windows ou Android, gratuite). Le bouton 🔊 / 🔇 coupe ou rétablit sa voix. Pour confirmer un encaissement, il suffit de dire « oui ».

**Limites** : la dictée vocale (micro) passe par le navigateur. Elle ne comprend que le **français**, fonctionne dans **Chrome ou Edge**, exige une page **sécurisée** (https, ou localhost sur l'ordinateur) et une **connexion Internet**, car l'audio est traité par un service de Google. En wolof, ou sans Internet, il faut écrire. Sur téléphone, le micro du clavier (Gboard, clavier de l'iPhone) fonctionne aussi dans le champ de saisie. Fatou ne fait ni les annulations ni les opérations de caisse, qui restent dans les écrans habituels.

## 7. Commandes utiles

| Commande | Rôle |
|---|---|
| `php artisan coop:generer-cotisations [AAAA-MM]` | Génère les cotisations du mois (idempotent, rattrape les mois manquants) |
| `php artisan coop:rappels` | Rappels J-3, rappel le jour J, notifications de retard (internes) |
| `php artisan coop:sauvegarde` | Sauvegarde MySQL compressée avec rotation (`storage/app/private/backups`) |
| `php artisan coop:importer-membres fichier.docx\|.csv [--simulation] [--date-adhesion=AAAA-MM-JJ]` | Import de la liste du personnel |
| `php artisan coop:icones` | Régénère les icônes PWA à partir du logo |
| `php artisan coop:remise-a-zero --debut=AAAA-MM` | Passage en base réelle : sauvegarde, puis efface cotisations, paiements, opérations, historique et données fictives ; garde les membres réels et les comptes |
| `php artisan coop:nouveaux-mots-de-passe [identifiant…]` | Nouveaux mots de passe temporaires (tous les comptes actifs, ou ceux indiqués), affichés une seule fois ; changement imposé à la connexion |
| `php artisan coop:fatou "phrase"` | Teste la compréhension de l'assistante Fatou, sans rien enregistrer |
| `php artisan test` | Suite de tests (règles métier, permissions, traçabilité, import, PWA, rapports) |

**Planificateur.** Même sans cron, la génération des cotisations du mois se déclenche automatiquement une fois par jour, à la première visite. En production, ajoutez une tâche planifiée :

```bash
* * * * * cd /chemin/du/projet && php artisan schedule:run >> /dev/null 2>&1
```

Sous Windows, créez une tâche du Planificateur de tâches qui exécute `php artisan schedule:run` toutes les minutes.

## 8. Mise en production (HTTPS obligatoire)

1. Serveur : PHP 8.3+ avec OPcache activé, MySQL/MariaDB, et un nom de domaine avec certificat TLS (Let's Encrypt par exemple).
2. Faire pointer la racine web vers `public/`.
3. Réglages dans `.env` :
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`
   - `FORCE_HTTPS=true` (redirection vers HTTPS et en-tête HSTS)
   - `SESSION_SECURE_COOKIE=true`
   - `TRUSTED_PROXIES` si l'application est derrière un proxy
   - `ADMIN_INITIAL_PASSWORD=…` (sinon un mot de passe temporaire est affiché à l'installation)
4. Installation sans données fictives :

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

   ```bash
   npm ci
   ```

   ```bash
   npm run build
   ```

   ```bash
   php artisan migrate --force --seed --seeder=ProductionSeeder
   ```

   ```bash
   php artisan optimize
   ```

5. **Avant la mise en service**, ajuster dans *Paramètres* la première période de cotisation, le solde initial de caisse et le nom de la coopérative, puis compléter les fiches des membres (téléphones, services…).
6. Configurer le cron, puis l'externalisation des sauvegardes (voir `docs/SAUVEGARDE.md`).

**Mise à jour de l'application** :

```bash
php artisan down
```

```bash
git pull && composer install --no-dev -o && npm ci && npm run build
```

```bash
php artisan migrate --force && php artisan optimize
```

```bash
php artisan up
```

Pour forcer les postes installés à recharger les ressources, incrémentez `VERSION` dans `public/sw.js`.

## 9. Sécurité (Section 13)

- Mots de passe hachés (bcrypt). Limitation des tentatives par identifiant et par adresse IP. Mot de passe temporaire à changer à la première connexion. Sessions en base, expirées après 120 minutes d'inactivité.
- Protection CSRF (Laravel et Livewire) ; échappement Blade contre les failles XSS ; requêtes paramétrées (Eloquent) contre l'injection SQL ; validation stricte côté serveur.
- Fichiers envoyés : type et taille contrôlés, photos **ré-encodées** (métadonnées et contenu parasite supprimés), stockage **privé** hors du dossier public, accès après vérification des droits.
- En-têtes de sécurité : `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS en HTTPS ; `Cache-Control: no-store` sur les pages authentifiées.
- Journal d'audit en **ajout seul** : modification et suppression impossibles au niveau du modèle.

## 10. Structure du code

```
app/
  Console/Commands/     génération mensuelle, rappels, sauvegarde, import, icônes
  Enums/                statuts de membre, statuts et états de cotisation
  Http/Controllers/     reçus PDF, exports, fichiers privés, PWA, déconnexion
  Http/Middleware/      compte actif, changement de mot de passe, en-têtes, génération quotidienne
  Livewire/             les 19 écrans (un composant par écran)
  Models/               modèles Eloquent (suppression interdite sur les données sensibles)
  Services/             logique métier : cotisations, paiements, caisse, statistiques, rapports, audit, notifications
  Services/Assistant/   assistante « Fatou » : moteur local gratuit (Local/) + option Claude
  Support/              période AAAA-MM, navigation, catalogue des permissions, logo, images
database/migrations/    schéma (voir §16)
database/seeders/       référentiels, rôles, import du personnel, démo, production
resources/views/        layouts, composants Blade, écrans Livewire, modèles PDF, page hors ligne
public/sw.js            service worker
tests/Feature/          tests automatisés
```
