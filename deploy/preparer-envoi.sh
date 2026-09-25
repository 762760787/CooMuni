#!/usr/bin/env bash
# À lancer SUR CET ORDINATEUR (Git Bash), depuis la racine du projet :
#   bash deploy/preparer-envoi.sh
# Produit dans deploy/sortie/ :
#   - coop-code-AAAAMMJJ-HHMM.tar.gz : le code de l'application (sans secrets ni dépendances)
#   - coop-base-AAAAMMJJ-HHMM.sql.gz : la base réelle (membres, comptes, paramètres) — DONNÉES PERSONNELLES
set -euo pipefail

cd "$(dirname "$0")/.."
HORODATAGE=$(date +%Y%m%d-%H%M)
SORTIE=deploy/sortie
mkdir -p "$SORTIE"

PHP=${PHP:-/c/laragon/bin/php/php-8.3.26-Win32-vs16-x64/php.exe}
MYSQLDUMP=${MYSQLDUMP:-/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe}
BASE=${BASE:-coomuni}

echo "1/3 Compilation des ressources (CSS/JS)…"
npm run build >/dev/null

echo "2/3 Archive du code…"
tar -czf "$SORTIE/coop-code-$HORODATAGE.tar.gz" \
    --exclude='./.env' --exclude='./.env.*' --exclude='./node_modules' --exclude='./vendor' \
    --exclude='./.git' --exclude='./.claude' --exclude='./deploy/sortie' \
    --exclude='./storage/logs/*' --exclude='./storage/framework/cache/data/*' \
    --exclude='./storage/framework/sessions/*' --exclude='./storage/framework/views/*' \
    --exclude='./storage/app/private/*' --exclude='./public/storage' \
    --exclude='./Cahier_des_charges_Cooperative_Ngoundiane.docx' \
    .

echo "3/3 Export de la base « $BASE »…"
"$MYSQLDUMP" -uroot --single-transaction --routines --no-tablespaces --default-character-set=utf8mb4 \
    --ignore-table="$BASE.sessions" --ignore-table="$BASE.cache" --ignore-table="$BASE.cache_locks" \
    "$BASE" | gzip > "$SORTIE/coop-base-$HORODATAGE.sql.gz"

echo
echo "Fichiers prêts dans $SORTIE :"
ls -lh "$SORTIE" | tail -n +2
echo
echo "Envoi vers le VPS (remplacer UTILISATEUR et ADRESSE_DU_VPS) :"
echo "  scp $SORTIE/coop-code-$HORODATAGE.tar.gz $SORTIE/coop-base-$HORODATAGE.sql.gz deploy/installer-serveur.sh UTILISATEUR@ADRESSE_DU_VPS:/tmp/"
echo "⚠ Le fichier coop-base-*.sql.gz contient des données personnelles : ne le partagez pas et supprimez-le après import."
