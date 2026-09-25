@props(['cible' => null])
{{-- Indicateur de chargement (§11.5) --}}
<div wire:loading.delay @if ($cible) wire:target="{{ $cible }}" @endif
     {{ $attributes->merge(['class' => 'pointer-events-none fixed inset-x-0 top-0 z-[80]']) }} role="progressbar" aria-label="Chargement">
    <div class="h-1 w-full overflow-hidden bg-primaire-100">
        <div class="h-full w-1/3 animate-[chargement_1s_ease-in-out_infinite] bg-or-400"></div>
    </div>
</div>
