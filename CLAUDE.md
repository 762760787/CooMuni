# Notes pour les agents de développement

Application Laravel 13 + Livewire 4 de gestion de la coopérative du personnel de la Commune de Ngoundiane.

- Lire `README.md` (installation, écrans, commandes) et `ASSUMPTIONS.md` (règles métier retenues).
- PHP de Laragon : `C:/laragon/bin/php/php-8.3.26-Win32-vs16-x64/php.exe` ; MySQL : `C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin`.
- La logique métier est dans `app/Services` ; les composants Livewire se contentent d'appeler ces services
  et de re-vérifier la permission de chaque action (`AvecRetours::exiger`).
- Aucun paramètre métier codé en dur : utiliser `App\Services\Parametres` (table `parametres`).
- Aucune suppression de données financières : utiliser les annulations tracées (`App\Services\Audit`).
- Tests : `php artisan test` (SQLite en mémoire).
