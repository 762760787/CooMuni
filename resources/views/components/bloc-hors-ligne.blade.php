{{-- Règle §12.4 : aucune opération financière n'est saisie hors connexion. --}}
<div x-data x-show="!$store.reseau.enLigne" x-cloak role="alert"
     {{ $attributes->merge(['class' => 'flex items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900']) }}>
    <x-icone nom="wifi-off" class="mt-0.5 size-5 shrink-0" />
    <p><strong>Hors connexion.</strong> L'enregistrement des opérations financières est bloqué pour éviter toute perte ou double saisie. Il sera de nouveau possible dès le retour du réseau.</p>
</div>
