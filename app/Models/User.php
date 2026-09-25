<?php

namespace App\Models;

use App\Models\Concerns\InterditSuppression;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InterditSuppression;

    public const ROLE_ADMIN = 'Administrateur';
    public const ROLE_GESTIONNAIRE = 'Gestionnaire';
    public const ROLE_VERIFICATEUR = 'Vérificateur';
    public const ROLE_MEMBRE = 'Membre';

    protected $fillable = [
        'name', 'identifiant', 'email', 'telephone', 'password', 'membre_id',
        'actif', 'doit_changer_mdp', 'derniere_connexion_at', 'preferences',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'derniere_connexion_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
            'doit_changer_mdp' => 'boolean',
            'preferences' => 'array',
        ];
    }

    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    public function notificationsInternes(): HasMany
    {
        return $this->hasMany(NotificationInterne::class)->latest();
    }

    public function roleNom(): ?string
    {
        return $this->roles->first()?->name;
    }

    public function estAdministrateur(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /** Accès aux indicateurs globaux de la coopérative (et non au seul espace personnel). */
    public function voitDonneesGlobales(): bool
    {
        return $this->can('tableau_de_bord.global');
    }

    public function preference(string $cle, mixed $defaut = null): mixed
    {
        return data_get($this->preferences ?? [], $cle, $defaut);
    }

    public function initiales(): string
    {
        $nom = trim(preg_replace('/\(.*?\)/', '', $this->name));
        $parts = preg_split('/\s+/', $nom) ?: [];

        $premiere = mb_substr($parts[0] ?? '?', 0, 1);

        return mb_strtoupper(count($parts) > 1 ? $premiere.mb_substr(end($parts), 0, 1) : $premiere);
    }

    public function libelleAffiche(): string
    {
        return $this->name.($this->actif ? '' : ' (désactivé)');
    }
}
