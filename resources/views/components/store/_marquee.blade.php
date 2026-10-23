{{-- Kayan şerit: $items [{icon, text, badge}], $color, $speed (StoreFooter). İçerik iki kez yazılır; ikinci kopya ekran okuyuculardan gizlenir. --}}
@if ($items)
    @php
        $tone = match ($color) {
            'navy' => ['bar' => 'bg-navy text-white', 'badge' => 'bg-puzzle-sun text-navy', 'dot' => 'bg-white/30'],
            'green' => ['bar' => 'bg-puzzle-green text-white', 'badge' => 'bg-white text-puzzle-green', 'dot' => 'bg-white/50'],
            'blue' => ['bar' => 'bg-puzzle-blue text-white', 'badge' => 'bg-white text-puzzle-blue', 'dot' => 'bg-white/50'],
            'yellow' => ['bar' => 'bg-puzzle-sun text-navy', 'badge' => 'bg-navy text-white', 'dot' => 'bg-navy/30'],
            default => ['bar' => 'bg-puzzle-orange text-white', 'badge' => 'bg-white text-puzzle-orange', 'dot' => 'bg-white/50'],
        };
        $seconds = (\App\Domain\Store\Content\StoreFooter::MARQUEE_SPEEDS[$speed] ?? [null, 6])[1];
        // Kısa listede bant boş kalmasın: en az 6 madde olacak kadar çoğaltılır.
        $reel = array_merge(...array_fill(0, (int) ceil(6 / count($items)), $items));
    @endphp
    <div class="marquee overflow-hidden {{ $tone['bar'] }} print:hidden" role="region" aria-label="Kayan duyurular">
        <div class="marquee-track flex" style="--marquee-duration: {{ count($reel) * $seconds }}s">
            @foreach ([false, true] as $copy)
                <ul class="flex shrink-0 items-center" @if ($copy) aria-hidden="true" @endif>
                    @foreach ($reel as $item)
                        <li class="flex items-center gap-2.5 py-2 pr-8 pl-2 text-[13px] font-semibold whitespace-nowrap sm:text-sm">
                            <x-icon :name="$item['icon']" class="size-4 shrink-0 sm:size-[18px]" />
                            @if ($item['text'])<span>{{ $item['text'] }}</span>@endif
                            @if ($item['badge'])<span class="rounded-md px-2.5 py-1 text-[11px] font-extrabold tracking-wider uppercase shadow-sm sm:text-xs {{ $tone['badge'] }}">{{ $item['badge'] }}</span>@endif
                            <span class="ml-6 size-1.5 rounded-full {{ $tone['dot'] }}" aria-hidden="true"></span>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    </div>
@endif
