<?php

namespace App\Livewire;

use App\Livewire\Concerns\AvecRetours;
use App\Models\NotificationInterne;
use App\Models\User;
use App\Services\Audit;
use App\Services\Notifications\Message;
use App\Services\Notifications\NotificationService;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/** Notifications internes (§7.9, §18) + diffusion d'une information par un administrateur. */
#[Title('Notifications')]
class Notifications extends Component
{
    use AvecRetours, WithPagination;

    public string $titre = '';
    public string $contenu = '';

    public function ouvrir(int $id)
    {
        $n = NotificationInterne::where('user_id', auth()->id())->findOrFail($id);
        if (! $n->lu) {
            $n->update(['lu' => true, 'lu_le' => now()]);
        }
        // Seul le chemin interne est suivi (jamais un domaine externe).
        if ($n->lien && ($chemin = parse_url($n->lien, PHP_URL_PATH))) {
            $query = parse_url($n->lien, PHP_URL_QUERY);

            return $this->redirect(url($chemin.($query ? '?'.$query : '')));
        }
    }

    public function toutMarquerLu(): void
    {
        NotificationInterne::where('user_id', auth()->id())->nonLues()->update(['lu' => true, 'lu_le' => now()]);
        $this->succes('Toutes les notifications sont marquées comme lues.');
    }

    public function diffuser(NotificationService $service): void
    {
        $this->exiger('notifications.diffuser');
        $this->validate(['titre' => 'required|string|max:120', 'contenu' => 'required|string|max:1000'], [], ['contenu' => 'message']);
        $destinataires = User::where('actif', true)->get();
        $service->envoyer($destinataires, new Message('information', $this->titre, $this->contenu));
        Audit::log('notification.diffuser', 'NotificationInterne', null, ['titre' => $this->titre, 'destinataires' => $destinataires->count()], 'Information diffusée');
        $this->reset('titre', 'contenu');
        $this->dispatch('fermer-modal');
        $this->succes('Information diffusée à '.$destinataires->count().' utilisateur(s).');
    }

    public function render()
    {
        return view('livewire.notifications', [
            'notifications' => NotificationInterne::where('user_id', auth()->id())->latest('id')->paginate(20),
        ]);
    }
}
