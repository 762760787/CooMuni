<?php

namespace App\Livewire\Cotisations;

use App\Enums\StatutCotisation;
use App\Livewire\Concerns\AvecRetours;
use App\Models\Cotisation;
use App\Models\Membre;
use App\Models\ModePaiement;
use App\Services\CotisationService;
use App\Services\PaiementService;
use App\Services\Statistiques;
use App\Support\Periode;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Écran 6 — Cotisations du mois : vue consolidée, statuts, saisie rapide (§7.2, §9.1). */
#[Title('Cotisations du mois')]
class Mensuelle extends Component
{
    use AvecRetours, WithPagination;

    #[Url]
    public string $periode = '';

    #[Url(except: '')]
    public string $etat = '';

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $service = '';

    // Saisie rapide
    public ?int $cotisationId = null;
    public string $montant = '';
    public string $modeId = '';
    public string $datePaiement = '';
    public string $reference = '';
    public bool $confirmerDoublon = false;
    public array $avertissements = [];

    // Correction administrative
    public string $actionCorrection = '';
    public string $motif = '';

    public function mount(): void
    {
        if (! Periode::isValid($this->periode)) {
            $this->periode = (string) Periode::courante();
        }
    }

    public function updated($prop): void
    {
        if (in_array($prop, ['periode', 'etat', 'recherche', 'service'], true)) {
            $this->resetPage();
        }
    }

    public function changerPeriode(int $decalage): void
    {
        $this->periode = (string) Periode::fromString($this->periode)->ajouterMois($decalage);
        $this->resetPage();
    }

    public function generer(CotisationService $service): void
    {
        $this->exiger('cotisations.generer');
        $p = Periode::fromString($this->periode);
        if ($p->compare(Periode::courante()->suivante()) > 0) {
            $this->erreur('Seules les cotisations jusqu\'au mois prochain peuvent être générées à l\'avance.');

            return;
        }
        $n = $this->tenter(fn () => $service->genererPeriode($p));
        $this->succes($n ? "{$n} cotisation(s) générée(s) pour ".$p->libelle().'.' : 'Toutes les cotisations de ce mois existent déjà.');
    }

    public function ouvrirSaisie(int $id): void
    {
        $this->exiger('paiements.creer');
        $c = Cotisation::findOrFail($id);
        $this->resetErrorBag();
        $this->cotisationId = $c->id;
        $this->montant = (string) $c->reste;
        $this->datePaiement = today()->toDateString();
        $this->modeId = (string) (ModePaiement::actifs()->value('id') ?? '');
        $this->reference = '';
        $this->confirmerDoublon = false;
        $this->avertissements = [];
        $this->dispatch('ouvrir-modal', 'saisie-rapide');
    }

    public function encaisser(PaiementService $service): void
    {
        $this->exiger('paiements.creer');
        $this->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'modeId' => ['required', 'exists:modes_paiement,id'],
            'datePaiement' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
        ], [], ['modeId' => 'mode de paiement', 'datePaiement' => 'date de paiement']);

        $c = Cotisation::with('membre')->findOrFail($this->cotisationId);
        if (! $this->confirmerDoublon) {
            $this->avertissements = $service->detecterDoublons($c->membre_id, (int) $this->montant, $this->datePaiement, $this->reference ?: null)['avertissements'];
            if ($this->avertissements) {
                return;
            }
        }

        $paiement = $this->tenter(fn () => $service->enregistrer([
            'membre_id' => $c->membre_id,
            'periodes' => [$c->periode],
            'montant' => (int) $this->montant,
            'date_paiement' => $this->datePaiement,
            'mode_paiement_id' => (int) $this->modeId,
            'reference' => $this->reference ?: null,
            'confirmer_doublon' => $this->confirmerDoublon,
        ]), 'montant');

        if ($paiement) {
            $this->dispatch('fermer-modal');
            $this->succes("Paiement enregistré — reçu {$paiement->numero_recu}.");
            $this->cotisationId = null;
        }
    }

    public function ouvrirCorrection(int $id, string $action): void
    {
        $this->exiger('cotisations.corriger');
        abort_unless(in_array($action, ['annuler', 'regulariser', 'retablir'], true), 400);
        $this->resetErrorBag();
        $this->cotisationId = $id;
        $this->actionCorrection = $action;
        $this->motif = '';
        $this->dispatch('ouvrir-modal', 'correction');
    }

    public function corriger(CotisationService $service): void
    {
        $this->exiger('cotisations.corriger');
        $this->validate(['motif' => ['required', 'string', 'min:5', 'max:500']]);
        $c = Cotisation::with('membre')->findOrFail($this->cotisationId);

        $ok = $this->tenter(function () use ($service, $c) {
            match ($this->actionCorrection) {
                'annuler' => $service->annuler($c, $this->motif),
                'regulariser' => $service->regulariser($c, $this->motif),
                'retablir' => $service->retablir($c, $this->motif),
            };

            return true;
        }, 'motif');

        if ($ok) {
            $this->dispatch('fermer-modal');
            $this->succes('Cotisation mise à jour (opération tracée dans le journal d\'audit).');
        }
    }

    public function render(Statistiques $stats)
    {
        $this->exiger('cotisations.voir');
        $periode = Periode::isValid($this->periode) ? Periode::fromString($this->periode) : Periode::courante();
        $aujourdhui = today()->toDateString();

        $cotisations = Cotisation::query()
            ->where('periode', (string) $periode)
            ->join('membres', 'membres.id', '=', 'cotisations.membre_id')
            ->select('cotisations.*')
            ->with('membre')
            ->when($this->recherche, fn (Builder $q) => $q->whereIn('membre_id', Membre::recherche($this->recherche)->select('id')))
            ->when($this->service, fn (Builder $q) => $q->where('membres.service', $this->service))
            ->when($this->etat, fn (Builder $q) => match ($this->etat) {
                'a_jour' => $q->whereIn('cotisations.statut', ['paye', 'paye_retard', 'regularise']),
                'paye' => $q->where('cotisations.statut', 'paye'),
                'paye_retard' => $q->where('cotisations.statut', 'paye_retard'),
                'impaye' => $q->whereIn('cotisations.statut', StatutCotisation::dues())->where('date_echeance', '<', $aujourdhui),
                'a_venir' => $q->whereIn('cotisations.statut', StatutCotisation::dues())->where('date_echeance', '>=', $aujourdhui),
                'partiel' => $q->where('cotisations.statut', 'partiel'),
                'annule' => $q->whereIn('cotisations.statut', ['annule', 'regularise']),
                default => $q,
            })
            ->orderBy('membres.nom')->orderBy('membres.prenom')
            ->paginate(25);

        return view('livewire.cotisations.mensuelle', [
            'periodeObj' => $periode,
            'stats' => $stats->periode($periode),
            'cotisations' => $cotisations,
            'modes' => ModePaiement::actifs()->get(),
            'services' => Membre::whereNotNull('service')->distinct()->orderBy('service')->pluck('service'),
            'cotisationSaisie' => $this->cotisationId ? Cotisation::with('membre')->find($this->cotisationId) : null,
        ]);
    }
}
