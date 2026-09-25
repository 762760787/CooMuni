<?php

namespace App\Support;

use App\Models\User;

/**
 * Catalogue des permissions (§6) et affectation par défaut aux rôles.
 * Les affectations sont ensuite modifiables dans l'écran « Rôles et permissions »,
 * sauf pour le rôle Administrateur qui conserve toujours toutes les permissions.
 */
final class CataloguePermissions
{
    public const GROUPES = [
        'Tableau de bord' => [
            'tableau_de_bord.global' => 'Voir les indicateurs globaux de la coopérative',
        ],
        'Assistante IA' => [
            'assistant.ia' => 'Utiliser l\'assistante IA (encaissement en langage naturel)',
        ],
        'Espace personnel' => [
            'espace.personnel' => 'Consulter son historique, son statut et ses reçus',
        ],
        'Membres' => [
            'membres.voir' => 'Consulter la liste et les fiches des membres',
            'membres.creer' => 'Créer un membre',
            'membres.modifier' => 'Modifier un membre',
            'membres.changer_statut' => 'Changer le statut d\'un membre (suspension, sortie, décès)',
        ],
        'Cotisations' => [
            'cotisations.voir' => 'Consulter les cotisations',
            'cotisations.generer' => 'Générer les cotisations d\'un mois',
            'cotisations.corriger' => 'Annuler, régulariser ou rétablir une cotisation',
            'impayes.voir' => 'Suivre les impayés',
            'retards.voir' => 'Suivre les retards',
        ],
        'Paiements et reçus' => [
            'paiements.voir' => 'Consulter les paiements',
            'paiements.creer' => 'Enregistrer un paiement',
            'paiements.demander_annulation' => 'Demander l\'annulation d\'un paiement',
            'paiements.annuler' => 'Annuler un paiement / valider une demande d\'annulation',
            'recus.voir' => 'Consulter tous les reçus',
        ],
        'Opérations financières' => [
            'operations.voir' => 'Consulter les opérations de caisse',
            'operations.creer' => 'Saisir une opération de caisse',
            'operations.categories_restreintes' => 'Saisir dans les catégories réservées',
            'operations.demander_annulation' => 'Demander l\'annulation d\'une opération',
            'operations.annuler' => 'Annuler une opération / valider une demande',
        ],
        'Rapports' => [
            'rapports.voir' => 'Consulter les rapports',
            'rapports.exporter' => 'Exporter les rapports (PDF, Excel, CSV)',
        ],
        'Administration' => [
            'utilisateurs.gerer' => 'Gérer les utilisateurs',
            'roles.gerer' => 'Gérer les rôles et permissions',
            'parametres.gerer' => 'Modifier les paramètres de la coopérative',
            'audit.voir' => 'Consulter le journal d\'audit',
            'notifications.diffuser' => 'Diffuser une information à tous les utilisateurs',
        ],
    ];

    /** Permissions critiques que le rôle Administrateur ne peut jamais perdre. */
    public const VERROUILLEES_ADMIN = ['utilisateurs.gerer', 'roles.gerer', 'parametres.gerer', 'audit.voir'];

    public static function toutes(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::GROUPES)));
    }

    public static function parDefaut(): array
    {
        return [
            User::ROLE_ADMIN => self::toutes(),
            User::ROLE_GESTIONNAIRE => [
                'tableau_de_bord.global', 'espace.personnel', 'assistant.ia',
                'membres.voir', 'membres.creer', 'membres.modifier',
                'cotisations.voir', 'cotisations.generer', 'impayes.voir', 'retards.voir',
                'paiements.voir', 'paiements.creer', 'paiements.demander_annulation', 'recus.voir',
                'operations.voir', 'operations.creer', 'operations.demander_annulation',
                'rapports.voir', 'rapports.exporter',
            ],
            User::ROLE_VERIFICATEUR => [
                'tableau_de_bord.global', 'espace.personnel',
                'membres.voir', 'cotisations.voir', 'impayes.voir', 'retards.voir',
                'paiements.voir', 'recus.voir', 'operations.voir',
                'rapports.voir', 'rapports.exporter', 'audit.voir',
            ],
            User::ROLE_MEMBRE => ['espace.personnel'],
        ];
    }

    public const DESCRIPTIONS_ROLES = [
        User::ROLE_ADMIN => 'Gestion complète : utilisateurs, membres, paramètres, corrections, rapports, audit.',
        User::ROLE_GESTIONNAIRE => 'Trésorier : enregistre cotisations et paiements, suit impayés/retards, saisit les opérations courantes.',
        User::ROLE_VERIFICATEUR => 'Président / vérificateur : consultation étendue en lecture seule, rapports et journal d\'audit.',
        User::ROLE_MEMBRE => 'Consultation de son profil, de son historique de cotisations et de ses reçus.',
    ];
}
