<div class="flex items-center gap-3 border-b border-white/10 px-5 py-4">
    <img src="{{ route('logo') }}" alt="Logo" class="size-11 shrink-0 rounded-lg bg-white/95 object-contain p-0.5">
    <div class="min-w-0 leading-tight">
        <p class="truncate text-sm font-bold text-white">{{ parametre('coop_nom_court', 'Coopérative') }}</p>
        <p class="truncate text-xs text-primaire-200">Personnel municipal</p>
    </div>
</div>
<nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4" aria-label="Menu">
    @foreach ($sections as $titre => $items)
        <div>
            <p class="px-3 pb-1 text-[11px] font-semibold tracking-wider text-primaire-300 uppercase">{{ $titre }}</p>
            <div class="space-y-0.5">
                @foreach ($items as $item)
                    @php $actif = request()->routeIs($item['actif']); @endphp
                    <a href="{{ route($item['route']) }}" class="nav-lien {{ $actif ? 'nav-lien-actif' : '' }}" @if ($actif) aria-current="page" @endif>
                        <x-icone :nom="$item['icone']" class="size-5 shrink-0" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</nav>
<div class="border-t border-white/10 p-3">
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="nav-lien w-full"><x-icone nom="deconnexion" /> Se déconnecter</button>
    </form>
</div>
