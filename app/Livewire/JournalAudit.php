<?php

namespace App\Livewire;

use App\Livewire\Concerns\AvecRetours;
use App\Models\AuditLog;
use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Écran 18 — Journal d'audit, filtrable par utilisateur / action / date (lecture seule). */
#[Title('Journal d\'audit')]
class JournalAudit extends Component
{
    use AvecRetours, WithPagination;

    #[Url(except: '')]
    public string $utilisateur = '';

    #[Url(except: '')]
    public string $action = '';

    #[Url(except: '')]
    public string $du = '';

    #[Url(except: '')]
    public string $au = '';

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    public ?int $detailId = null;

    public function updated($p): void
    {
        if ($p !== 'detailId') {
            $this->resetPage();
        }
    }

    public function voir(int $id): void
    {
        $this->detailId = $id;
        $this->dispatch('ouvrir-modal', 'detail-audit');
    }

    public function render()
    {
        $this->exiger('audit.voir');
        $terme = trim($this->recherche);

        $logs = AuditLog::with('user')
            ->when($this->utilisateur === 'systeme', fn ($q) => $q->whereNull('user_id'))
            ->when($this->utilisateur !== '' && $this->utilisateur !== 'systeme', fn ($q) => $q->where('user_id', $this->utilisateur))
            ->when($this->action, fn ($q) => str_ends_with($this->action, '.*')
                ? $q->where('action', 'like', substr($this->action, 0, -1).'%')
                : $q->where('action', $this->action))
            ->when($this->du, fn ($q) => $q->where('date_action', '>=', $this->du.' 00:00:00'))
            ->when($this->au, fn ($q) => $q->where('date_action', '<=', $this->au.' 23:59:59'))
            ->when($terme !== '', fn ($q) => $q->where(fn ($q) => $q->where('description', 'like', "%{$terme}%")->orWhere('ip', 'like', "%{$terme}%")))
            ->orderByDesc('date_action')->orderByDesc('id')
            ->paginate(30);

        $familles = collect(AuditLog::LIBELLES)->keys()->map(fn ($a) => explode('.', $a)[0])->unique()->values();

        return view('livewire.journal-audit', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name', 'actif']),
            'actions' => AuditLog::LIBELLES,
            'familles' => $familles,
            'detail' => $this->detailId ? AuditLog::with('user')->find($this->detailId) : null,
        ]);
    }
}
