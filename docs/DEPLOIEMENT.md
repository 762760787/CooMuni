# Déploiement sur le VPS de la mairie (ngoundiane.sn)

Objectif : publier l'application sur **https://coop.ngoundiane.sn**, à côté des applications déjà hébergées dans `/var/www/`, **sans les perturber** :

- dossier dédié `/var/www/coop` ;
- hôte virtuel dédié (un seul fichier ajouté à la configuration du serveur web) ;
- base MySQL et utilisateur MySQL dédiés (`coop_ngoundiane`) ;
- configuration toujours vérifiée (`nginx -t` / `apachectl configtest`) avant tout rechargement ;
- aucun redémarrage brutal : uniquement des rechargements « en douceur » (`reload`).

## 0. Prérequis sur le VPS (à vérifier, sans rien modifier)

```bash
php -v
```

```bash
ls /run/php/
```

```bash
composer -V
```

```bash
mysql --version
```

```bash
sudo nginx -v || apache2 -v
```

```bash
ls /var/www/
```

- **PHP 8.3 ou plus** avec les extensions `mysql, mbstring, xml, gd, intl, zip, bcmath, curl`. Si le serveur est en PHP 8.1 ou 8.2 pour les autres applications, **installez PHP 8.3 à côté** (`php8.3-fpm`) : chaque application garde sa propre version.
- **Composer 2**, **MySQL / MariaDB**, **certbot** (HTTPS Let's Encrypt).

## 1. DNS

Chez le gestionnaire du domaine `ngoundiane.sn`, ajoutez un enregistrement **A** `coop` pointant vers l'adresse IP du VPS (même IP que les autres applications). Vérification :

```bash
nslookup coop.ngoundiane.sn
```

## 2. Récupérer le code depuis GitHub (sur le VPS)

Le dépôt https://github.com/762760787/CooMuni contient le code et les ressources déjà compilées (`public/build`) : Node.js n'est pas nécessaire sur le serveur. Il ne contient **aucune donnée personnelle ni aucun secret** (ni `.env`, ni liste du personnel, ni export de base).

```bash
sudo git clone https://github.com/762760787/CooMuni.git /var/www/coop
```

## 3. Envoyer la base réelle (depuis l'ordinateur de la coopérative)

La base (82 membres, comptes, paramètres) ne passe **pas** par GitHub. Sur l'ordinateur Windows, dans PowerShell :

```bash
scp C:\laragon\www\CooMuni\deploy\sortie\coop-base-XXXX.sql.gz UTILISATEUR@IP_DU_VPS:/tmp/coop-base.sql.gz
```

(Pour produire un export à jour : `bash deploy/preparer-envoi.sh` dans Git Bash.)

## 3 bis. Installer (sur le VPS)

```bash
cd /var/www/coop && sudo bash deploy/installer-serveur.sh /tmp/coop-base.sql.gz
```

Si l'administrateur MySQL a un mot de passe (et non l'accès par `sudo mysql`) :

```bash
cd /var/www/coop && sudo MYSQL_CMD="mysql -uroot -p" bash deploy/installer-serveur.sh /tmp/coop-base.sql.gz
```

Le script crée la base et son utilisateur (mot de passe aléatoire écrit dans `/var/www/coop/.env`), importe les données, génère la clé de l'application, règle les droits et les caches, puis **affiche de nouveaux mots de passe temporaires** pour `admin`, `tresorier` et `verificateur` : notez-les, ils ne seront plus affichés (sinon : `sudo php8.3 artisan coop:nouveaux-mots-de-passe`).

## 4. Brancher le site web

**Avec Nginx :**

```bash
sudo cp /var/www/coop/deploy/nginx/coop.ngoundiane.sn.conf /etc/nginx/sites-available/
```

```bash
sudo ln -s /etc/nginx/sites-available/coop.ngoundiane.sn.conf /etc/nginx/sites-enabled/
```

```bash
sudo nginx -t
```

Seulement si `nginx -t` répond « syntax is ok » :

```bash
sudo systemctl reload nginx
```

**Avec Apache :**

```bash
sudo cp /var/www/coop/deploy/apache/coop.ngoundiane.sn.conf /etc/apache2/sites-available/
```

```bash
sudo a2ensite coop.ngoundiane.sn
```

```bash
sudo apachectl configtest
```

Seulement si le test répond « Syntax OK » :

```bash
sudo systemctl reload apache2
```

Vérifiez dans le fichier que la ligne `php8.3-fpm.sock` correspond à la version présente dans `/run/php/`.

## 5. HTTPS

```bash
sudo certbot --nginx -d coop.ngoundiane.sn
```

Pour Apache : `sudo certbot --apache -d coop.ngoundiane.sn`. Certbot ajoute le bloc HTTPS et la redirection, et le renouvellement est automatique.

## 6. Tâches planifiées (génération mensuelle, rappels, sauvegarde de nuit)

```bash
sudo crontab -u www-data -e
```

Ajoutez la ligne :

```
* * * * * cd /var/www/coop && php8.3 artisan schedule:run >> /dev/null 2>&1
```

## 7. Vérifications finales

- Ouvrir https://coop.ngoundiane.sn et se connecter avec `admin` et le mot de passe temporaire affiché à l'installation. Le changement de mot de passe est imposé.
- *Paramètres* : vérifier le premier mois de cotisation (**octobre 2026**) et le nom de la coopérative.
- Sur un téléphone : ouvrir le site, **installer l'application** (menu du navigateur › « Installer »), tester le micro de Fatou.
- Tester la sauvegarde :

  ```bash
  sudo -u www-data php8.3 /var/www/coop/artisan coop:sauvegarde
  ```

- Supprimer l'export de données envoyé :

  ```bash
  rm -f /tmp/coop-base.sql.gz
  ```

- Vérifier que les **6 autres applications** répondent toujours normalement.

## Mises à jour ultérieures

Après chaque `git push` des modifications vers GitHub, sur le VPS :

```bash
cd /var/www/coop && sudo bash deploy/mettre-a-jour.sh
```

La mise à jour passe l'application en maintenance, sauvegarde la base, récupère le code (`git pull`) en conservant `.env` et les fichiers, applique les migrations, puis recharge en douceur le PHP-FPM de la bonne version.

(Sans GitHub, une archive reste possible : `bash deploy/preparer-envoi.sh`, puis `sudo bash deploy/mettre-a-jour.sh /tmp/coop-code-XXXX.tar.gz`.)

## Sauvegardes

Sauvegarde quotidienne à 2 h dans `/var/www/coop/storage/app/private/backups` (14 versions conservées). **Recommandé** : copier ce dossier chaque jour vers un autre serveur ou un stockage externe (voir `docs/SAUVEGARDE.md`).
