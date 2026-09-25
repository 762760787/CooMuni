<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $paiement->numero_recu }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1e293b; }
        .entete { width: 100%; border-bottom: 2px solid #1f6f3a; padding-bottom: 8px; }
        .entete td { vertical-align: middle; }
        .logo { width: 62px; }
        .coop { font-size: 13px; font-weight: bold; color: #1f6f3a; }
        .petit { font-size: 9px; color: #64748b; }
        h1 { font-size: 16px; margin: 14px 0 2px; letter-spacing: .5px; }
        .num { font-size: 12px; font-weight: bold; color: #b7860a; }
        table.infos { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.infos td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; }
        table.infos td.l { color: #64748b; width: 38%; }
        .montant { margin: 14px 0; padding: 10px; background: #f0f8f1; border: 1px solid #bbdfc2; text-align: center; }
        .montant .v { font-size: 20px; font-weight: bold; color: #1f6f3a; }
        table.vent { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.vent th { background: #f1f5f9; text-align: left; padding: 5px 6px; font-size: 9.5px; }
        table.vent td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; }
        .droite { text-align: right; }
        .annule { position: fixed; top: 38%; left: 8%; font-size: 64px; color: rgba(220, 38, 38, .22); transform: rotate(-28deg); font-weight: bold; }
        .signature { margin-top: 26px; width: 100%; }
        .signature td { width: 50%; vertical-align: top; }
        .pied { position: fixed; bottom: -4mm; left: 0; right: 0; text-align: center; font-size: 8.5px; color: #94a3b8; }
    </style>
</head>
<body>
    @if ($paiement->estAnnule())
        <div class="annule">ANNULÉ</div>
    @endif

    <table class="entete">
        <tr>
            <td style="width: 70px"><img class="logo" src="{{ $logo }}" alt=""></td>
            <td>
                <div class="coop">{{ $coop['nom'] }}</div>
                <div class="petit">{{ $coop['adresse'] }}{{ $coop['telephone'] ? ' — Tél. '.$coop['telephone'] : '' }}</div>
            </td>
        </tr>
    </table>

    <h1>REÇU DE COTISATION</h1>
    <div class="num">N° {{ $paiement->numero_recu }}</div>

    <table class="infos">
        <tr><td class="l">Membre</td><td><strong>{{ $paiement->membre->nom_complet }}</strong></td></tr>
        <tr><td class="l">Matricule</td><td>{{ $paiement->membre->matricule }}</td></tr>
        <tr><td class="l">Période(s)</td><td>{{ $paiement->libellePeriodes() }}</td></tr>
        <tr><td class="l">Date du paiement</td><td>{{ $paiement->date_paiement->format('d/m/Y') }}</td></tr>
        <tr><td class="l">Mode de paiement</td><td>{{ $paiement->modePaiement->nom }}{{ $paiement->reference ? ' — réf. '.$paiement->reference : '' }}</td></tr>
        <tr><td class="l">Enregistré par</td><td>{{ $paiement->enregistrePar->name }}, le {{ $paiement->created_at->format('d/m/Y à H:i') }}</td></tr>
    </table>

    <div class="montant">
        <div class="petit">Montant reçu</div>
        <div class="v">{{ fcfa($paiement->montant) }}</div>
    </div>

    <table class="vent">
        <thead><tr><th>Période</th><th class="droite">Montant affecté</th><th class="droite">Situation de la période</th></tr></thead>
        <tbody>
            @foreach ($paiement->cotisations as $c)
                <tr>
                    <td>{{ $c->periode_libelle }}</td>
                    <td class="droite">{{ fcfa($c->pivot->montant) }}</td>
                    <td class="droite">{{ $c->etat()->label() }} ({{ fcfa($c->montant_paye) }} / {{ fcfa($c->montant_attendu) }})</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($paiement->paiementCorrige)
        <p class="petit">Ce reçu remplace le reçu annulé {{ $paiement->paiementCorrige->numero_recu }}.</p>
    @endif
    @if ($paiement->estAnnule())
        <p style="color:#b91c1c"><strong>Reçu annulé</strong> le {{ $paiement->annule_le->format('d/m/Y') }} — motif : {{ $paiement->motif_annulation }}</p>
    @endif

    <table class="signature">
        <tr>
            <td class="petit">Le membre</td>
            <td class="petit droite">Pour la coopérative, le trésorier</td>
        </tr>
    </table>

    <div class="pied">Document généré le {{ now()->format('d/m/Y à H:i') }} — reçu n° {{ $paiement->numero_recu }} — toute rature le rend invalide.</div>
</body>
</html>
