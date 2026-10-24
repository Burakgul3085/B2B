{{--
    Çerez tercihi (Gizlilik ve Çerez Politikası): ilk ziyarette altta bant; "Çerez tercihleri" bağlantıları ve
    "Tercihleri yönet" düğmesi kategorili ayrıntılı pencereyi açar. Listelenen çerezler gerçekten kullanılanlardır.
--}}
@php $categories = \App\Domain\Store\Storefront\CookieCatalog::categories(); @endphp
<div x-data="cookieConsent" class="print:hidden">
    {{-- Bant --}}
    <div x-show="banner && ! modal" x-cloak x-transition role="region" aria-label="Çerez bildirimi"
        class="fixed inset-x-0 bottom-0 z-50 border-t border-line bg-white shadow-2xl sm:inset-x-auto sm:bottom-5 sm:left-5 sm:max-w-md sm:rounded-3xl sm:border">
        <div class="p-5 sm:p-6">
            <p class="flex items-center gap-2.5 font-display text-lg font-extrabold">
                <span class="flex size-9 items-center justify-center rounded-xl bg-puzzle-orange-soft text-puzzle-orange"><x-icon name="shield" class="size-5" /></span>
                Çerezleri kullanıyoruz
            </p>
            <p class="mt-2.5 text-sm leading-relaxed text-muted">Sitemizin çalışması için zorunlu çerezler kullanırız. Tema tercihiniz gibi işlevsel çerezler ile analitik ve pazarlama çerezleri yalnızca izninizle çalışır.
                <a href="{{ route('store.legal', 'gizlilik-cerez') }}" class="font-semibold text-navy-ink underline">Çerez Politikası</a></p>
            <div class="mt-4 grid grid-cols-2 gap-2">
                <button type="button" @click="acceptAll()" class="col-span-2 h-11 rounded-xl bg-navy text-sm font-semibold text-white transition hover:bg-puzzle-orange">Tümünü kabul et</button>
                <button type="button" @click="rejectAll()" class="h-11 rounded-xl border border-line text-sm font-semibold transition hover:bg-surface">Yalnızca zorunlu</button>
                <button type="button" @click="openModal()" class="h-11 rounded-xl border border-line text-sm font-semibold transition hover:bg-surface">Tercihleri yönet</button>
            </div>
        </div>
    </div>

    {{-- Ayrıntılı tercihler --}}
    <div x-show="modal" x-cloak class="fixed inset-0 z-[80] flex items-end justify-center bg-[#0b1631]/60 backdrop-blur-sm sm:items-center sm:p-4"
        @click.self="closeModal()" @keydown.window.escape="closeModal()" role="dialog" aria-modal="true" aria-labelledby="cerez-baslik">
        <div x-show="modal" x-transition class="flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:max-w-2xl sm:rounded-3xl">
            <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-5">
                <div class="flex items-center gap-3">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-[#0b1631] text-amber-400"><x-icon name="shield" class="size-5" /></span>
                    <div>
                        <h2 id="cerez-baslik" class="font-display text-xl font-extrabold">Çerez tercihleri</h2>
                        <p class="text-xs text-muted">Tercihinizi istediğiniz zaman alt bilgideki “Çerez tercihleri”nden değiştirebilirsiniz.</p>
                    </div>
                </div>
                <button type="button" @click="closeModal()" class="flex size-10 shrink-0 items-center justify-center rounded-xl text-muted transition hover:bg-surface hover:text-ink" aria-label="Kapat"><x-icon name="x" class="size-5" /></button>
            </div>

            <div class="flex-1 space-y-3 overflow-y-auto px-6 py-5">
                <p class="text-sm leading-relaxed text-slate-600">Sitemizde hangi çerezlerin kullanılacağını aşağıdan seçebilirsiniz. Zorunlu çerezler sitenin çalışması için gereklidir ve kapatılamaz. Ayrıntılar için
                    <a href="{{ route('store.legal', 'gizlilik-cerez') }}" class="font-semibold text-navy-ink underline">Gizlilik ve Çerez Politikası</a>.</p>

                @foreach ($categories as $key => $category)
                    <details class="group rounded-2xl border border-line bg-white open:shadow-sm">
                        <summary class="flex cursor-pointer list-none items-center gap-4 px-4 py-4 [&::-webkit-details-marker]:hidden">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-surface text-navy-ink"><x-icon :name="$category['icon']" class="size-5" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2 font-semibold">{{ $category['title'] }}
                                    <x-icon name="chevron-down" class="size-4 text-muted transition group-open:rotate-180" /></span>
                                <span class="mt-0.5 line-clamp-1 block text-xs text-muted group-open:hidden">{{ $category['text'] }}</span>
                            </span>
                            @if ($category['required'])
                                <span class="shrink-0 rounded-full bg-[#34d77b]/15 px-3 py-1 text-[11px] font-bold text-green-700">Her zaman açık</span>
                            @else
                                <button type="button" role="switch" @click.prevent="toggle(@alpine($key))" :aria-checked="isOn(@alpine($key))" aria-label="{{ $category['title'] }}"
                                    :class="isOn(@alpine($key)) ? 'bg-navy' : 'bg-slate-300'" class="relative h-7 w-12 shrink-0 rounded-full transition">
                                    <span :class="isOn(@alpine($key)) ? 'translate-x-5' : 'translate-x-0'" class="absolute top-1 left-1 size-5 rounded-full bg-white shadow transition"></span>
                                </button>
                            @endif
                        </summary>
                        <div class="border-t border-line px-4 py-4">
                            <p class="text-sm leading-relaxed text-slate-600">{{ $category['text'] }}</p>
                            @if ($category['cookies'])
                                <div class="mt-3 overflow-x-auto rounded-xl border border-line">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-surface text-muted"><tr><th class="px-3 py-2 font-semibold">Çerez</th><th class="px-3 py-2 font-semibold">Amacı</th><th class="px-3 py-2 font-semibold whitespace-nowrap">Süre</th></tr></thead>
                                        <tbody class="divide-y divide-line">
                                            @foreach ($category['cookies'] as [$name, $purpose, $duration])
                                                <tr><td class="px-3 py-2 font-mono text-[11px] whitespace-nowrap">{{ $name }}</td><td class="px-3 py-2 text-slate-600">{{ $purpose }}</td><td class="px-3 py-2 whitespace-nowrap text-slate-600">{{ $duration }}</td></tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="mt-3 rounded-xl bg-surface px-3 py-2 text-xs text-muted">Bu kategoride şu anda çerez kullanılmıyor.</p>
                            @endif
                        </div>
                    </details>
                @endforeach
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-line bg-surface/60 px-6 py-4 sm:flex-row sm:justify-end">
                <button type="button" @click="rejectAll()" class="h-11 rounded-xl border border-line bg-white px-5 text-sm font-semibold transition hover:bg-surface">Tümünü reddet</button>
                <button type="button" @click="saveChoices()" class="h-11 rounded-xl border border-navy/20 bg-white px-5 text-sm font-semibold text-navy-ink transition hover:bg-navy hover:text-white">Seçimlerimi kaydet</button>
                <button type="button" @click="acceptAll()" class="h-11 rounded-xl bg-navy px-5 text-sm font-semibold text-white transition hover:bg-puzzle-orange">Tümünü kabul et</button>
            </div>
        </div>
    </div>
</div>
