@props(['etat'])
{{-- Badge coloré de statut de cotisation (payé / retard / impayé...) — §11.5 --}}
<span {{ $attributes->merge(['class' => 'badge '.$etat->couleur()]) }}>
    <span aria-hidden="true">{{ $etat->icone() }}</span>{{ $etat->label() }}
</span>
