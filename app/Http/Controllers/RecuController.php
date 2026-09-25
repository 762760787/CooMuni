<?php

namespace App\Http\Controllers;

use App\Models\Paiement;
use App\Services\Audit;
use App\Support\Logo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Reçu PDF d'un paiement (§7.6). Accessible aux détenteurs de « recus.voir »
 * et au membre concerné uniquement (§14 protection des documents générés).
 */
class RecuController extends Controller
{
    public function __invoke(Request $request, Paiement $paiement)
    {
        $user = $request->user();
        abort_unless(
            $user->can('recus.voir') || ($user->can('espace.personnel') && $user->membre_id === $paiement->membre_id),
            403
        );

        $paiement->load(['membre', 'modePaiement', 'enregistrePar', 'cotisations', 'annulePar', 'paiementCorrige']);
        Audit::log('recu.telecharger', $paiement, null, null, 'Reçu '.$paiement->numero_recu);

        $pdf = Pdf::loadView('pdf.recu', [
            'paiement' => $paiement,
            'logo' => Logo::dataUri(),
            'coop' => [
                'nom' => parametre('coop_nom'),
                'adresse' => parametre('coop_adresse'),
                'telephone' => parametre('coop_telephone'),
            ],
        ])->setPaper('a5', 'portrait');

        $nom = 'recu-'.$paiement->numero_recu.'.pdf';

        return $request->boolean('telecharger') ? $pdf->download($nom) : $pdf->stream($nom);
    }
}
