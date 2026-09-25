<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Exceptions\RegleMetierException;
use App\Models\Membre;
use App\Models\MembreStatut;
use App\Models\User;
use App\Services\Notifications\Message;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembreService
{
    public function __construct(
        private Parametres $parametres,
        private CotisationService $cotisations,
        private NotificationService $notifications,
    ) {}

    public static function normaliserTelephone(?string $tel): ?string
    {
        $tel = preg_replace('/[^\d+]/', '', (string) $tel);

        return $tel === '' ? null : $tel;
    }

    public function prochainMatricule(): string
    {
        $prefixe = (string) $this->parametres->requis('matricule_prefixe');
        $max = Membre::where('matricule', 'like', $prefixe.'%')->pluck('matricule')
            ->map(fn ($m) => (int) preg_replace('/\D/', '', substr($m, strlen($prefixe))))
            ->max() ?? 0;

        return $prefixe.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Contrôle anti-doublon (§7.1, §27.2) : matricule et téléphone bloquants ;
     * homonymie (même nom + prénom) signalée comme simple avertissement.
     *
     * @return array{bloquants: array<string,string>, homonymes: \Illuminate\Support\Collection}
     */
    public function verifierDoublons(array $data, ?int $ignorerId = null): array
    {
        $bloquants = [];
        $q = fn () => Membre::query()->when($ignorerId, fn ($q) => $q->where('id', '!=', $ignorerId));

        if (! empty($data['matricule']) && $m = $q()->where('matricule', trim($data['matricule']))->first()) {
            $bloquants['matricule'] = "Ce matricule est déjà attribué à {$m->nom_complet}.";
        }
        $tel = self::normaliserTelephone($data['telephone'] ?? null);
        if ($tel && $m = $q()->where('telephone', $tel)->first()) {
            $bloquants['telephone'] = "Ce numéro de téléphone est déjà utilisé par {$m->nom_complet} ({$m->matricule}).";
        }
        $homonymes = (! empty($data['nom']) && ! empty($data['prenom']))
            ? $q()->where('nom', trim($data['nom']))->where('prenom', trim($data['prenom']))->get()
            : collect();

        return compact('bloquants', 'homonymes');
    }

    public function creer(array $data): Membre
    {
        $doublons = $this->verifierDoublons($data);
        if ($doublons['bloquants']) {
            throw new RegleMetierException(implode(' ', $doublons['bloquants']));
        }

        $membre = DB::transaction(function () use ($data) {
            $membre = Membre::create($this->nettoyer($data) + [
                'statut' => StatutMembre::Actif,
                'cree_par' => Auth::id(),
            ]);
            MembreStatut::create([
                'membre_id' => $membre->id,
                'ancien_statut' => null,
                'nouveau_statut' => StatutMembre::Actif,
                'date_effet' => $membre->date_adhesion,
                'motif' => 'Adhésion',
                'user_id' => Auth::id(),
            ]);

            return $membre;
        });

        Audit::log('membre.creer', $membre, null, $membre->only([
            'matricule', 'nom', 'prenom', 'sexe', 'telephone', 'email', 'fonction', 'service', 'date_adhesion',
        ]), "{$membre->matricule} — {$membre->nom_complet}");

        // Cotisation(s) du mois en cours générée(s) selon la règle d'adhésion (§27.2).
        $this->cotisations->genererPourMembre($membre);

        return $membre;
    }

    public function modifier(Membre $membre, array $data): Membre
    {
        $doublons = $this->verifierDoublons($data, $membre->id);
        if ($doublons['bloquants']) {
            throw new RegleMetierException(implode(' ', $doublons['bloquants']));
        }
        $data = $this->nettoyer($data);
        [$avant, $apres] = Audit::diff($membre->only(array_keys($data)), $data);
        $membre->update($data);

        if ($apres) {
            Audit::log('membre.modifier', $membre, $avant, $apres, "{$membre->matricule} — {$membre->nom_complet}");
        }
        // Le nom affiché du compte utilisateur suit la fiche membre.
        $membre->user?->update(['name' => $membre->nom_complet]);

        return $membre;
    }

    /** Changement de statut historisé (§7.1) avec effets sur les cotisations et le compte. */
    public function changerStatut(Membre $membre, StatutMembre $nouveau, string $dateEffet, string $motif): void
    {
        if (mb_strlen(trim($motif)) < 5) {
            throw new RegleMetierException('Un motif explicite (5 caractères minimum) est obligatoire.');
        }
        if ($membre->statut === $nouveau) {
            throw new RegleMetierException('Le membre a déjà ce statut.');
        }
        if ($dateEffet < $membre->date_adhesion->toDateString()) {
            throw new RegleMetierException('La date d\'effet ne peut pas précéder la date d\'adhésion.');
        }
        $ancien = $membre->statut;

        DB::transaction(function () use ($membre, $nouveau, $dateEffet, $motif, $ancien) {
            $membre->update([
                'statut' => $nouveau,
                'date_sortie' => $nouveau->estDefinitif() ? $dateEffet : null,
            ]);
            MembreStatut::create([
                'membre_id' => $membre->id,
                'ancien_statut' => $ancien,
                'nouveau_statut' => $nouveau,
                'date_effet' => $dateEffet,
                'motif' => $motif,
                'user_id' => Auth::id(),
            ]);
        });

        $annulees = $nouveau->estDefinitif() ? $this->cotisations->appliquerFinAdhesion($membre->fresh()) : 0;
        if ($nouveau === StatutMembre::Actif) {
            $this->cotisations->genererPourMembre($membre->fresh());
        }
        // Décès : le compte d'accès est clôturé (l'historique reste consultable par les gestionnaires).
        if ($nouveau === StatutMembre::Decede && $membre->user?->actif) {
            $membre->user->update(['actif' => false]);
            Audit::log('utilisateur.desactiver', $membre->user, ['actif' => true], ['actif' => false], 'Clôture suite au décès du membre');
        }

        Audit::log('membre.statut', $membre, ['statut' => $ancien->value], [
            'statut' => $nouveau->value, 'date_effet' => $dateEffet, 'motif' => $motif, 'cotisations_annulees' => $annulees,
        ], "{$membre->matricule} — {$membre->nom_complet} : {$ancien->label()} → {$nouveau->label()}");
    }

    /**
     * Ouvre un compte « Membre » lié à la fiche. Retourne le mot de passe
     * temporaire (affiché une seule fois, changement imposé à la 1re connexion).
     *
     * @return array{0: User, 1: string}
     */
    public function creerCompte(Membre $membre): array
    {
        if ($membre->user) {
            throw new RegleMetierException('Ce membre dispose déjà d\'un compte.');
        }
        if ($membre->statut === StatutMembre::Decede) {
            throw new RegleMetierException('Impossible de créer un compte pour un membre décédé.');
        }
        if (User::where('identifiant', $membre->matricule)->exists()) {
            throw new RegleMetierException('L\'identifiant '.$membre->matricule.' est déjà utilisé.');
        }
        $mdp = self::motDePasseTemporaire();

        $user = User::create([
            'name' => $membre->nom_complet,
            'identifiant' => $membre->matricule,
            'email' => $membre->email && ! User::where('email', $membre->email)->exists() ? $membre->email : null,
            'telephone' => $membre->telephone && ! User::where('telephone', $membre->telephone)->exists() ? $membre->telephone : null,
            'password' => $mdp,
            'membre_id' => $membre->id,
            'actif' => true,
            'doit_changer_mdp' => true,
        ]);
        $user->assignRole(User::ROLE_MEMBRE);

        Audit::log('utilisateur.creer', $user, null, [
            'identifiant' => $user->identifiant, 'role' => User::ROLE_MEMBRE, 'membre' => $membre->matricule,
        ], "Compte membre pour {$membre->nom_complet}");

        $this->notifications->envoyer($user, new Message('information', 'Bienvenue',
            'Votre compte de la coopérative a été créé. Vous pouvez consulter vos cotisations et vos reçus.', route('mon-historique')));

        return [$user, $mdp];
    }

    /** Mot de passe temporaire lisible (sans caractères ambigus). */
    public static function motDePasseTemporaire(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $s = '';
        for ($i = 0; $i < 10; $i++) {
            $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return substr($s, 0, 5).'-'.substr($s, 5);
    }

    private function nettoyer(array $data): array
    {
        $champs = ['matricule', 'nom', 'prenom', 'sexe', 'telephone', 'email', 'fonction', 'service', 'date_adhesion', 'observations', 'photo'];
        $out = [];
        foreach ($champs as $c) {
            if (! array_key_exists($c, $data)) {
                continue;
            }
            $v = is_string($data[$c]) ? trim($data[$c]) : $data[$c];
            $out[$c] = $v === '' ? null : $v;
        }
        if (isset($out['nom'])) {
            $out['nom'] = mb_strtoupper($out['nom']);
        }
        if (isset($out['prenom'])) {
            $out['prenom'] = Str::title(mb_strtolower($out['prenom']));
        }
        if (array_key_exists('telephone', $out)) {
            $out['telephone'] = self::normaliserTelephone($out['telephone']);
        }
        if (isset($out['email'])) {
            $out['email'] = mb_strtolower($out['email']);
        }

        return $out;
    }
}
