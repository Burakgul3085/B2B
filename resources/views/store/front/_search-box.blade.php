{{-- Canlı arama kutusu ve öneri paneli (x-data="liveSearch" olan formun içinde). $inputId: etiketle odaklanmak için. --}}
<div class="relative">
    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted" />
    <input id="{{ $inputId }}" name="q" x-model="q" @input="input" @focus="reopen" autocomplete="off" value="{{ request()->routeIs('store.search') ? request('q') : '' }}"
        placeholder="Ürün, marka veya ürün kodu arayın" aria-label="Mağazada ara"
        class="h-12 w-full rounded-xl border-2 border-line bg-surface pr-24 pl-12 text-sm transition focus:border-navy-ink/40 focus:bg-white focus:ring-4 focus:ring-navy-ink/10 focus:outline-none">
    <button class="absolute top-1.5 right-1.5 h-9 rounded-lg bg-navy px-4 text-sm font-semibold text-white transition hover:bg-navy-2">Ara</button>
</div>
<div x-show="open" x-cloak x-transition.opacity.duration.100ms class="absolute inset-x-0 top-full z-50 mt-2 overflow-hidden rounded-2xl border border-line bg-white shadow-2xl shadow-navy/10 md:inset-x-0">
    <p x-show="loading && ! hasResults" class="px-5 py-4 text-sm text-muted">Aranıyor…</p>
    <p x-show="showEmpty" class="px-5 py-4 text-sm text-muted">Eşleşen ürün bulunamadı. Farklı bir kelime deneyin.</p>
    <div x-show="hasResults" class="grid gap-0 md:grid-cols-3">
        <div class="md:col-span-2 md:border-r md:border-line">
            <p class="px-5 pt-4 text-[11px] font-bold tracking-[0.18em] text-muted uppercase">Ürünler</p>
            <ul class="p-2">
                <template x-for="item in results.products" :key="item.url">
                    <li>
                        <a :href="item.url" class="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-surface">
                            <span class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-[#fff]">
                                <img x-show="item.image" :src="item.image" alt="" class="max-h-full max-w-full object-contain">
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium" x-text="item.name"></span>
                                <span class="block text-xs text-muted" x-text="item.brand"></span>
                            </span>
                            <span class="text-sm font-bold tabular-nums" x-text="item.price"></span>
                        </a>
                    </li>
                </template>
            </ul>
        </div>
        <div class="space-y-4 p-4">
            <div x-show="results.categories.length">
                <p class="text-[11px] font-bold tracking-[0.18em] text-muted uppercase">Kategoriler</p>
                <template x-for="item in results.categories" :key="item.url"><a :href="item.url" class="mt-1 block rounded-lg px-2 py-1.5 text-sm font-medium hover:bg-surface" x-text="item.name"></a></template>
            </div>
            <div x-show="results.brands.length">
                <p class="text-[11px] font-bold tracking-[0.18em] text-muted uppercase">Markalar</p>
                <template x-for="item in results.brands" :key="item.url"><a :href="item.url" class="mt-1 block rounded-lg px-2 py-1.5 text-sm font-medium hover:bg-surface" x-text="item.name"></a></template>
            </div>
            <a :href="results.all" class="flex items-center gap-1 rounded-xl bg-navy-soft px-3 py-2.5 text-sm font-semibold text-navy-ink hover:underline">Tüm sonuçları gör <x-icon name="arrow-right" class="size-4" /></a>
        </div>
    </div>
</div>
