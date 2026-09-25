@props(['titre', 'sousTitre' => null, 'retour' => null])
<div {{ $attributes->merge(['class' => 'mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="flex min-w-0 items-start gap-2">
        @if ($retour)
            <a href="{{ $retour }}" class="btn-icone -ml-2 shrink-0" aria-label="Retour"><x-icone nom="chevron-gauche" class="size-6" /></a>
        @endif
        <div class="min-w-0">
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ $titre }}</h1>
            @if ($sousTitre)
                <p class="mt-0.5 text-sm text-slate-500">{{ $sousTitre }}</p>
            @endif
        </div>
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
