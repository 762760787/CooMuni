<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parametre extends Model
{
    protected $fillable = ['cle', 'valeur', 'type', 'groupe', 'libelle', 'description', 'options', 'ordre'];

    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    /** Valeur convertie selon le type déclaré. */
    public function valeurTypee(): mixed
    {
        return match ($this->type) {
            'int' => $this->valeur === null ? null : (int) $this->valeur,
            'bool' => filter_var($this->valeur, FILTER_VALIDATE_BOOLEAN),
            default => $this->valeur,
        };
    }
}
