{{-- Avantaj şeridi: Üst ve Alt Bilgi → Güven şeridi maddeleri (renkler sırayla yapboz renkleri). --}}
@if ($perks)
    @php $colors = \App\Domain\Store\Storefront\Palette::CYCLE; @endphp
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" aria-label="Avantajlar">
        <ul class="grid gap-3 rounded-3xl border border-line bg-white p-3 sm:grid-cols-2 {{ [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4'][min(4, count($perks))] }}">
            @foreach ($perks as $i => $perk)
                @php $c = \App\Domain\Store\Storefront\Palette::classes($colors[$i % 4]); @endphp
                <li class="flex items-center gap-4 rounded-2xl px-4 py-4">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl {{ $c['soft'] }} {{ $c['text'] }}"><x-icon :name="$perk['icon']" class="size-6" /></span>
                    <span><span class="block font-display font-bold">{{ $perk['title'] }}</span>@if ($perk['text'])<span class="block text-sm text-muted">{{ $perk['text'] }}</span>@endif</span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
