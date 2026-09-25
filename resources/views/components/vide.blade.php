@props(['message' => 'Aucun élément à afficher.', 'icone' => 'info'])
{{-- État vide (§11.5) --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-2 px-6 py-12 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><x-icone :nom="$icone" class="size-6" /></span>
    <p class="text-sm text-slate-500">{{ $message }}</p>
    {{ $slot }}
</div>
