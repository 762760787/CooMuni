# Sauvegarde et restauration (§20)

## Sauvegarde automatique

- Commande : `php artisan coop:sauvegarde`, planifiée **tous les jours à 2 h** (`routes/console.php`).
- Fonctionnement : `mysqldump --single-transaction` (sans verrouillage), compression gzip.
- Fichiers produits : `storage/app/private/backups/coop-AAAA-MM-JJ_HHMMSS.sql.gz`.
- Rotation : seules les **N dernières** sauvegardes sont conservées (paramètre « Nombre de sauvegardes conservées », 14 par défaut).
- Chaque sauvegarde est inscrite au journal d'audit (`sauvegarde.creer`).
- Le chemin de `mysqldump` se règle avec `MYSQLDUMP_PATH` dans `.env`.

## Externalisation (recommandée)

Copiez chaque jour le dossier `storage/app/private/backups` **hors du serveur** : stockage objet, autre serveur, ou disque externe chiffré. Exemple sous Linux, après la sauvegarde :

```bash
rclone copy /chemin/projet/storage/app/private/backups distant:coop-ngoundiane-sauvegardes
```

Les photos et les justificatifs se trouvent dans `storage/app/private/photos` et `storage/app/private/justificatifs` : sauvegardez-les avec la même méthode.

## Restauration

1. Mettre l'application en maintenance :

   ```bash
   php artisan down
   ```

2. Créer une base vide, ou vider la base existante, puis importer la sauvegarde :

   ```bash
   gzip -dc storage/app/private/backups/coop-2026-09-24_020000.sql.gz | mysql -u UTILISATEUR -p coomuni
   ```

3. Restaurer si nécessaire les dossiers `photos/` et `justificatifs/`.
4. Vider les caches puis rouvrir l'application :

   ```bash
   php artisan optimize:clear
   ```

   ```bash
   php artisan up
   ```

## Test de restauration

Cette procédure a été testée à la livraison : restauration dans une base temporaire `coomuni_restauration_test`, puis comparaison des effectifs (406 paiements et 86 membres, identiques à la source).

Répétez ce test **au moins une fois par trimestre** :

```bash
mysql -uroot -e "CREATE DATABASE coomuni_restauration_test"
```

```bash
gzip -dc storage/app/private/backups/coop-DERNIERE.sql.gz | mysql -uroot coomuni_restauration_test
```

```bash
mysql -uroot -e "SELECT COUNT(*) FROM coomuni_restauration_test.paiements; DROP DATABASE coomuni_restauration_test;"
```
