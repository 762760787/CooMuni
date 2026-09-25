<?php

namespace App\Livewire;

use App\Livewire\Concerns\AvecRetours;
use App\Models\Membre;
use App\Services\Rapports as ServiceRapports;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Écran 14 — Rapports : type + période → aperçu → export PDF / Excel / CSV (§9.4, §19, §27.3). */
#[Title('Rapports')]
class Rapports extends Component
{
    use AvecRetours;

    #[Url]
    public string $type = 'etat_mensuel';

    #[Url]
    public array $params = [];

    public string $rechercheMembre = '';
    public bool $apercu = false;

    public function mount(): void
    {
        $this->exiger('rapports.voir');
        $this->params = array_merge(ServiceRapports::parametresParDefaut(), array_filter($this->params, fn ($v) => $v !== null && $v !== ''));
    }

    public function updatedType(): void
    {
        $this->apercu = false;
        $this->resetErrorBag();
    }

    public function updatedParams(): void
    {
        $this->apercu = false;
    }

    public function generer(): void
    {
        $this->exiger('rapports.voir');
        $this->apercu = true;
    }

    public function render(ServiceRapports $service)
    {
        $rapport = null;
        if ($this->apercu) {
            try {
                $rapport = $service->generer($this->type, $this->params);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $champ => $messages) {
                    $this->addError('params.'.$champ, $messages[0]);
                }
                $this->apercu = false;
            }
        }
        $besoins = ServiceRapports::TYPES[$this->type]['params'] ?? [];

        return view('livewire.rapports', [
            'types' => ServiceRapports::TYPES,
            'besoins' => $besoins,
            'rapport' => $rapport,
            'membres' => in_array('membre_id', $besoins, true)
                ? Membre::recherche($this->rechercheMembre)->orderBy('nom')->orderBy('prenom')->limit(50)->get(['id', 'nom', 'prenom', 'matricule'])
                : collect(),
            'exportParams' => array_intersect_key($this->params, array_flip($besoins)) + ['type' => $this->type],
        ]);
    }
}
