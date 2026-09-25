@props(['donnees', 'series', 'hauteur' => 'h-44', 'format' => 'montant'])
{{--
    Graphique en barres léger, rendu côté serveur (HTML/CSS, aucun script) :
    lisible hors connexion et économe en données mobiles (§7.4, §21).
    $donnees : [['libelle' => 'Sept. 26', 'valeurs' => ['attendu' => 100, ...]], ...]
    $series  : [['cle' => 'attendu', 'nom' => 'Attendu', 'couleur' => 'bg-slate-300'], ...]
--}}
@php
    $max = max(1, collect($donnees)->flatMap(fn ($d) => array_values($d['valeurs']))->max());
    $fmt = fn ($v) => $format === 'montant' ? fcfa($v) : number_format($v, 0, ',', ' ');
@endphp
<div {{ $attributes }}>
    <div class="mb-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
        @foreach ($series as $s)
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm {{ $s['couleur'] }}"></span>{{ $s['nom'] }}</span>
        @endforeach
    </div>
    <div class="flex {{ $hauteur }} items-end gap-1 overflow-x-auto border-b border-slate-200 pb-px sm:gap-2" role="img"
         aria-label="Graphique : {{ collect($series)->pluck('nom')->implode(', ') }}">
        @foreach ($donnees as $d)
            <div class="flex h-full min-w-8 flex-1 items-end justify-center gap-0.5">
                @foreach ($series as $s)
                    @php $v = $d['valeurs'][$s['cle']] ?? 0; @endphp
                    <div class="w-full max-w-5 rounded-t {{ $s['couleur'] }}" style="height: {{ max($v > 0 ? 2 : 0, round($v * 100 / $max, 1)) }}%"
                         title="{{ $d['libelle'] }} — {{ $s['nom'] }} : {{ $fmt($v) }}"></div>
                @endforeach
            </div>
        @endforeach
    </div>
    <div class="flex gap-1 overflow-x-hidden pt-1 sm:gap-2">
        @foreach ($donnees as $d)
            <div class="min-w-8 flex-1 truncate text-center text-[10px] text-slate-500 sm:text-xs">{{ $d['libelle'] }}</div>
        @endforeach
    </div>
</div>
