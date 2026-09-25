# Hypothèses et décisions à faire valider par la coopérative

Ce document recense **toutes les décisions prises par l'équipe de développement** sur les points que le cahier des charges (v0.1) marque « À valider / À définir » (sections 8.2, 31 et 32), ainsi que les ambiguïtés relevées dans les documents.

Principes appliqués pour chaque décision :

1. **Rien n'est supprimé en silence.** Toute correction passe par une annulation motivée et tracée.
2. **La traçabilité est systématique** : auteur, date et motif, dans le journal d'audit.
3. **Tout ce qui peut varier est paramétrable** (écran *Paramètres*), pour que la coopérative puisse changer d'avis sans développement.

Pour chaque point, la colonne « Où le changer » indique comment revenir sur la décision.

---

## 1. Cas particuliers de gestion (§8.2)

| # | Question | Décision retenue | Justification | Où le changer |
|---|----------|------------------|---------------|---------------|
| 1 | **Adhésion en cours de mois** : cotisation complète ou proratisée ? | **Jamais de prorata.** Le mois d'adhésion est dû **en entier** si l'adhésion a lieu **au plus tard le jour d'échéance (le 5)**. Au-delà, la première cotisation due est celle du mois suivant. | C'est la règle la plus simple à comprendre et à contrôler : pas de montants fractionnés, et elle reste cohérente avec l'échéance du 5. | Paramètre « Adhésion en cours de mois » : 3 règles au choix (selon l'échéance / mois toujours dû / toujours le mois suivant). |
| 2 | **Sortie d'un membre** : jusqu'à quand est-il redevable ? Que devient son historique ? | Le membre reste redevable du mois de sortie **s'il est encore membre à la date d'échéance** (sortie le 5 ou après). Les cotisations postérieures **non payées** sont automatiquement **annulées** (motif tracé), jamais supprimées. Les impayés antérieurs **restent dus**. L'historique est conservé intégralement. Le compte d'accès reste actif, pour qu'il puisse consulter ses reçus. | La règle est symétrique à celle de l'adhésion. On ne perd aucune dette, et on ne crée aucune dette après la sortie. | Statut du membre (fiche membre). Un administrateur peut rétablir une cotisation annulée. |
| 3 | **Changement du montant de cotisation** : rétroactif ? | **Non rétroactif.** Le montant est figé sur chaque cotisation au moment où elle est générée. Un nouveau montant ne s'applique qu'aux mois **pas encore générés**. | La non-rétroactivité est la seule option sûre : un changement de paramètre ne modifie jamais un montant déjà attendu ou déjà payé. | Paramètre « Montant de la cotisation mensuelle ». Si un rattrapage exceptionnel est décidé, il se fait par opération tracée (annulation ou rétablissement). |
| 4 | **Paiement partiel** : autorisé ? | **Autorisé** : un versement inférieur au dû donne le statut « Partiellement payé ». Si le solde est réglé après l'échéance, la cotisation passe à « Payé en retard ». Une cotisation partielle dont l'échéance est dépassée compte comme **impayée** pour le reste dû. | En tontine, refuser un versement partiel pousse à encaisser sans rien enregistrer, ce qui est le vrai risque. | Paramètre « Autoriser les paiements partiels » (oui/non). |
| 5 | **Paiement de plusieurs mois en une fois** : comment le ventiler ? | Un paiement = **un reçu**, qui peut couvrir **plusieurs mois**, y compris **d'avance** (12 mois maximum). Le montant est affecté **d'abord au mois le plus ancien**. Un montant **supérieur** au total dû est **refusé** (pas de trop-perçu). | La ventilation chronologique est l'usage courant : on apure d'abord les dettes anciennes. Refuser le trop-perçu évite de gérer des avoirs. | Paramètre « Paiement d'avance : nombre de mois maximum ». |
| 6 | **Correction d'une erreur de saisie** : qui, et comment ? | Un paiement **n'est jamais modifié**. Pour le corriger, on l'**annule** (motif obligatoire) puis on **ressaisit** le bon paiement, qui reste **lié** au reçu annulé (« Saisir le paiement corrigé »). Les cotisations sont recalculées automatiquement. | Un reçu déjà remis au membre ne doit jamais changer de contenu. Le couple « annulé + corrigé » garde toute la trace. | — |
| 7 | **Annulation d'un paiement** : quel workflow ? | Deux niveaux : le **Gestionnaire/Trésorier demande** l'annulation (motif obligatoire), puis un **Administrateur valide ou rejette** (motif également). L'administrateur peut aussi annuler directement, avec motif. Chaque étape notifie les personnes concernées et figure dans l'audit. Même workflow pour les **opérations de caisse**. | Contrôle à quatre yeux : la personne qui encaisse n'est pas celle qui annule. | Écran *Rôles et permissions* : permissions `paiements.demander_annulation` et `paiements.annuler`. |
| 8 | **Doublon de paiement** : comment le détecter ? | (a) **Blocage** si la **référence de transaction** (Wave, OM, chèque…) a déjà été utilisée. (b) **Alerte avec confirmation explicite** si un paiement **du même montant pour le même membre** existe à ± N jours. (c) Une période déjà réglée ne peut pas être sélectionnée. | Le blocage sur la référence évite les vrais doublons. L'alerte laisse passer les paiements légitimes identiques (ex. deux mensualités). | Paramètre « Détection de doublon : fenêtre (jours) » (défaut : 3). |
| 9 | **Remboursement** : possible ? | **Aucun remboursement automatique en V1.** Un paiement erroné est annulé (voir 6 et 7). Un remboursement décidé par le bureau s'enregistre en **opération de sortie**, catégorie « Remboursement de cotisation », **réservée aux administrateurs**. | Le trop-perçu étant impossible (voir 5), le remboursement reste exceptionnel et doit être décidé par le bureau. | Catégories financières (Paramètres). |
| 10 | **Membre suspendu** : reste-t-il redevable ? | **Oui par défaut** : les cotisations continuent d'être générées pendant la suspension. | Il est plus sûr d'avoir une cotisation à régulariser (exonération tracée) qu'une cotisation oubliée à recréer après coup. | Paramètre « Un membre suspendu reste redevable » (oui/non). |
| 11 | **Membre décédé** : que devient le compte ? | Statut « Décédé » avec date d'effet. Les cotisations postérieures sont annulées automatiquement, le **compte d'accès est désactivé**, l'**historique est conservé**. Les impayés antérieurs peuvent être **régularisés** (exonération motivée). Une aide aux ayants droit s'enregistre en sortie « Aides sociales aux membres ». La gestion des ayants droit eux-mêmes est **hors V1**. | Clôture propre et tracée, sans perte de données. | Fiche membre (statut) et Cotisations du mois (« Régulariser »). |
| 12 | **Perte de mot de passe** | Aucun email ni SMS n'est disponible en V1. L'écran « Mot de passe oublié » **transmet la demande aux administrateurs** (notification interne), avec la même réponse que le compte existe ou non. L'administrateur **vérifie l'identité** puis génère un **mot de passe temporaire**, affiché une seule fois. Le changement est **imposé à la première connexion**. | C'est une procédure réaliste sans coût externe, et plus sûre qu'une réinitialisation automatique non vérifiée. | Réinitialisation par email à activer en V2 (architecture prévue). |
| 13 | **Utilisateur désactivé** : ses actions restent-elles visibles ? | **Oui.** Aucun compte n'est supprimé, seulement désactivé ; ses sessions ouvertes sont fermées immédiatement. Son nom reste affiché partout avec la mention « (désactivé) » : reçus, détails, audit. Il est impossible de désactiver son propre compte ou le dernier administrateur actif. | Traçabilité (§8.1) et garde-fou contre le blocage de l'administration. | Écran *Utilisateurs*. |

## 2. Autres points à valider (§31, §32)

| Sujet | Décision retenue | À confirmer |
|-------|------------------|-------------|
| **Rôle « Président / Vérificateur »** (§6) | **Créé**. Lecture seule étendue : tableau de bord global, membres, cotisations, paiements, reçus, impayés, retards, opérations, rapports (avec export) et journal d'audit. **Aucun droit de saisie.** | Pertinence et périmètre exact. La matrice est modifiable dans l'écran *Rôles et permissions*. |
| **Liste des rôles** | Administrateur, Gestionnaire (Trésorier), Vérificateur, Membre. De nouveaux rôles peuvent être créés depuis l'interface. | Liste définitive. |
| **« Saisie de certaines opérations financières » par le Gestionnaire** (§6) | Le terme « certaines » n'est pas défini. Les catégories marquées **« réservée »** (par défaut « Aides sociales aux membres » et « Remboursement de cotisation ») sont réservées aux détenteurs de la permission `operations.categories_restreintes` (administrateurs). | Liste des catégories réservées. |
| **Statut « Régularisé »** (§7.2) | Non défini dans le cahier des charges. Retenu : cotisation **soldée par décision administrative, sans encaissement** (exonération, décès, geste du bureau). Administrateur uniquement, motif obligatoire, réversible (« Rétablir »). | Définition. |
| **Statut « Impayé »** | Le modèle de données (§16) ne l'inclut pas parmi les statuts stockés, mais §8.1 le définit. Il est donc **calculé** : cotisation « À payer » ou « Partiellement payé » dont l'échéance est dépassée. | — |
| **Date d'adhésion des membres importés** | La liste du personnel ne donne pas de date d'adhésion. Tous les membres importés reçoivent le **1er jour de la première période de cotisation** (paramètre, démo : **mai 2026**). | Date réelle de démarrage de la coopérative, puis ajustement du paramètre « Première période de cotisation » **avant la mise en production**. |
| **Matricules** | Le « N° » de la liste devient le matricule, avec le préfixe `NGD-` (ex. N° 7 → `NGD-007`). Les nouveaux membres reçoivent le numéro suivant proposé automatiquement (modifiable). | Existe-t-il un matricule officiel (matricule de la fonction publique) à utiliser à la place ? |
| **Comptes des membres** | Pas de création massive de comptes (aucun mot de passe partagé). Un administrateur ouvre l'accès **membre par membre** (bouton « Créer un accès membre » sur la fiche) : identifiant = matricule, mot de passe temporaire. | Procédure de remise des accès. |
| **Mode de gestion actuel / reprise de données** (§2) | Note du cahier : « on vient tout juste de commencer ». **Aucune reprise historique.** L'import porte uniquement sur la liste du personnel. Pour reprendre un historique, la commande `coop:importer-membres` accepte aussi un CSV. | Existence de cotisations déjà encaissées à saisir. |
| **Effectif** (§32) | 82 agents dans la liste. L'architecture (index SQL, pagination, agrégats en base) est dimensionnée pour plusieurs milliers de membres. | — |
| **Architecture** (§15, §32 P1) | Stack recommandée retenue : **Laravel 13 + MySQL 8 + Blade/Livewire 4**, Tailwind CSS 4. | — |
| **API REST** (§17) | **Aucune API publique** : avec Livewire, l'interface serveur n'en a pas besoin (§17 le prévoit pour ce cas). Toutes les routes sont protégées par la même couche de permissions. | À prévoir seulement pour une future application native. |
| **Prêts / crédits** (§7.13) | **Hors V1** (V2), comme proposé. | — |
| **Notifications** (§18) | **Notifications internes uniquement** en V1 : confirmation de paiement, rappel J-3, rappel le jour de l'échéance, notification de retard, demandes d'annulation et de réinitialisation, information diffusée par un administrateur. L'architecture « canaux » (`app/Services/Notifications`) permet d'ajouter email, SMS ou WhatsApp sans refonte. | Budget des canaux externes. |
| **Relance des impayés** (écran 11 « relance future ») | Bouton de rappel **interne** pour les membres ayant un compte. SMS et WhatsApp : V2/V3. | — |
| **Exercice** (§7.12) | Paramètre « mois de début d'exercice » (défaut : janvier). Il fixe la date de début par défaut des rapports. Les statistiques annuelles restent par année civile. | Exercice réel de la coopérative. |
| **Solde de caisse** | Solde = **solde initial (paramètre)** + cotisations encaissées + autres entrées − sorties (hors éléments annulés). Les cotisations ne sont pas recopiées dans les opérations de caisse, pour éviter tout double comptage. | Solde initial réel. |
| **Sortie de caisse supérieure au solde** | **Alerte** avec confirmation, sans blocage : les soldes de départ ne sont pas encore connus avec certitude. | Faut-il bloquer ? |
| **Traçabilité des consultations** (§14) | Journal d'audit : toutes les écritures ; connexions (réussies, échouées, bloquées) ; téléchargements de reçus ; exports de rapports ; consultations de justificatifs. **Les simples affichages d'écran ne sont pas journalisés** : ce serait trop volumineux pour une utilité faible. | Niveau de traçabilité attendu. |
| **Conservation des données** (§14) | **Conservation illimitée** en attendant une politique : rien n'est supprimé, les membres sortis ou décédés restent consultables. | Durée de conservation et politique d'archivage. |
| **Sauvegardes** (§20) | Sauvegarde **quotidienne à 2 h**, conservation des **14 dernières** (paramétrable). Procédure de restauration testée : voir `docs/SAUVEGARDE.md`. L'externalisation hors serveur est à organiser par l'hébergeur. | Fréquence, durée de rétention et stockage externe. |
| **Politique de mot de passe** | 8 caractères minimum, avec lettres et chiffres. 5 essais par identifiant puis blocage de 5 minutes, et 20 essais par minute et par adresse IP. Session expirée après 120 minutes d'inactivité. | — |
| **Hors connexion** (§12.4) | Consultation hors ligne limitée aux pages **déjà ouvertes** : tableau de bord, mon historique, reçus. Ce cache est **purgé à la déconnexion**. **Toute saisie financière est bloquée hors connexion** (boutons désactivés, message explicite). Rien n'est mis en file d'attente. | — |

## 2 bis. Assistante « Fatou » (demande ajoutée après le cahier des charges)

| Sujet | Décision retenue | Justification / à confirmer |
|-------|------------------|-----------------------------|
| **Moteur** | **Moteur local, gratuit**, écrit dans l'application : règles, vocabulaire français / wolof et reconnaissance approchée des noms. Aucun service externe, aucune donnée transmise. Claude (Anthropic) reste une **option payante désactivée**, qui ne sert qu'aux phrases non comprises. | Demande de la coopérative : « sans rien dépenser ». |
| **Exécution « directe »** | Fatou prépare tout (membre, mois, montant, mode, date). L'écriture en caisse demande **un geste de confirmation** : bouton « Confirmer », ou réponse « oui » / « waaw ». | Une phrase peut être mal comprise, surtout à l'oral. Le cahier des charges exclut toute erreur financière silencieuse (§8.1). |
| **Ambiguïtés** | Homonymes, ou plusieurs personnes citées : Fatou **demande** lequel (numéro, matricule ou service). Nom introuvable : elle le dit, sans jamais deviner. | — |
| **Valeurs par défaut** | Mois = le plus ancien mois dû (ou le mois suivant, en avance, si le membre est à jour) ; montant = reste dû des mois choisis ; mode = espèces ; date = aujourd'hui. Un mois cité sans année = l'occurrence la plus proche (jusqu'à 3 mois d'avance, sinon dans le passé). | — |
| **Montants en wolof** | Les nombres wolof sont compris en **dërëm** (1 dërëm = 5 FCFA) : « junni » = 5 000 FCFA, « ñaari junni » = 10 000 FCFA, « fukki junni » = 50 000 FCFA. Le montant en FCFA est toujours affiché sur la carte avant confirmation. | À confirmer avec les utilisateurs. |
| **Réponses en wolof** | Quand on lui écrit en wolof, Fatou répond en wolof simple, **suivi de la traduction française**. | Les tournures wolof des réponses gagneraient à être relues par un locuteur natif (fichier `AssistantLocal.php`). |
| **Droits et traçabilité** | Permission `assistant.ia` (Administrateur et Gestionnaire) ; le vérificateur et les membres n'y ont pas accès. Chaque demande (`ia.commande`) et chaque paiement confirmé sont journalisés. | — |
| **Voix** | Dictée en français via le navigateur (Chrome ou Edge). **Elle nécessite Internet** : l'audio est envoyé au service de reconnaissance de Google, contrairement à la compréhension du texte, qui reste locale. Le wolof oral n'est reconnu par aucun navigateur, il faut donc l'écrire. | Accord du bureau sur l'envoi de l'audio à Google ; sinon, se limiter à l'écrit. |

## 3. Ambiguïtés et contradictions relevées dans les documents

1. **Renvois erronés en §13** : « Journalisation des actions sensibles (voir Section 20 — Journal d'audit) » et « voir Section 20 (livrable) et 21… voir Section 20 "Sauvegarde" ». La section 20 traite de la sauvegarde ; le journal d'audit est décrit en §16 (`audit_logs`). La phrase sur les sauvegardes semble tronquée.
2. **§16 `paiements.cotisation_id` (clé unique) vs §16.1** (« une cotisation peut être réglée par un ou plusieurs paiements — paiement groupé »). Un paiement groupé couvre plusieurs cotisations, ce qu'une clé étrangère unique ne peut pas représenter. → Ajout d'une table de ventilation `paiement_cotisation` (écart assumé par rapport au modèle, que le §16 déclare « non exhaustif »).
3. **§16 `users.role_id` (un seul rôle)** : les rôles et permissions sont gérés par *spatie/laravel-permission* (recommandé en §15), avec les tables `roles`, `permissions` et `role_has_permissions` du §16. En pratique, un rôle par utilisateur est attribué depuis l'interface.
4. **Montants** : le §16 indique `decimal`. Le franc CFA n'ayant pas de subdivision utilisée, les montants sont stockés en **entiers FCFA**, ce qui exclut toute erreur d'arrondi.
5. **Colonne « Montant » de la liste du personnel** : elle est vide pour tous les agents. Son intention n'est pas claire (cotisation variable par agent ? salaire ?). Elle est **ignorée** ; la cotisation reste le montant unique paramétré (10 000 FCFA).
6. **Qualité de la liste du personnel** :
   - N° 28 « IBRA / Ibra » : le nom de famille reprend le prénom et manque probablement ; importé avec l'observation « nom à vérifier ».
   - N° 29 « AMY / Amy » : **corrigé en Amy TINE** sur indication de la coopérative (fichier `database/seeders/data/corrections_personnel.php`, appliqué à chaque import).
   - Homonymes : DAOUDA SENE (N° 20, 68, 89), DJIBY SENE (33, 50), NDEYE NGOM (53, 56), MODOU SENE (31, 58). Ils sont importés comme **personnes distinctes** ; le formulaire de création signale désormais toute homonymie.
   - Numéros absents : 27, 40, 44, 60 à 63, 66 (agents retirés de la liste ?).
   - Aucun téléphone, sexe, fonction ni service : ces champs sont facultatifs et à compléter.
7. **Écran 14 « Rapports »** : le §6 ne donne au Gestionnaire que la « consultation » des rapports financiers, alors que l'écran 14 et le parcours §9.4 lui prévoient l'**export**. → Export autorisé au Gestionnaire (permission `rapports.exporter`, modifiable).
8. **Écran 4** : le §10 prévoit une « lecture pour le membre concerné » de sa fiche, sans l'écran de liste. → Le membre voit sa propre fiche et son historique, jamais ceux des autres (vérifié par les tests).

## 4. Données de démonstration (à ne pas confondre avec les données réelles)

La base de démonstration (`php artisan migrate:fresh --seed`) contient :

- les **82 membres réels** de la liste du personnel ;
- **4 membres fictifs** `NGD-901` à `NGD-904`, qui illustrent les cas particuliers (adhésion après le 5, suspension, sortie) ;
- un historique de cotisations et de paiements **fictif** de mai 2026 à aujourd'hui, avec des profils de paiement aléatoires mais reproductibles ;
- des opérations de caisse fictives, une annulation corrigée, des demandes d'annulation en attente et une régularisation.

**Pour la mise en production**, utiliser le seeder de production, qui ne crée aucune donnée fictive :
`php artisan migrate:fresh --seed --seeder=ProductionSeeder`.
