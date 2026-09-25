<?php

namespace Database\Seeders;

use App\Enums\StatutMembre;
use App\Models\Categorie;
use App\Models\Cotisation;
use App\Models\Membre;
use App\Models\ModePaiement;
use App\Models\OperationFinanciere;
use App\Models\Paiement;
use App\Models\User;
use App\Services\CotisationService;
use App\Services\MembreService;
use App\Services\Notifications\Message;
use App\Services\Notifications\NotificationService;
use App\Services\OperationService;
use App\Services\PaiementService;
use App\Support\Periode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

/**
 * Données de DÉMONSTRATION (fictives) : comptes de test, 4 membres fictifs
 * (matricules NGD-9xx), historique de cotisations/paiements de mai à
 * aujourd'hui, opérations de caisse, annulations et demandes en attente.
 *
 * Toutes les écritures passent par les services métier : les règles, la
 * numérotation et le journal d'audit sont donc ceux de l'application réelle.
 * Ne pas exécuter en production (utiliser ProductionSeeder).
 */
class DemoSeeder extends Seeder
{
    private User $admin;
    private User $tresorier;
    private User $ancienTresorier;
    private string $finMandatAncien;
    private array $modes;

    public function run(): void
    {
        mt_srand(2026);
        $fin = CarbonImmutable::now();

        $this->creerComptes();
        $this->modes = ModePaiement::pluck('id', 'code')->all();

        $cotisations = app(CotisationService::class);
        $paiements = app(PaiementService::class);
        $membresService = app(MembreService::class);

        $debut = Periode::fromString(parametre('cotisation_periode_debut'));
        $courante = Periode::fromDate($fin);
        $this->finMandatAncien = $debut->suivante()->premierJour()->toDateString();

        // Membres fictifs illustrant les cas particuliers (adhésion en cours de mois, suspension, sortie).
        $this->instant($debut->premierJour()->setTime(9, 0));
        Auth::setUser($this->admin);
        $demo = [];
        foreach ([
            ['NGD-901', 'DÉMO', 'Awa', 'F', '770000901', 'Secrétaire', 'Secrétariat général', $debut->premierJour()],
            ['NGD-902', 'EXEMPLE', 'Moussa', 'H', '770000902', 'Agent de voirie', 'Services techniques', $debut->premierJour()],
            ['NGD-903', 'TEST', 'Fatou', 'F', '770000903', 'Agent d\'état civil', 'État civil', $debut->premierJour()],
        ] as [$mat, $nom, $prenom, $sexe, $tel, $fonction, $service, $adhesion]) {
            $demo[$mat] = $membresService->creer(compact('nom', 'prenom', 'sexe', 'fonction', 'service') + [
                'matricule' => $mat, 'telephone' => $tel, 'date_adhesion' => $adhesion->toDateString(),
                'observations' => 'Membre fictif (données de démonstration).',
            ]);
        }

        // Compte « membre » de démonstration rattaché à Awa DÉMO.
        User::create([
            'name' => $demo['NGD-901']->nom_complet, 'identifiant' => 'membre', 'telephone' => '770000901',
            'password' => 'Membre@2026', 'membre_id' => $demo['NGD-901']->id, 'actif' => true,
        ])->assignRole(User::ROLE_MEMBRE);

        // Profils de paiement déterministes.
        $membres = Membre::orderBy('id')->get();
        $profils = [];
        foreach ($membres as $m) {
            $r = mt_rand(1, 100);
            $profils[$m->id] = match (true) {
                $m->matricule === 'NGD-901' => 'ponctuel',
                $r <= 58 => 'ponctuel',
                $r <= 82 => 'retardataire',
                $r <= 93 => 'mauvais',
                default => 'partiel',
            };
        }

        // Planification des événements, exécutés ensuite dans l'ordre chronologique.
        $evenements = [];
        foreach (Periode::plage($debut, $courante) as $p) {
            $evenements[] = [$p->premierJour()->setTime(0, 15), 'generer', $p];
            $estCourante = $p->compare($courante) === 0;
            foreach ($membres as $m) {
                foreach ($this->planifier($profils[$m->id], $p, $estCourante, $fin) as $e) {
                    $evenements[] = [$e['date'], 'payer', $m, $e['periodes'], $e['montant']];
                }
            }
        }
        // Cas particuliers
        $evenements[] = [CarbonImmutable::parse($this->finMandatAncien)->setTime(9, 0), 'fin_mandat'];
        $evenements[] = [$debut->ajouterMois(2)->premierJour()->setTime(8, 0)->addDays(14), 'statut', $demo['NGD-902'], StatutMembre::Suspendu, 'Mise en disponibilité (demande de l\'agent)'];
        $evenements[] = [$debut->ajouterMois(3)->premierJour()->setTime(8, 0)->addDays(19), 'statut', $demo['NGD-903'], StatutMembre::Sorti, 'Mutation dans une autre collectivité'];
        usort($evenements, fn ($a, $b) => $a[0] <=> $b[0]);

        foreach ($evenements as $e) {
            if ($e[0]->gt($fin)) {
                continue;
            }
            $this->instant($e[0]);
            match ($e[1]) {
                'generer' => $cotisations->genererPeriode($e[2]),
                'statut' => (function () use ($e, $membresService) {
                    Auth::setUser($this->admin);
                    $membresService->changerStatut($e[2]->fresh(), $e[3], $e[0]->toDateString(), $e[4]);
                })(),
                'payer' => $this->payer($paiements, $e[2], $e[3], $e[4]),
                'fin_mandat' => (function () {
                    Auth::setUser($this->admin);
                    $this->ancienTresorier->update(['actif' => false]);
                    \App\Services\Audit::log('utilisateur.desactiver', $this->ancienTresorier, ['actif' => true], ['actif' => false], 'Fin de mandat de l\'ancien trésorier');
                })(),
            };
        }

        // Adhésion en cours de mois après l'échéance : première cotisation au mois suivant.
        $this->instant($courante->echeance(12)->setTime(10, 0));
        Auth::setUser($this->tresorier);
        $membresService->creer([
            'matricule' => 'NGD-904', 'nom' => 'FICTIF', 'prenom' => 'Ibrahima', 'sexe' => 'H', 'telephone' => '770000904',
            'fonction' => 'Chauffeur', 'service' => 'Parc automobile', 'date_adhesion' => $courante->echeance(12)->toDateString(),
            'observations' => 'Membre fictif (démonstration) : adhésion le 12, après l\'échéance → premier mois dû le mois suivant.',
        ]);

        $this->casCorrectionEtAnnulation($paiements, $cotisations, $courante);
        $this->operationsFinancieres($debut, $fin);

        // Information diffusée par l'administrateur + rappels d'échéance.
        $this->instant($fin->subDays(2)->setTime(9, 30));
        Auth::setUser($this->admin);
        app(NotificationService::class)->envoyer(User::where('actif', true)->get(), new Message(
            'information', 'Assemblée générale',
            'L\'assemblée générale de la coopérative se tiendra le dernier vendredi du mois à 16h, salle de délibération de la mairie.',
        ));
        \App\Services\Audit::log('notification.diffuser', 'NotificationInterne', null, ['titre' => 'Assemblée générale'], 'Information diffusée à tous les utilisateurs');

        CarbonImmutable::setTestNow();
        \Illuminate\Support\Carbon::setTestNow();
        Auth::forgetUser();
        Artisan::call('coop:rappels');
    }

    private function creerComptes(): void
    {
        $this->admin = User::create([
            'name' => 'Administrateur (démo)', 'identifiant' => 'admin', 'email' => 'admin@coop-ngoundiane.sn',
            'password' => 'Admin@2026', 'actif' => true,
        ]);
        $this->admin->assignRole(User::ROLE_ADMIN);

        $this->tresorier = User::create([
            'name' => 'Trésorier (démo)', 'identifiant' => 'tresorier', 'email' => 'tresorier@coop-ngoundiane.sn',
            'password' => 'Tresor@2026', 'actif' => true,
        ]);
        $this->tresorier->assignRole(User::ROLE_GESTIONNAIRE);

        User::create([
            'name' => 'Président / Vérificateur (démo)', 'identifiant' => 'verificateur', 'email' => 'verificateur@coop-ngoundiane.sn',
            'password' => 'Verif@2026', 'actif' => true,
        ])->assignRole(User::ROLE_VERIFICATEUR);

        // Ancien trésorier : saisit les paiements du premier mois, puis son compte est désactivé.
        // Ses actions passées restent visibles dans l'historique et l'audit (§8.2).
        $this->ancienTresorier = User::create([
            'name' => 'Ancien trésorier (démo)', 'identifiant' => 'ancien.tresorier',
            'password' => \App\Services\MembreService::motDePasseTemporaire(), 'actif' => true,
        ]);
        $this->ancienTresorier->assignRole(User::ROLE_GESTIONNAIRE);
    }

    /** @return list<array{date: CarbonImmutable, periodes: list<string>, montant: int}> */
    private function planifier(string $profil, Periode $p, bool $courante, CarbonImmutable $fin): array
    {
        $montant = (int) parametre('cotisation_montant');
        $jourMax = $courante ? max(1, (int) $fin->format('j') - 1) : 28;
        $aTemps = fn () => $p->premierJour()->addDays(mt_rand(0, 4))->setTime(mt_rand(8, 16), mt_rand(0, 59));
        $enRetard = fn (int $min = 6) => $p->premierJour()->addDays(mt_rand($min, max($min, $jourMax)) - 1)->setTime(mt_rand(8, 16), mt_rand(0, 59));
        $un = fn ($date, $m = null) => [['date' => $date, 'periodes' => [(string) $p], 'montant' => $m ?? $montant]];

        return match ($profil) {
            'ponctuel' => mt_rand(1, 100) <= ($courante ? 80 : 92) ? $un($aTemps()) : ($courante && mt_rand(1, 2) === 1 ? [] : $un($enRetard())),
            'retardataire' => match (true) {
                mt_rand(1, 100) <= 45 => $un($aTemps()),
                $courante && mt_rand(1, 100) <= 50 => [],
                default => $un($enRetard()),
            },
            // Laisse un mois sur deux impayé puis règle deux mois d'un coup, en retard.
            'mauvais' => match (true) {
                $p->mois % 2 === 0 => [],
                $courante => [],
                default => [['date' => $enRetard(10), 'periodes' => array_values(array_filter([(string) $p->precedente(), (string) $p],
                    fn ($x) => $x >= parametre('cotisation_periode_debut'))), 'montant' => 0]],
            },
            // Verse la moitié à temps, le solde plus tard (sauf le mois courant).
            'partiel' => $courante
                ? [['date' => $aTemps(), 'periodes' => [(string) $p], 'montant' => intdiv($montant, 2)]]
                : [['date' => $aTemps(), 'periodes' => [(string) $p], 'montant' => intdiv($montant, 2)],
                    ['date' => $enRetard(12), 'periodes' => [(string) $p], 'montant' => $montant - intdiv($montant, 2)]],
        };
    }

    private function payer(PaiementService $service, Membre $membre, array $periodes, int $montant): void
    {
        $membre = $membre->fresh();
        $dues = Cotisation::where('membre_id', $membre->id)->whereIn('periode', $periodes)->dues()->get();
        if ($dues->isEmpty()) {
            return;
        }
        $montant = $montant > 0 ? min($montant, $dues->sum('reste')) : $dues->sum('reste');
        $r = mt_rand(1, 100);
        $code = $r <= 50 ? 'especes' : ($r <= 75 ? 'wave' : ($r <= 95 ? 'orange_money' : 'virement'));
        Auth::setUser(match (true) {
            now()->toDateString() < $this->finMandatAncien => $this->ancienTresorier,
            mt_rand(1, 100) <= 85 => $this->tresorier,
            default => $this->admin,
        });

        $service->enregistrer([
            'membre_id' => $membre->id,
            'periodes' => $dues->pluck('periode')->all(),
            'montant' => $montant,
            'date_paiement' => now()->toDateString(),
            'mode_paiement_id' => $this->modes[$code],
            'reference' => $code === 'especes' ? null : strtoupper(substr($code, 0, 2)).'-'.now()->format('ymd').'-'.mt_rand(100000, 999999),
            'confirmer_doublon' => true,
        ]);
    }

    private function casCorrectionEtAnnulation(PaiementService $paiements, CotisationService $cotisations, Periode $courante): void
    {
        // 1) Erreur de saisie de montant : annulation par l'administrateur puis nouvelle saisie liée.
        $cotis = Cotisation::where('periode', (string) $courante)->dues()->where('montant_paye', 0)
            ->whereHas('membre', fn ($q) => $q->where('matricule', '<', 'NGD-900'))->orderBy('membre_id')->first();
        $cible = $cotis?->membre;
        if ($cotis) {
            $jour = CarbonImmutable::now()->subDays(3)->setTime(10, 5);
            $this->instant($jour);
            Auth::setUser($this->tresorier);
            $errone = $paiements->enregistrer([
                'membre_id' => $cible->id, 'periodes' => [$cotis->periode], 'montant' => 1000,
                'date_paiement' => $jour->toDateString(), 'mode_paiement_id' => $this->modes['especes'],
                'note' => 'Saisie erronée (démonstration)', 'confirmer_doublon' => true,
            ]);
            $this->instant($jour->addMinutes(20));
            $paiements->demanderAnnulation($errone, 'Montant saisi 1 000 au lieu de 10 000 FCFA');
            $this->instant($jour->addHours(2));
            Auth::setUser($this->admin);
            $paiements->annuler($errone, 'Validation : erreur de saisie du montant (1 000 au lieu de 10 000)');
            $this->instant($jour->addHours(2)->addMinutes(10));
            Auth::setUser($this->tresorier);
            $paiements->enregistrer([
                'membre_id' => $cible->id, 'periodes' => [$cotis->periode], 'montant' => 10000,
                'date_paiement' => $jour->toDateString(), 'mode_paiement_id' => $this->modes['especes'],
                'note' => 'Correction du reçu '.$errone->numero_recu, 'corrige_paiement_id' => $errone->id, 'confirmer_doublon' => true,
            ]);
        }

        // 2) Demande d'annulation en attente de validation par l'administrateur.
        $this->instant(CarbonImmutable::now()->subDay()->setTime(15, 30));
        Auth::setUser($this->tresorier);
        $p = Paiement::valides()->whereDate('date_paiement', '>=', CarbonImmutable::now()->subDays(10)->toDateString())
            ->whereNull('corrige_paiement_id')->where('membre_id', '!=', $cible?->id)->orderByDesc('id')->first();
        if ($p) {
            $paiements->demanderAnnulation($p, 'Le membre signale avoir été enregistré deux fois (à vérifier)');
        }

        // 3) Régularisation (exonération décidée par le bureau).
        $this->instant(CarbonImmutable::now()->subDays(5)->setTime(11, 0));
        Auth::setUser($this->admin);
        $c = Cotisation::impayees()->where('periode', '<', (string) $courante)->orderBy('periode')->first();
        if ($c) {
            $cotisations->regulariser($c, 'Exonération accordée par le bureau (hospitalisation du membre)');
        }
    }

    private function operationsFinancieres(Periode $debut, CarbonImmutable $fin): void
    {
        $service = app(OperationService::class);
        $cat = Categorie::pluck('id', 'nom');
        $ops = [
            [0, 14, 'Contributions exceptionnelles', 50000, 'Contribution de lancement versée par la mairie', 'admin', 'virement'],
            [1, 2, 'Fournitures et impression', 12500, 'Registre, carnets de reçus et fournitures de bureau', 'tresorier', 'especes'],
            [1, 27, 'Aides sociales aux membres', 50000, 'Aide sociale — décès d\'un parent d\'un membre', 'admin', 'especes'],
            [2, 9, 'Dons', 25000, 'Don d\'un partenaire de la commune', 'tresorier', 'wave'],
            [2, 30, 'Frais bancaires', 2500, 'Frais de tenue de compte (juillet)', 'tresorier', 'virement'],
            [3, 11, 'Frais de fonctionnement', 15000, 'Réunion du bureau — collation', 'tresorier', 'especes'],
            [4, 7, 'Aides sociales aux membres', 75000, 'Aide sociale — naissance (baptême)', 'admin', 'wave'],
            [4, 14, 'Autres recettes', 10000, 'Vente de carnets (saisie en double)', 'tresorier', 'especes'],
            [4, 16, 'Frais de fonctionnement', 7500, 'Photocopies des états mensuels', 'tresorier', 'especes'],
        ];
        $crees = [];
        foreach ($ops as [$decalage, $jour, $categorie, $montant, $desc, $par, $mode]) {
            $date = $debut->ajouterMois($decalage)->premierJour()->addDays($jour)->setTime(11, 30);
            if ($date->gt($fin)) {
                continue;
            }
            $this->instant($date);
            Auth::setUser($par === 'admin' ? $this->admin : $this->tresorier);
            $crees[] = $service->creer([
                'categorie_id' => $cat[$categorie], 'montant' => $montant, 'date_operation' => $date->toDateString(),
                'description' => $desc, 'mode_paiement_id' => $this->modes[$mode],
                'reference' => $mode === 'especes' ? null : 'REF-'.$date->format('ymd'),
            ]);
        }

        $doublon = collect($crees)->first(fn (OperationFinanciere $o) => str_contains($o->description, 'en double'));
        if ($doublon) {
            $this->instant($doublon->created_at->addDay()->setTime(9, 0));
            Auth::setUser($this->admin);
            $service->annuler($doublon, 'Opération saisie deux fois — la vente est déjà comptée dans une autre écriture');
        }
        $attente = collect($crees)->last(fn (OperationFinanciere $o) => ! $o->estAnnule() && $o->type === 'sortie' && $o->enregistre_par === $this->tresorier->id);
        if ($attente) {
            $this->instant(CarbonImmutable::now()->subHours(20));
            Auth::setUser($this->tresorier);
            $service->demanderAnnulation($attente->fresh(), 'Montant à vérifier avec la facture du prestataire');
        }
    }

    private function instant(\DateTimeInterface $date): void
    {
        CarbonImmutable::setTestNow($date);
        \Illuminate\Support\Carbon::setTestNow($date);
    }
}
