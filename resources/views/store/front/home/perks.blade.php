{{--
    Avantaj şeridi: Üst ve Alt Bilgi → Güven şeridi maddeleri (renkler sırayla yapboz renkleri).
    Eşit hücreler, ince ayraçlar; içerik her hücrede ortalanır (geniş ekranda boşluk sağa yığılmaz).
--}}
@if ($perks)
    @php
        $colors = \App\Domain\Store\Storefront\Palette::CYCLE;
        $cols = [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4'][min(4, count($perks))];
    @endphp
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" aria-label="Avantajlar">
        <ul class="grid grid-cols-1 overflow-hidden rounded-3xl bg-surface/70 ring-1 ring-line sm:grid-cols-2 {{ $cols }}">
            @foreach ($perks as $i => $perk)
                @php $c = \App\Domain\Store\Storefront\Palette::classes($colors[$i % 4]); @endphp
                <li class="group relative flex items-center gap-4 px-6 py-5 sm:justify-center sm:py-6 lg:px-8 [&:not(:first-child)]:border-t [&:not(:first-child)]:border-line sm:[&:nth-child(2)]:border-t-0 sm:[&:nth-child(even)]:border-l lg:[&:not(:first-child)]:border-t-0 lg:[&:not(:first-child)]:border-l">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-line transition group-hover:-translate-y-0.5 {{ $c['text'] }}"><x-icon :name="$perk['icon']" class="size-6" /></span>
                    <span class="min-w-0">
                        <span class="block font-display text-[15px] font-extrabold tracking-tight">{{ $perk['title'] }}</span>
                        @if ($perk['text'])<span class="mt-0.5 block text-sm text-muted">{{ $perk['text'] }}</span>@endif
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
