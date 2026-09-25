<?php

namespace App\Livewire\Membres;

use App\Livewire\Concerns\AvecRetours;
use App\Models\Membre;
use App\Services\CotisationService;
use App\Services\MembreService;
use App\Support\Images;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Écran 5 — Ajout / modification d'un membre, avec contrôle anti-doublon (§7.1, §27.2). */
class Formulaire extends Component
{
    use AvecRetours, WithFileUploads;

    public ?Membre $membre = null;

    public string $matricule = '';
    public string $nom = '';
    public string $prenom = '';
    public string $sexe = '';
    public string $telephone = '';
    public string $email = '';
    public string $fonction = '';
    public string $service = '';
    public string $date_adhesion = '';
    public string $observations = '';
    public $photo = null;

    public bool $confirmerHomonyme = false;

    public function mount(?Membre $membre = null): void
    {
        if ($membre?->exists) {
            $this->exiger('membres.modifier');
            $this->membre = $membre;
            $this->fill(array_map(fn ($v) => (string) $v, $membre->only(['matricule', 'nom', 'prenom', 'fonction', 'service', 'observations'])));
            $this->sexe = (string) $membre->sexe;
            $this->telephone = (string) $membre->telephone;
            $this->email = (string) $membre->email;
            $this->date_adhesion = $membre->date_adhesion->toDateString();
        } else {
            $this->exiger('membres.creer');
            $this->membre = null;
            $this->matricule = app(MembreService::class)->prochainMatricule();
            $this->date_adhesion = today()->toDateString();
        }
    }

    protected function rules(): array
    {
        $id = $this->membre?->id;

        return [
            'matricule' => ['required', 'string', 'max:30', Rule::unique('membres', 'matricule')->ignore($id)],
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:150'],
            'sexe' => ['nullable', 'in:H,F'],
            'telephone' => ['nullable', 'string', 'regex:/^\+?[\d\s.\-]{7,20}$/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'fonction' => ['nullable', 'string', 'max:120'],
            'service' => ['nullable', 'string', 'max:120'],
            'date_adhesion' => ['required', 'date', 'before_or_equal:today'],
            'observations' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['date_adhesion' => 'date d\'adhésion', 'prenom' => 'prénom', 'telephone' => 'téléphone'];
    }

    /** Contrôle anti-doublon en direct pendant la saisie. */
    public function updated($propriete): void
    {
        if (in_array($propriete, ['matricule', 'telephone', 'nom', 'prenom', 'photo'], true)) {
            $this->validateOnly($propriete);
        }
        if (in_array($propriete, ['nom', 'prenom'], true)) {
            $this->confirmerHomonyme = false;
        }
    }

    public function enregistrer(MembreService $service)
    {
        $this->exiger($this->membre ? 'membres.modifier' : 'membres.creer');
        $data = $this->validate();

        $doublons = $service->verifierDoublons($data, $this->membre?->id);
        foreach ($doublons['bloquants'] as $champ => $message) {
            $this->addError($champ, $message);
        }
        if ($doublons['bloquants']) {
            return;
        }
        if ($doublons['homonymes']->isNotEmpty() && ! $this->confirmerHomonyme) {
            $this->addError('confirmerHomonyme', 'Un membre porte déjà ce nom et ce prénom. Cochez la case pour confirmer qu\'il s\'agit d\'une autre personne.');

            return;
        }

        if ($this->photo) {
            $data['photo'] = Images::enregistrerPhoto($this->photo);
        } else {
            unset($data['photo']);
        }

        $membre = $this->tenter(fn () => $this->membre ? $service->modifier($this->membre, $data) : $service->creer($data));
        if (! $membre) {
            return;
        }

        session()->flash('succes', $this->membre ? 'Membre mis à jour.' : 'Membre créé. Ses cotisations ont été générées selon la règle d\'adhésion.');

        return $this->redirectRoute('membres.show', $membre);
    }

    public function render(MembreService $service, CotisationService $cotisations)
    {
        $homonymes = ($this->nom && $this->prenom)
            ? $service->verifierDoublons(['nom' => mb_strtoupper(trim($this->nom)), 'prenom' => \Illuminate\Support\Str::title(mb_strtolower(trim($this->prenom)))], $this->membre?->id)['homonymes']
            : collect();

        // Aperçu de la règle d'adhésion en cours de mois (§8.2).
        $premierMois = null;
        if (! $this->membre && $this->date_adhesion && strtotime($this->date_adhesion)) {
            $premierMois = $cotisations->premierePeriodeDue(new Membre(['date_adhesion' => $this->date_adhesion]))->libelle();
        }

        return view('livewire.membres.formulaire', [
            'homonymes' => $homonymes,
            'premierMois' => $premierMois,
            'services' => Membre::whereNotNull('service')->distinct()->orderBy('service')->pluck('service'),
            'fonctions' => Membre::whereNotNull('fonction')->distinct()->orderBy('fonction')->pluck('fonction'),
        ])->title($this->membre ? 'Modifier un membre' : 'Nouveau membre');
    }
}
