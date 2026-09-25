<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $r['titre'] }}</title>
    <style>
        @page { margin: 14mm 10mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; }
        .entete { width: 100%; border-bottom: 2px solid #1f6f3a; padding-bottom: 6px; margin-bottom: 8px; }
        .entete td { vertical-align: middle; }
        .coop { font-size: 11px; font-weight: bold; color: #1f6f3a; }
        h1 { font-size: 14px; margin: 0; }
        .sous { color: #64748b; font-size: 9.5px; }
        table.resume { border-collapse: separate; border-spacing: 4px; margin: 6px -4px 8px; }
        table.resume td { background: #f0f8f1; border: 1px solid #bbdfc2; padding: 5px 8px; }
        table.resume .l { color: #475569; font-size: 8px; }
        table.resume .v { font-weight: bold; font-size: 10.5px; }
        table.donnees { width: 100%; border-collapse: collapse; }
        table.donnees th { background: #1f6f3a; color: #fff; padding: 5px 4px; text-align: left; font-size: 8.5px; }
        table.donnees td { padding: 4px; border-bottom: 1px solid #e2e8f0; }
        table.donnees tr:nth-child(even) td { background: #f8fafc; }
        table.donnees tfoot td { font-weight: bold; background: #e2e8f0; border-top: 1px solid #94a3b8; }
        .droite { text-align: right; }
        .pied { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <table class="entete">
        <tr>
            <td style="width: 50px"><img src="{{ $logo }}" style="width: 44px" alt=""></td>
            <td>
                <div class="coop">{{ $r['cooperative'] }}</div>
                <h1>{{ $r['titre'] }}</h1>
                <div class="sous">{{ $r['sous_titre'] }}</div>
            </td>
        </tr>
    </table>

    @if ($r['resume'])
        <table class="resume">
            <tr>
                @foreach ($r['resume'] as $x)
                    <td><div class="l">{{ $x['label'] }}</div><div class="v">{{ $x['valeur'] }}</div></td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="donnees">
        <thead>
            <tr>@foreach ($r['colonnes'] as $c)<th class="{{ in_array($c['type'], ['montant', 'nombre'], true) ? 'droite' : '' }}">{{ $c['label'] }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($r['lignes'] as $ligne)
                <tr>
                    @foreach ($r['colonnes'] as $c)
                        <td class="{{ in_array($c['type'], ['montant', 'nombre'], true) ? 'droite' : '' }}">{{ \App\Services\Rapports::texte($ligne[$c['cle']] ?? null, $c['type']) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($r['colonnes']) }}">Aucune donnée pour cette période.</td></tr>
            @endforelse
        </tbody>
        @if ($r['totaux'])
            <tfoot>
                <tr>
                    @foreach ($r['colonnes'] as $c)
                        <td class="{{ in_array($c['type'], ['montant', 'nombre'], true) ? 'droite' : '' }}">{{ isset($r['totaux'][$c['cle']]) ? \App\Services\Rapports::texte($r['totaux'][$c['cle']], $c['type']) : '' }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="pied">Généré le {{ $r['genere_le']->format('d/m/Y à H:i') }} par {{ auth()->user()?->name }} — {{ $r['cooperative'] }}</div>
</body>
</html>
