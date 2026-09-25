@props(['label', 'valeur', 'sous' => null, 'icone' => null, 'ton' => 'primaire', 'lien' => null])
@php
    $tons = [
        'primaire' => 'bg-primaire-100 text-primaire-700',
        'or' => 'bg-or-100 text-or-600',
        'rouge' => 'bg-red-100 text-red-700',
        'ambre' => 'bg-amber-100 text-amber-700',
        'bleu' => 'bg-sky-100 text-sky-700',
        'gris' => 'bg-slate-100 text-slate-600',
    ];
    $tag = $lien ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($lien) href="{{ $lien }}" @endif {{ $attributes->merge(['class' => 'carte flex min-w-0 items-start gap-3 p-3 sm:p-4'.($lien ? ' transition hover:border-primaire-300 hover:shadow-md' : '')]) }}>
    @if ($icone)
        <span class="hidden size-10 shrink-0 items-center justify-center rounded-xl min-[400px]:flex {{ $tons[$ton] ?? $tons['primaire'] }}">
            <x-icone :nom="$icone" class="size-5" />
        </span>
    @endif
    <div class="min-w-0">
        <p class="text-xs font-medium text-slate-500 sm:text-sm">{{ $label }}</p>
        <p class="mt-0.5 text-base font-bold tracking-tight break-words text-slate-900 tabular-nums sm:text-xl">{{ $valeur }}</p>
        @if ($sous)
            <p class="mt-0.5 text-xs text-slate-500">{{ $sous }}</p>
        @endif
    </div>
</{{ $tag }}>
