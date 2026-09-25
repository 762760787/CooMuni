#!/usr/bin/env bash
# MISE À JOUR de l'application sur le VPS (code seulement ; .env, fichiers et base conservés).
# Usage :
#   - depuis GitHub : cd /var/www/coop && sudo bash deploy/mettre-a-jour.sh
#   - depuis une archive : sudo bash deploy/mettre-a-jour.sh /tmp/coop-code-XXXX.tar.gz
# Une sauvegarde de la base est faite avant les migrations.
set -euo pipefail

ARCHIVE=${1:-}
[ -z "$ARCHIVE" ] || ARCHIVE=$(readlink -f "$ARCHIVE")
CIBLE=${CIBLE:-/var/www/coop}
WEB_USER=${WEB_USER:-www-data}
# Version de PHP : la plus récente disponible parmi 8.4 / 8.3 (le « php » par défaut du
# serveur peut être plus ancien pour les autres applications : on n'y touche pas).
if [ -z "${PHP:-}" ]; then
    for v in php8.4 php8.3 php; do command -v "$v" >/dev/null 2>&1 && { PHP=$v; break; }; done
fi
# Composer exécuté avec CETTE version de PHP (et non le php par défaut).
COMPOSER="$PHP $(command -v composer || echo composer)"
cd "$CIBLE"

sudo -u "$WEB_USER" "$PHP" artisan down --retry=30 || true
trap 'sudo -u "$WEB_USER" "$PHP" artisan up || true' EXIT

echo "== Sauvegarde de la base avant mise à jour"
sudo -u "$WEB_USER" "$PHP" artisan coop:sauvegarde

echo "== Nouveau code (.env et storage/ conservés)"
if [ -n "$ARCHIVE" ]; then
    tar -xzf "$ARCHIVE" -C "$CIBLE" --exclude='./storage' --exclude='./.env'
else
    git -c safe.directory="$CIBLE" pull --ff-only
fi
COMPOSER_ALLOW_SUPERUSER=1 $COMPOSER install --no-dev --optimize-autoloader --no-interaction

echo "== Migrations et caches"
"$PHP" artisan migrate --force
"$PHP" artisan db:seed --class=Database\\Seeders\\ReferentielsSeeder --force
"$PHP" artisan db:seed --class=Database\\Seeders\\RolesPermissionsSeeder --force
chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache
sudo -u "$WEB_USER" "$PHP" artisan optimize:clear
sudo -u "$WEB_USER" "$PHP" artisan optimize

# Rechargement « en douceur » (graceful) du PHP-FPM de CETTE version : vide l'OPcache
# sans interrompre les requêtes en cours des autres sites.
VERSION=$("$PHP" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
if systemctl is-active --quiet "php$VERSION-fpm"; then
    systemctl reload "php$VERSION-fpm"
fi
echo "== Mise à jour terminée."
