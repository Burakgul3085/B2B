{{-- Tüm kategoriler açılır paneli; ilk açılışta CategoryMenuController ile yüklenir (header.blade.php › megaMenu). --}}
@php
    use App\Domain\Store\Storefront\Palette;

    $moreAfter = 6;
@endphp
<div x-init="setNames(@alpine($menuNames))" class="mx-auto flex h-[min(72vh,600px)] max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="flex w-72 shrink-0 flex-col border-r border-line py-4 pr-3 xl:w-80">
        <label class="relative mb-3 block">
            <span class="sr-only">Kategori ara</span>
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
            <input type="search" x-model="query" placeholder="Kategori ara…" autocomplete="off"
                class="h-10 w-full rounded-xl border-0 bg-surface pr-3 pl-9 text-sm ring-1 ring-line transition placeholder:text-muted focus:bg-white focus:ring-2 focus:ring-puzzle-orange">
        </label>
        <ul class="filter-scroll -mr-1 flex-1 space-y-0.5 overflow-y-auto pr-1">
            @foreach ($menu as $i => $root)
                @php $c = Palette::classes($root->palette); @endphp
                <li x-show="matches({{ $i }})">
                    <a href="{{ route('store.category', $root->slug) }}" @mouseenter="show({{ $i }})" @focus="pick({{ $i }})"
                        :class="isActive({{ $i }}) ? '{{ $c['soft'] }} text-ink shadow-xs' : 'text-ink/75 hover:bg-surface'"
                        class="group flex items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-semibold transition duration-200">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-lg font-display text-[13px] font-extrabold text-white transition duration-200 group-hover:scale-110 {{ $c['solid'] }}">{{ mb_substr($root->name, 0, 1) }}</span>
                        <span class="min-w-0 flex-1 truncate">{{ $root->name }}</span>
                        @if ($root->children->isNotEmpty())<span class="text-[11px] font-medium text-muted tabular-nums">{{ $root->children->count() }}</span>@endif
                        <x-icon name="chevron-down" class="size-4 shrink-0 -rotate-90 text-muted transition duration-200" ::class="isActive({{ $i }}) && 'translate-x-0.5 text-ink'" />
                    </a>
                </li>
            @endforeach
            <li x-show="noMatch" x-cloak class="px-3 py-6 text-center text-sm text-muted">Eşleşen kategori yok.</li>
        </ul>
    </div>

    <div class="relative min-w-0 flex-1" @mouseenter="hold">
        @foreach ($menu as $i => $root)
            @php $c = Palette::classes($root->palette); @endphp
            <div x-show="isActive({{ $i }})" @if ($i) x-cloak @endif
                x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-2 opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
                class="filter-scroll absolute inset-0 overflow-y-auto py-5 pr-2 pl-8">
                <div class="flex items-center gap-4 border-b border-line pb-4">
                    @if ($root->image_path)
                        <img src="{{ route('store.media.category', ['storeCategory' => $root->id, 'w' => 160]) }}" alt="" loading="lazy" class="size-14 shrink-0 rounded-xl bg-surface object-contain ring-1 ring-line">
                    @else
                        <span class="flex size-14 shrink-0 items-center justify-center rounded-xl font-display text-2xl font-extrabold text-white {{ $c['solid'] }}">{{ mb_substr($root->name, 0, 1) }}</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="font-display text-xl font-extrabold tracking-tight">{{ $root->name }}</p>
                        @if ($root->tagline)<p class="truncate text-sm text-muted">{{ $root->tagline }}</p>@endif
                    </div>
                    <a href="{{ route('store.category', $root->slug) }}" class="group/all inline-flex shrink-0 items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-px hover:shadow-md {{ $c['solid'] }}">
                        Tümünü gör <x-icon name="arrow-right" class="size-4 transition group-hover/all:translate-x-0.5" />
                    </a>
                </div>
                @if ($root->children->isNotEmpty())
                    {{-- 2. seviye başlık, altında 3. seviye; bloklar sütunlara kendiliğinden dağılır. --}}
                    <div class="mt-5 columns-2 gap-8 xl:columns-3">
                        @foreach ($root->children as $child)
                            <div class="mb-5 break-inside-avoid">
                                <a href="{{ route('store.category', $child->slug) }}" class="group/child flex items-center gap-2 border-b border-line/70 pb-1.5 text-sm font-bold text-ink transition hover:text-puzzle-orange">
                                    <span class="size-1.5 shrink-0 rounded-full transition group-hover/child:scale-150 {{ $c['solid'] }}"></span>{{ $child->name }}
                                </a>
                                @if ($child->children->isNotEmpty())
                                    <ul class="mt-1.5 space-y-0.5">
                                        @foreach ($child->children->take($moreAfter) as $grandchild)
                                            <li><a href="{{ route('store.category', $grandchild->slug) }}" class="block py-0.5 text-[13px] text-ink/70 transition hover:translate-x-0.5 hover:text-ink">{{ $grandchild->name }}</a></li>
                                        @endforeach
                                        @if ($child->children->count() > $moreAfter)
                                            <li><a href="{{ route('store.category', $child->slug) }}" class="inline-flex items-center gap-1 pt-0.5 text-[13px] font-semibold {{ $c['text'] }} hover:underline">+{{ $child->children->count() - $moreAfter }} daha <x-icon name="arrow-right" class="size-3.5" /></a></li>
                                        @endif
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-8 rounded-2xl border border-dashed border-line px-6 py-10 text-center">
                        <p class="text-sm text-muted">Bu kategorideki tüm ürünleri görmek için "Tümünü gör"e tıklayın.</p>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
