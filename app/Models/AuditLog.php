<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Journal d'audit : en ajout seul. Toute modification ou suppression est refusée.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'cible_type', 'cible_id', 'description',
        'ancienne_valeur', 'nouvelle_valeur', 'ip', 'user_agent', 'date_action',
    ];

    protected function casts(): array
    {
        return [
            'ancienne_valeur' => 'array',
            'nouvelle_valeur' => 'array',
            'date_action' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal d\'audit ne peut pas être modifié.'));
        static::deleting(fn () => throw new LogicException('Le journal d\'audit ne peut pas être supprimé.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Libellés lisibles des actions pour l'écran du journal. */
    public const LIBELLES = [
        'auth.connexion' => 'Connexion',
        'auth.echec' => 'Échec de connexion',
        'auth.deconnexion' => 'Déconnexion',
        'auth.blocage' => 'Connexion bloquée (trop de tentatives)',
        'auth.mdp_modifie' => 'Mot de passe modifié',
        'auth.demande_reinitialisation' => 'Demande de réinitialisation du mot de passe',
        'membre.creer' => 'Création de membre',
        'membre.modifier' => 'Modification de membre',
        'membre.statut' => 'Changement de statut de membre',
        'membre.import' => 'Import de membres',
        'cotisation.generer' => 'Génération des cotisations',
        'cotisation.annuler' => 'Annulation de cotisation',
        'cotisation.regulariser' => 'Régularisation de cotisation',
        'cotisation.retablir' => 'Rétablissement de cotisation',
        'paiement.creer' => 'Enregistrement de paiement',
        'paiement.demande_annulation' => 'Demande d\'annulation de paiement',
        'paiement.rejet_annulation' => 'Rejet de demande d\'annulation',
        'paiement.annuler' => 'Annulation de paiement',
        'recu.telecharger' => 'Consultation / téléchargement de reçu',
        'operation.creer' => 'Saisie d\'opération financière',
        'operation.demande_annulation' => 'Demande d\'annulation d\'opération',
        'operation.rejet_annulation' => 'Rejet de demande d\'annulation d\'opération',
        'operation.annuler' => 'Annulation d\'opération financière',
        'operation.justificatif' => 'Consultation de justificatif',
        'rapport.exporter' => 'Export de rapport',
        'utilisateur.creer' => 'Création d\'utilisateur',
        'utilisateur.modifier' => 'Modification d\'utilisateur',
        'utilisateur.activer' => 'Activation d\'utilisateur',
        'utilisateur.desactiver' => 'Désactivation d\'utilisateur',
        'utilisateur.reinitialiser_mdp' => 'Réinitialisation de mot de passe',
        'role.creer' => 'Création de rôle',
        'role.permissions' => 'Modification des permissions d\'un rôle',
        'parametre.modifier' => 'Modification de paramètre',
        'categorie.creer' => 'Création de catégorie',
        'categorie.modifier' => 'Modification de catégorie',
        'mode_paiement.creer' => 'Création de mode de paiement',
        'mode_paiement.modifier' => 'Modification de mode de paiement',
        'notification.diffuser' => 'Diffusion d\'une information',
        'sauvegarde.creer' => 'Sauvegarde de la base',
        'systeme.remise_a_zero' => 'Remise à zéro (passage en base réelle)',
        'ia.commande' => 'Demande à l\'assistante IA',
    ];

    public function libelleAction(): string
    {
        return self::LIBELLES[$this->action] ?? $this->action;
    }
}
