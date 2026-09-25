{{-- Pagination Livewire en français, adaptée au tactile (§21 pagination systématique) --}}
@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-2 border-t border-slate-100 px-4 py-3" aria-label="Pagination">
        <p class="text-xs text-slate-500 sm:text-sm">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} sur {{ $paginator->total() }}
        </p>
        <div class="flex items-center gap-1">
            <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" @disabled($paginator->onFirstPage())
                    class="btn-secondaire px-3" aria-label="Page précédente"><x-icone nom="chevron-gauche" /></button>
            <span class="px-2 text-sm text-slate-600 tabular-nums">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
            <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" @disabled(! $paginator->hasMorePages())
                    class="btn-secondaire px-3" aria-label="Page suivante"><x-icone nom="chevron-droite" /></button>
        </div>
    </nav>
@endif
