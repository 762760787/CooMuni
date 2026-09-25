<?php

namespace App\Livewire;

use App\Livewire\Concerns\AvecRetours;
use App\Models\Categorie;
use App\Models\ModePaiement;
use App\Models\Parametre;
use App\Services\Audit;
use App\Services\Parametres as ServiceParametres;
use App\Support\Images;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Écran 17 — Paramètres : montant, échéance, règles, catégories, modes de paiement…
 * Tous stockés en base, modifiables sans développement (§7.12, §8.1), chaque modification tracée.
 */
#[Title('Paramètres')]
class Parametres extends Component
{
    use AvecRetours, WithFileUploads;

    public const GROUPES = [
        'general' => 'Général',
        'cotisations' => 'Cotisations',
        'categories' => 'Catégories financières',
        'modes' => 'Modes de paiement',
        'numerotation' => 'Numérotation',
        'notifications' => 'Notifications',
        'assistant' => 'Assistante IA',
        'sauvegarde' => 'Sauvegarde',
    ];

    #[Url]
    public string $onglet = 'general';

    public array $valeurs = [];
    public $logo = null;

    // Catégorie / mode en édition
    public ?int $categorieId = null;
    public array $categorie = ['nom' => '', 'type' => 'sortie', 'description' => '', 'restreinte' => false, 'actif' => true];
    public ?int $modeId = null;
    public array $mode = ['nom' => '', 'reference_requise' => false, 'actif' => true, 'ordre' => 0];

    public function mount(): void
    {
        $this->exiger('parametres.gerer');
        if (! isset(self::GROUPES[$this->onglet])) {
            $this->onglet = 'general';
        }
        $this->charger();
    }

    private function charger(): void
    {
        $this->valeurs = Parametre::pluck('valeur', 'cle')->map(fn ($v) => (string) $v)->all();
    }

    private function regle(Parametre $p): array
    {
        $regles = match ($p->type) {
            'int' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'bool' => ['required', 'in:0,1'],
            'month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'select' => ['required', Rule::in(array_map('strval', array_keys($p->options ?? [])))],
            'image' => ['nullable'],
            default => ['nullable', 'string', 'max:255'],
        };

        return match ($p->cle) {
            'cotisation_montant' => ['required', 'integer', 'min:100', 'max:10000000'],
            'cotisation_jour_echeance' => ['required', 'integer', 'min:1', 'max:28'],
            'coop_nom', 'coop_nom_court', 'ia_nom', 'matricule_prefixe', 'recu_prefixe', 'operation_prefixe' => ['required', 'string', 'max:120'],
            'sauvegarde_retention' => ['required', 'integer', 'min:1', 'max:365'],
            default => $regles,
        };
    }

    public function enregistrer(ServiceParametres $service): void
    {
        $this->exiger('parametres.gerer');
        $params = Parametre::where('groupe', $this->onglet)->where('type', '!=', 'image')->get();
        $regles = $params->mapWithKeys(fn ($p) => ['valeurs.'.$p->cle => $this->regle($p)])->all();
        $noms = $params->mapWithKeys(fn ($p) => ['valeurs.'.$p->cle => mb_strtolower($p->libelle)])->all();
        if ($this->onglet === 'general') {
            $regles['logo'] = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'];
        }
        $this->validate($regles, [], $noms);

        foreach ($params as $p) {
            $service->set($p->cle, $this->valeurs[$p->cle] ?? null);
        }
        if ($this->logo) {
            $service->set('coop_logo', Images::enregistrerPhoto($this->logo, 512));
            $this->logo = null;
        }
        $this->charger();
        $this->succes('Paramètres enregistrés. Les modifications sont tracées dans le journal d\'audit.');
    }

    public function supprimerLogo(ServiceParametres $service): void
    {
        $this->exiger('parametres.gerer');
        $service->set('coop_logo', '');
        $this->charger();
        $this->succes('Logo par défaut rétabli.');
    }

    // --- Catégories -----------------------------------------------------------

    public function editerCategorie(?int $id = null): void
    {
        $this->exiger('parametres.gerer');
        $c = $id ? Categorie::findOrFail($id) : null;
        $this->categorieId = $c?->id;
        $this->categorie = $c ? $c->only(['nom', 'type', 'description', 'restreinte', 'actif'])
            : ['nom' => '', 'type' => 'sortie', 'description' => '', 'restreinte' => false, 'actif' => true];
        $this->categorie['description'] = (string) $this->categorie['description'];
        $this->resetErrorBag();
        $this->dispatch('ouvrir-modal', 'categorie');
    }

    public function enregistrerCategorie(): void
    {
        $this->exiger('parametres.gerer');
        $data = $this->validate([
            'categorie.nom' => ['required', 'string', 'max:120', Rule::unique('categories', 'nom')->where('type', $this->categorie['type'])->ignore($this->categorieId)],
            'categorie.type' => ['required', 'in:entree,sortie'],
            'categorie.description' => ['nullable', 'string', 'max:255'],
            'categorie.restreinte' => ['boolean'],
            'categorie.actif' => ['boolean'],
        ], [], ['categorie.nom' => 'nom'])['categorie'];

        if ($this->categorieId) {
            $c = Categorie::findOrFail($this->categorieId);
            if ($c->type !== $data['type'] && $c->operations()->exists()) {
                $this->addError('categorie.type', 'Des opérations utilisent cette catégorie : son type ne peut plus changer.');

                return;
            }
            [$a, $b] = Audit::diff($c->only(array_keys($data)), $data);
            $c->update($data);
            Audit::log('categorie.modifier', $c, $a, $b, $c->nom);
        } else {
            $c = Categorie::create($data);
            Audit::log('categorie.creer', $c, null, $data, $c->nom);
        }
        $this->dispatch('fermer-modal');
        $this->succes('Catégorie enregistrée.');
    }

    // --- Modes de paiement ------------------------------------------------------

    public function editerMode(?int $id = null): void
    {
        $this->exiger('parametres.gerer');
        $m = $id ? ModePaiement::findOrFail($id) : null;
        $this->modeId = $m?->id;
        $this->mode = $m ? $m->only(['nom', 'reference_requise', 'actif', 'ordre'])
            : ['nom' => '', 'reference_requise' => false, 'actif' => true, 'ordre' => (int) ModePaiement::max('ordre') + 1];
        $this->resetErrorBag();
        $this->dispatch('ouvrir-modal', 'mode');
    }

    public function enregistrerMode(): void
    {
        $this->exiger('parametres.gerer');
        $data = $this->validate([
            'mode.nom' => ['required', 'string', 'max:80', Rule::unique('modes_paiement', 'nom')->ignore($this->modeId)],
            'mode.reference_requise' => ['boolean'],
            'mode.actif' => ['boolean'],
            'mode.ordre' => ['required', 'integer', 'min:0', 'max:999'],
        ], [], ['mode.nom' => 'nom'])['mode'];

        if ($this->modeId) {
            $m = ModePaiement::findOrFail($this->modeId);
            if (! $data['actif'] && ModePaiement::actifs()->where('id', '!=', $m->id)->doesntExist()) {
                $this->addError('mode.actif', 'Au moins un mode de paiement doit rester actif.');

                return;
            }
            [$a, $b] = Audit::diff($m->only(array_keys($data)), $data);
            $m->update($data);
            Audit::log('mode_paiement.modifier', $m, $a, $b, $m->nom);
        } else {
            $m = ModePaiement::create($data + ['code' => Str::slug($data['nom'], '_').'_'.Str::lower(Str::random(4))]);
            Audit::log('mode_paiement.creer', $m, null, $data, $m->nom);
        }
        $this->dispatch('fermer-modal');
        $this->succes('Mode de paiement enregistré.');
    }

    public function render()
    {
        return view('livewire.parametres', [
            'groupes' => self::GROUPES,
            'parametres' => Parametre::where('groupe', $this->onglet)->orderBy('ordre')->get(),
            'categories' => $this->onglet === 'categories' ? Categorie::withCount('operations')->orderBy('type')->orderBy('nom')->get() : collect(),
            'modes' => $this->onglet === 'modes' ? ModePaiement::withCount('paiements')->orderBy('ordre')->get() : collect(),
        ]);
    }
}
