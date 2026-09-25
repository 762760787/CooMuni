<?php

namespace App\Livewire\Concerns;

use App\Exceptions\RegleMetierException;

trait AvecRetours
{
    protected function succes(string $message): void
    {
        $this->dispatch('toast', message: $message, type: 'succes');
    }

    protected function erreur(string $message): void
    {
        $this->dispatch('toast', message: $message, type: 'erreur');
    }

    /** Contrôle serveur de la permission pour chaque action (§6, §13). */
    protected function exiger(string ...$permissions): void
    {
        $user = auth()->user();
        abort_unless($user && collect($permissions)->contains(fn ($p) => $user->can($p)), 403,
            'Vous n\'avez pas les droits nécessaires pour cette action.');
    }

    /** Exécute une opération métier ; une règle violée devient un message clair. */
    protected function tenter(callable $operation, ?string $champErreur = null): mixed
    {
        try {
            return $operation();
        } catch (RegleMetierException $e) {
            $champErreur ? $this->addError($champErreur, $e->getMessage()) : $this->erreur($e->getMessage());

            return null;
        }
    }

    public function paginationView(): string
    {
        return 'components.pagination';
    }
}
