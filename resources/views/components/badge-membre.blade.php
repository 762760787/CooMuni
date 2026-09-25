@props(['statut'])
<span {{ $attributes->merge(['class' => 'badge '.$statut->couleur()]) }}>{{ $statut->label() }}</span>
