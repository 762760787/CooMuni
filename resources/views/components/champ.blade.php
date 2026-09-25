@props(['label', 'for' => null, 'erreur' => null, 'aide' => null, 'requis' => false])
<div {{ $attributes }}>
    <label @if ($for) for="{{ $for }}" @endif class="etiquette">
        {{ $label }} @if ($requis)<span class="text-red-600" aria-hidden="true">*</span>@endif
    </label>
    {{ $slot }}
    @if ($erreur)
        <p class="mt-1 text-sm text-red-600" role="alert">{{ $erreur }}</p>
    @elseif ($aide)
        <p class="mt-1 text-xs text-slate-500">{{ $aide }}</p>
    @endif
</div>
