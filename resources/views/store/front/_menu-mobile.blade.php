{{-- Mobil adım adım kategori menüsü; menü ilk açıldığında yüklenir (header.blade.php › drillMenu). --}}
@php
    use App\Domain\Store\Storefront\Palette;
@endphp
<div x-init="setNames(@alpine($menuNames))">
<div x-show="isScreen('')">
    <p class="px-4 pt-5 pb-2 text-[11px] font-bold tracking-[0.2em] text-muted uppercase">Kategoriler</p>
    @if ($menu->count() > 8)
        <label class="relative mx-4 mb-2 block">
            <span class="sr-only">Kategori ara</span>
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
            <input type="search" x-model="query" placeholder="Kategori ara…" autocomplete="off"
                class="h-10 w-full rounded-xl border-0 bg-surface pr-3 pl-9 text-sm ring-1 ring-line placeholder:text-muted focus:bg-white focus:ring-2 focus:ring-puzzle-orange">
        </label>
    @endif
    <ul class="px-2">
        @foreach ($menu as $i => $root)
            @php $c = Palette::classes($root->palette); @endphp
            <li x-show="matches({{ $i }})">
                @if ($root->children->isNotEmpty())
                    <button type="button" @click="go('r{{ $root->id }}')" class="flex w-full items-center gap-3 rounded-xl px-2 py-2.5 text-left text-sm font-semibold active:bg-surface">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg font-display text-sm font-extrabold text-white {{ $c['solid'] }}">{{ mb_substr($root->name, 0, 1) }}</span>
                        <span class="min-w-0 flex-1 truncate">{{ $root->name }}</span>
                        <x-icon name="chevron-down" class="size-4 shrink-0 -rotate-90 text-muted" />
                    </button>
                @else
                    <a href="{{ route('store.category', $root->slug) }}" class="flex items-center gap-3 rounded-xl px-2 py-2.5 text-sm font-semibold active:bg-surface">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg font-display text-sm font-extrabold text-white {{ $c['solid'] }}">{{ mb_substr($root->name, 0, 1) }}</span>
                        <span class="min-w-0 flex-1 truncate">{{ $root->name }}</span>
                    </a>
                @endif
            </li>
        @endforeach
        <li x-show="noMatch" x-cloak class="px-3 py-6 text-center text-sm text-muted">Eşleşen kategori yok.</li>
    </ul>
</div>

@foreach ($menu as $root)
    @continue($root->children->isEmpty())
    @php $c = Palette::classes($root->palette); @endphp
    <div x-show="isScreen('r{{ $root->id }}')" x-cloak x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-6 opacity-0" x-transition:enter-end="translate-x-0 opacity-100">
        @include('components.store._drill-head', ['back' => 'Tüm kategoriler', 'title' => $root->name, 'url' => route('store.category', $root->slug), 'tone' => $c])
        <ul class="px-2 pb-2">
            @foreach ($root->children as $child)
                <li>
                    @if ($child->children->isNotEmpty())
                        <button type="button" @click="go('c{{ $child->id }}')" class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-medium active:bg-surface">
                            <span class="size-1.5 shrink-0 rounded-full {{ $c['solid'] }}"></span>
                            <span class="min-w-0 flex-1">{{ $child->name }}</span>
                            <span class="text-[11px] text-muted tabular-nums">{{ $child->children->count() }}</span>
                            <x-icon name="chevron-down" class="size-4 shrink-0 -rotate-90 text-muted" />
                        </button>
                    @else
                        <a href="{{ route('store.category', $child->slug) }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium active:bg-surface">
                            <span class="size-1.5 shrink-0 rounded-full {{ $c['solid'] }}"></span>{{ $child->name }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
    @foreach ($root->children as $child)
        @continue($child->children->isEmpty())
        <div x-show="isScreen('c{{ $child->id }}')" x-cloak x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-6 opacity-0" x-transition:enter-end="translate-x-0 opacity-100">
            @include('components.store._drill-head', ['back' => $root->name, 'title' => $child->name, 'url' => route('store.category', $child->slug), 'tone' => $c])
            <ul class="px-2 pb-2">
                @foreach ($child->children as $grandchild)
                    <li><a href="{{ route('store.category', $grandchild->slug) }}" class="block rounded-xl px-3 py-3 text-sm text-ink/80 active:bg-surface">{{ $grandchild->name }}</a></li>
                @endforeach
            </ul>
        </div>
    @endforeach
@endforeach
</div>
