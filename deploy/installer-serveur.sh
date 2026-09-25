#!/usr/bin/env bash
# PREMIÈRE INSTALLATION sur le VPS de la mairie (Ubuntu/Debian), sans toucher aux autres applications.
# Usage (en tant qu'utilisateur disposant de sudo) :
#   - depuis GitHub (code déjà cloné dans /var/www/coop) :
#       cd /var/www/coop && sudo bash deploy/installer-serveur.sh /tmp/coop-base-XXXX.sql.gz
#   - depuis une archive :
#       sudo bash /tmp/installer-serveur.sh /tmp/coop-code-XXXX.tar.gz /tmp/coop-base-XXXX.sql.gz
# Les arguments sont reconnus par leur extension (.tar.gz = code, .sql.gz = base) ; tous deux facultatifs.
#
# Ce script :
#   - vérifie PHP (>= 8.3 + extensions), Composer et MySQL ;
#   - installe le code dans /var/www/coop (dossier dédié) ;
#   - crée la base et l'utilisateur MySQL DÉDIÉS (coop_ngoundiane) et importe les données ;
#   - prépare .env, droits, caches.
# Il NE modifie PAS la configuration Nginx/Apache ni les autres sites : ces étapes sont
# décrites à la fin et dans docs/DEPLOIEMENT.md, pour être faites en connaissance de cause.
set -euo pipefail

ARCHIVE=""
DUMP=""
for arg in "$@"; do
    case "$arg" in
        *.tar.gz) ARCHIVE=$(readlink -f "$arg") ;;
        *.sql.gz|*.sql) DUMP=$(readlink -f "$arg") ;;
        *) echo "Argument non reconnu : $arg (attendu : *.tar.gz ou *.sql.gz)"; exit 1 ;;
    esac
done
[ -z "$DUMP" ] || [ -f "$DUMP" ] || { echo "Fichier de données introuvable : $DUMP"; exit 1; }
CIBLE=${CIBLE:-/var/www/coop}
BASE=${BASE:-coop_ngoundiane}
UTIL_BD=${UTIL_BD:-coop_ngoundiane}
WEB_USER=${WEB_USER:-www-data}
# Version de PHP : la plus récente disponible parmi 8.4 / 8.3 (le « php » par défaut du
# serveur peut être plus ancien pour les autres applications : on n'y touche pas).
if [ -z "${PHP:-}" ]; then
    for v in php8.4 php8.3 php; do command -v "$v" >/dev/null 2>&1 && { PHP=$v; break; }; done
fi
# Composer exécuté avec CETTE version de PHP (et non le php par défaut).
COMPOSER="$PHP $(command -v composer || echo composer)"
# Commande d'administration MySQL (ex. MYSQL_CMD="mysql -uroot -p" si root a un mot de passe).
MYSQL_CMD=${MYSQL_CMD:-sudo mysql}

echo "== 1. Vérifications (PHP utilisé : $PHP)"
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
    || { echo "PHP 8.3 ou plus est requis (trouvé : $("$PHP" -r 'echo PHP_VERSION;')). Voir docs/DEPLOIEMENT.md."; exit 1; }
MANQUANTES=""
for ext in pdo_mysql mbstring xml ctype fileinfo gd intl zip bcmath curl openssl tokenizer dom; do
    "$PHP" -m | grep -qi "^$ext$" || MANQUANTES="$MANQUANTES $ext"
done
[ -z "$MANQUANTES" ] || { echo "Extensions PHP manquantes :$MANQUANTES (ex. sudo apt install $PHP-{mysql,mbstring,xml,gd,intl,zip,bcmath,curl})"; exit 1; }
command -v composer >/dev/null || { echo "Composer est requis (https://getcomposer.org/download/)."; exit 1; }
command -v mysql >/dev/null || { echo "Client MySQL introuvable."; exit 1; }
$MYSQL_CMD -e "SELECT 1" >/dev/null 2>&1 || { echo "Connexion administrateur MySQL impossible avec « $MYSQL_CMD ». Relancez avec MYSQL_CMD=\"mysql -uroot -p\"."; exit 1; }
[ ! -e "$CIBLE/.env" ] || { echo "$CIBLE/.env existe déjà : application déjà installée, utilisez deploy/mettre-a-jour.sh."; exit 1; }

echo "== 2. Code dans $CIBLE"
if [ -n "$ARCHIVE" ]; then
    [ ! -e "$CIBLE/artisan" ] || { echo "$CIBLE contient déjà du code."; exit 1; }
    mkdir -p "$CIBLE"
    tar -xzf "$ARCHIVE" -C "$CIBLE"
else
    [ -e "$CIBLE/artisan" ] || { echo "Aucun code dans $CIBLE : clonez d'abord le dépôt GitHub (voir docs/DEPLOIEMENT.md)."; exit 1; }
fi
cd "$CIBLE"
# Le site Nginx doit utiliser le PHP-FPM de la même version.
VERSION=$("$PHP" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
sed -i "s#php[0-9.]*-fpm.sock#php$VERSION-fpm.sock#" deploy/nginx/coop.ngoundiane.sn.conf deploy/apache/coop.ngoundiane.sn.conf
[ -f public/build/manifest.json ] || { echo "Ressources compilées absentes (public/build) : lancez « npm ci && npm run build » ou utilisez l'archive."; exit 1; }
COMPOSER_ALLOW_SUPERUSER=1 $COMPOSER install --no-dev --optimize-autoloader --no-interaction

echo "== 3. Base de données dédiée « $BASE »"
MDP_BD=$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)
$MYSQL_CMD <<SQL
CREATE DATABASE IF NOT EXISTS \`$BASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$UTIL_BD'@'localhost' IDENTIFIED BY '$MDP_BD';
ALTER USER '$UTIL_BD'@'localhost' IDENTIFIED BY '$MDP_BD';
GRANT ALL PRIVILEGES ON \`$BASE\`.* TO '$UTIL_BD'@'localhost';
FLUSH PRIVILEGES;
SQL
if [ -n "$DUMP" ]; then
    echo "   Import des données : $DUMP"
    gzip -dcf "$DUMP" | $MYSQL_CMD "$BASE"
fi

echo "== 4. Fichier .env"
cp deploy/env.production.example .env
sed -i "s#^DB_DATABASE=.*#DB_DATABASE=$BASE#; s#^DB_USERNAME=.*#DB_USERNAME=$UTIL_BD#; s#^DB_PASSWORD=.*#DB_PASSWORD=$MDP_BD#" .env
sed -i "s#^MYSQLDUMP_PATH=.*#MYSQLDUMP_PATH=$(command -v mysqldump)#" .env
"$PHP" artisan key:generate --force
chmod 640 .env

echo "== 5. Migrations, référentiels, caches"
"$PHP" artisan migrate --force
"$PHP" artisan db:seed --class=Database\\Seeders\\ReferentielsSeeder --force
"$PHP" artisan db:seed --class=Database\\Seeders\\RolesPermissionsSeeder --force
if [ -z "$DUMP" ]; then
    "$PHP" artisan db:seed --class=Database\\Seeders\\AdministrateurSeeder --force
    "$PHP" artisan db:seed --class=Database\\Seeders\\MembresSeeder --force
fi
mkdir -p storage/app/private/backups storage/framework/{cache/data,sessions,views} storage/logs
chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache
chown root:"$WEB_USER" .env
chmod -R ug+rwX storage bootstrap/cache
sudo -u "$WEB_USER" "$PHP" artisan optimize

if [ -n "$DUMP" ]; then
    echo
    echo "== 6. Nouveaux mots de passe temporaires (ceux du développement ne sont plus valables)"
    "$PHP" artisan coop:nouveaux-mots-de-passe --force
fi

echo
echo "== Installation terminée dans $CIBLE."
echo "Étapes suivantes (docs/DEPLOIEMENT.md) :"
echo "  1. DNS : enregistrement A « coop.ngoundiane.sn » vers l'IP de ce serveur."
echo "  2. Site web : copier deploy/nginx/coop.ngoundiane.sn.conf (ou deploy/apache/…), vérifier (nginx -t) puis recharger."
echo "  3. HTTPS : sudo certbot --nginx -d coop.ngoundiane.sn"
echo "  4. Tâches planifiées : sudo crontab -u $WEB_USER -e  →  * * * * * cd $CIBLE && $PHP artisan schedule:run >> /dev/null 2>&1"
[ -z "$DUMP" ] || echo "  5. Supprimer le fichier de données importé : rm -f $DUMP"
