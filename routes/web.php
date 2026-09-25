<?php

use App\Http\Controllers\DeconnexionController;
use App\Http\Controllers\FichierController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\RapportExportController;
use App\Http\Controllers\RecuController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

/*
| Chaque route protégée porte son contrôle de permission côté serveur (§6, §13) ;
| les composants Livewire re-vérifient la permission dans chaque action.
*/

// PWA (§12) — accessibles sans authentification.
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/hors-ligne', [PwaController::class, 'horsLigne'])->name('pwa.offline');
Route::get('/logo.png', [PwaController::class, 'logo'])->name('logo');

// 1. Connexion
Route::middleware('guest')->group(function () {
    Route::get('/connexion', Livewire\Auth\Connexion::class)->name('login');
    Route::get('/mot-de-passe-oublie', Livewire\Auth\MotDePasseOublie::class)->name('password.request');
});

Route::middleware(['auth', 'compte.actif'])->group(function () {
    Route::post('/deconnexion', DeconnexionController::class)->name('logout');
    Route::get('/changer-mot-de-passe', Livewire\Auth\ChangerMotDePasse::class)->name('password.change');

    Route::middleware(['mdp.change', 'cotisations.ajour'])->group(function () {
        // 2. Tableau de bord (contenu selon le rôle)
        Route::get('/', Livewire\TableauDeBord::class)->name('dashboard');

        // Assistante IA « Fatou » : encaissement en langage naturel (français / wolof)
        Route::get('/assistant', Livewire\Assistant::class)->name('assistant')->middleware('can:assistant.ia');

        // 19. Profil utilisateur + notifications internes
        Route::get('/profil', Livewire\Profil::class)->name('profil');
        Route::get('/notifications', Livewire\Notifications::class)->name('notifications');

        // Espace personnel du membre (7. historique, 10. reçus)
        Route::get('/mon-historique', Livewire\Cotisations\Historique::class)->name('mon-historique')->middleware('can:espace.personnel');
        Route::get('/recus', Livewire\Recus::class)->name('recus.index');
        Route::get('/recus/{paiement}/pdf', RecuController::class)->name('recus.pdf');

        // 3-5. Membres (la fiche est aussi lisible par le membre concerné : contrôle dans le composant)
        Route::get('/membres', Livewire\Membres\Index::class)->name('membres.index')->middleware('can:membres.voir');
        Route::get('/membres/nouveau', Livewire\Membres\Formulaire::class)->name('membres.create')->middleware('can:membres.creer');
        Route::get('/membres/{membre}', Livewire\Membres\Fiche::class)->name('membres.show');
        Route::get('/membres/{membre}/modifier', Livewire\Membres\Formulaire::class)->name('membres.edit')->middleware('can:membres.modifier');
        Route::get('/membres/{membre}/historique', Livewire\Cotisations\Historique::class)->name('membres.historique');
        Route::get('/membres/{membre}/photo', [FichierController::class, 'photo'])->name('membres.photo');

        // 6. Cotisations du mois
        Route::get('/cotisations', Livewire\Cotisations\Mensuelle::class)->name('cotisations.index')->middleware('can:cotisations.voir');

        // 8-9. Paiements
        Route::get('/paiements', Livewire\Paiements\Index::class)->name('paiements.index')->middleware('can:paiements.voir');
        Route::get('/paiements/nouveau', Livewire\Paiements\Enregistrer::class)->name('paiements.create')->middleware('can:paiements.creer');
        Route::get('/paiements/{paiement}', Livewire\Paiements\Detail::class)->name('paiements.show')->middleware('can:paiements.voir');

        // 11-12. Impayés et retards
        Route::get('/impayes', Livewire\Impayes::class)->name('impayes')->middleware('can:impayes.voir');
        Route::get('/retards', Livewire\Retards::class)->name('retards')->middleware('can:retards.voir');

        // 13. Opérations financières
        Route::get('/operations', Livewire\Operations\Index::class)->name('operations.index')->middleware('can:operations.voir');
        Route::get('/operations/nouvelle', Livewire\Operations\Saisie::class)->name('operations.create')->middleware('can:operations.creer');
        Route::get('/operations/{operation}', Livewire\Operations\Detail::class)->name('operations.show')->middleware('can:operations.voir');
        Route::get('/operations/{operation}/justificatif', [FichierController::class, 'justificatif'])->name('operations.justificatif')->middleware('can:operations.voir');

        // 14. Rapports
        Route::get('/rapports', Livewire\Rapports::class)->name('rapports')->middleware('can:rapports.voir');
        Route::get('/rapports/export', RapportExportController::class)->name('rapports.export')->middleware('can:rapports.exporter');

        // 15-18. Administration
        Route::get('/utilisateurs', Livewire\Utilisateurs::class)->name('utilisateurs')->middleware('can:utilisateurs.gerer');
        Route::get('/roles', Livewire\Roles::class)->name('roles')->middleware('can:roles.gerer');
        Route::get('/parametres', Livewire\Parametres::class)->name('parametres')->middleware('can:parametres.gerer');
        Route::get('/audit', Livewire\JournalAudit::class)->name('audit')->middleware('can:audit.voir');
    });
});
