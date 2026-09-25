<?php

namespace App\Http\Controllers;

use App\Models\Membre;
use App\Models\OperationFinanciere;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Fichiers privés (photos, justificatifs) : stockés hors du dossier public
 * et servis uniquement après contrôle des droits (§13, §14).
 */
class FichierController extends Controller
{
    public function photo(Request $request, Membre $membre)
    {
        $user = $request->user();
        abort_unless($user->can('membres.voir') || $user->membre_id === $membre->id, 403);
        abort_unless($membre->photo && Storage::disk('local')->exists($membre->photo), 404);

        return Storage::disk('local')->response($membre->photo, null, [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function justificatif(Request $request, OperationFinanciere $operation)
    {
        abort_unless($operation->justificatif && Storage::disk('local')->exists($operation->justificatif), 404);
        Audit::log('operation.justificatif', $operation, null, null, $operation->numero);

        return Storage::disk('local')->response($operation->justificatif, $operation->justificatif_nom, [
            'Content-Disposition' => 'inline; filename="'.addslashes($operation->justificatif_nom ?? 'justificatif').'"',
        ]);
    }
}
