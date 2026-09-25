<?php

namespace App\Support;

use App\Models\User;

/**
 * Menu de l'application, filtré selon les permissions réelles de l'utilisateur.
 * (Le masquage d'un lien n'est qu'un confort : chaque route est protégée côté serveur.)
 */
final class Navigation
{
    /** @return array<string, list<array{label:string, route:string, icone:string, actif:string}>> */
    public static function sections(User $user): array
    {
        $a = fn (string $label, string $route, string $icone, bool $visible, ?string $actif = null) => $visible
            ? ['label' => $label, 'route' => $route, 'icone' => $icone, 'actif' => $actif ?? $route]
            : null;

        $perso = $user->can('espace.personnel') && $user->membre_id;
        $sections = [
            'Principal' => [
                $a('Tableau de bord', 'dashboard', 'accueil', true),
                $a(parametre('ia_nom', 'Fatou').' (assistante IA)', 'assistant', 'etincelles', $user->can('assistant.ia')),
                $a('Mon historique', 'mon-historique', 'calendrier', (bool) $perso),
                $a($user->can('recus.voir') ? 'Reçus' : 'Mes reçus', 'recus.index', 'recu', $user->can('recus.voir') || $perso),
            ],
            'Gestion' => [
                $a('Cotisations du mois', 'cotisations.index', 'calendrier', $user->can('cotisations.voir')),
                $a('Membres', 'membres.index', 'membres', $user->can('membres.voir'), 'membres.*'),
                $a('Paiements', 'paiements.index', 'billet', $user->can('paiements.voir'), 'paiements.*'),
                $a('Impayés', 'impayes', 'alerte', $user->can('impayes.voir')),
                $a('Retards', 'retards', 'horloge', $user->can('retards.voir')),
                $a('Opérations financières', 'operations.index', 'caisse', $user->can('operations.voir'), 'operations.*'),
                $a('Rapports', 'rapports', 'rapport', $user->can('rapports.voir')),
            ],
            'Administration' => [
                $a('Utilisateurs', 'utilisateurs', 'utilisateurs', $user->can('utilisateurs.gerer')),
                $a('Rôles et permissions', 'roles', 'bouclier', $user->can('roles.gerer')),
                $a('Paramètres', 'parametres', 'reglages', $user->can('parametres.gerer')),
                $a('Journal d\'audit', 'audit', 'journal', $user->can('audit.voir')),
            ],
            'Mon compte' => [
                $a('Notifications', 'notifications', 'cloche', true),
                $a('Mon profil', 'profil', 'profil', true),
            ],
        ];

        return array_filter(array_map(fn ($items) => array_values(array_filter($items)), $sections));
    }

    /** Barre de navigation inférieure mobile : 4 raccourcis prioritaires selon le rôle (§11.2). */
    public static function barreMobile(User $user): array
    {
        $priorites = $user->voitDonneesGlobales()
            ? ['dashboard', 'assistant', 'cotisations.index', 'membres.index', 'impayes', 'rapports', 'audit']
            : ['dashboard', 'mon-historique', 'recus.index', 'notifications'];

        $tous = collect(self::sections($user))->flatten(1)->keyBy('route');
        $courts = [
            'dashboard' => 'Accueil', 'assistant' => parametre('ia_nom', 'Fatou'), 'cotisations.index' => 'Cotisations', 'membres.index' => 'Membres',
            'impayes' => 'Impayés', 'rapports' => 'Rapports', 'audit' => 'Audit', 'mon-historique' => 'Historique',
            'recus.index' => 'Reçus', 'notifications' => 'Alertes',
        ];

        return collect($priorites)->filter(fn ($r) => $tous->has($r))->take(4)
            ->map(fn ($r) => array_merge($tous[$r], ['label' => $courts[$r] ?? $tous[$r]['label']]))->values()->all();
    }
}
