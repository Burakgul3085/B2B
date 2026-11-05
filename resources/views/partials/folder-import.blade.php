{{--
    Klasörden toplu ürün ekleme ekranı (B2B toptancı ve mağaza yönetimi ortak görünümü; mantık resources/js/folder-import.js).
    $config: folderImport() ayarları, $taxOptions: [değer => etiket], $productsUrl: ürün listesi, $activateLabel, $includesTaxToggle (yalnızca B2B)
--}}
<div x-data="folderImport(@alpine($config))" class="space-y-6">
    {{-- 1) Klasör seçimi --}}
    <section x-show="phase === 'pick' || phase === 'reading'"
        @dragover.prevent="dragOver()" @dragleave.prevent="dragLeave()" @drop.prevent="drop($event)"
        :class="dragging ? 'border-primary bg-primary/5' : 'border-line bg-white'"
        class="rounded-2xl border-2 border-dashed px-6 py-14 text-center shadow-xs transition">
        <x-icon name="upload" class="mx-auto size-10 text-primary" />
        <p class="mt-4 text-lg font-semibold">Ürün klasörünü buraya sürükleyip bırakın</p>
        <p class="mt-1 text-sm text-muted">"Ürünler" klasörünün tamamını veya yalnızca bir kategori klasörünü bırakabilirsiniz. Önce kontrol edilir, onayınız olmadan hiçbir şey eklenmez.</p>
        <div class="mt-6" x-show="phase === 'pick'">
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-medium shadow-xs hover:bg-slate-50">
                <x-icon name="layers" class="size-4" /> Klasör seç
                <input type="file" class="sr-only" webkitdirectory directory multiple @change="pick($event)">
            </label>
        </div>
        <p class="mt-6 text-sm font-medium text-primary" x-show="phase === 'reading'" x-text="message"></p>
        <p class="mt-6 text-sm text-danger" x-show="phase === 'pick' && message" x-text="message"></p>
    </section>

    {{-- 2) Kontrol sonucu --}}
    <template x-if="phase === 'review'">
        <div class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl border border-line bg-white p-4 shadow-xs"><p class="text-sm text-muted">Bulunan ürün</p><p class="text-2xl font-bold tabular-nums" x-text="summary.products"></p></div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4"><p class="text-sm text-emerald-800">Eklenecek</p><p class="text-2xl font-bold text-emerald-800 tabular-nums" x-text="summary.new"></p></div>
                <div class="rounded-xl border border-line bg-white p-4 shadow-xs"><p class="text-sm text-muted">Zaten var (atlanacak)</p><p class="text-2xl font-bold tabular-nums" x-text="summary.existing"></p></div>
                <div class="rounded-xl border p-4" :class="summary.invalid ? 'border-red-200 bg-danger-soft' : 'border-line bg-white'"><p class="text-sm text-muted">Hatalı (eklenmeyecek)</p><p class="text-2xl font-bold tabular-nums" :class="summary.invalid ? 'text-red-800' : ''" x-text="summary.invalid"></p></div>
            </div>
            <p class="text-sm text-muted">
                <span x-text="summary.options"></span> seçenek, <span x-text="summary.images"></span> resim.
                Yeni oluşturulacak: <b class="text-ink"><span x-text="summary.newCategories"></span> kategori</b>, <b class="text-ink"><span x-text="summary.newBrands"></span> marka</b>.
                <span x-show="notes.unsupported">· <span x-text="notes.unsupported"></span> resim desteklenmeyen biçimde (yalnızca JPG, PNG, WEBP) ve atlanacak.</span>
                <span x-show="notes.empty.length">· <span x-text="notes.empty.length"></span> boş klasör yok sayıldı.</span>
            </p>

            <div class="grid gap-6 lg:grid-cols-3">
                <x-card title="Ayarlar" class="lg:col-span-1">
                    <div class="space-y-4">
                        <div>
                            <p class="mb-2 text-sm font-medium">KDV oranı <span class="font-normal text-muted">· ana kategoriye göre</span></p>
                            <div class="space-y-2">
                                <template x-for="g in taxGroups" :key="g.key">
                                    <label class="flex items-center justify-between gap-3 text-sm">
                                        <span class="min-w-0 truncate"><span x-text="g.name"></span> <span class="text-xs text-muted">(<span x-text="g.count"></span>)</span></span>
                                        <select class="w-28 rounded-lg border-line text-sm" :data-group="g.key" @change="setTax($event)">
                                            @foreach ($taxOptions as $value => $label)
                                                <option value="{{ $value }}" :selected="g.tax === '{{ $value }}'">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </template>
                            </div>
                        </div>
                        @if ($includesTaxToggle)
                            <label class="flex items-start gap-3 text-sm">
                                <input type="checkbox" x-model="includesTax" class="mt-0.5 size-4 rounded border-line text-primary">
                                <span><span class="font-medium">Klasördeki fiyatlar KDV dahil</span><span class="block text-xs text-muted">İşaretlemezseniz fiyatlar KDV hariç kabul edilir.</span></span>
                            </label>
                        @endif
                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" x-model="activate" class="mt-0.5 size-4 rounded border-line text-primary">
                            <span><span class="font-medium">{{ $activateLabel }}</span><span class="block text-xs text-muted">İşaretlemezseniz ürünler kapalı eklenir; kontrol edip ürün listesinden toplu açabilirsiniz.</span></span>
                        </label>
                        <p class="rounded-lg bg-slate-50 p-3 text-xs text-muted">Var olan ürünlere dokunulmaz. Yükleme yarıda kalırsa aynı klasörü yeniden bırakın: eklenenler atlanır, kalanlar eklenir.</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-xs hover:opacity-90 disabled:opacity-50" :disabled="!canStart()" @click="start()">
                                <x-icon name="upload" class="size-4" /> <span><span x-text="summary.new"></span> ürünü ekle</span>
                            </button>
                            <button type="button" class="rounded-lg border border-line bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50" @click="startOver()">Vazgeç</button>
                        </div>
                        <p class="text-sm text-danger" x-show="fatal" x-text="fatal"></p>
                    </div>
                </x-card>

                <div class="space-y-6 lg:col-span-2">
                    <template x-if="problems.length">
                        <x-card title="Hatalı klasörler" description="Bu ürünler eklenmeyecek. Klasörü düzeltip yeniden bırakabilirsiniz; doğru olanlar yine eklenir." :padding="false">
                            <ul class="max-h-80 divide-y divide-line overflow-y-auto text-sm">
                                <template x-for="p in problems" :key="p.path">
                                    <li class="px-5 py-3"><p class="font-mono text-xs break-all text-muted" x-text="p.path"></p><p class="text-red-800" x-text="p.message"></p></li>
                                </template>
                            </ul>
                        </x-card>
                    </template>
                    <template x-if="warnings.length">
                        <details class="rounded-xl border border-line bg-white shadow-xs">
                            <summary class="cursor-pointer px-5 py-4 text-sm font-semibold">Uyarılar (<span x-text="warnings.length"></span>) <span class="font-normal text-muted">· ürünler yine eklenir</span></summary>
                            <ul class="max-h-72 divide-y divide-line overflow-y-auto border-t border-line text-sm">
                                <template x-for="w in warnings" :key="w.path">
                                    <li class="px-5 py-3"><p class="font-mono text-xs break-all text-muted" x-text="w.path"></p><p class="text-amber-800" x-text="w.message"></p></li>
                                </template>
                            </ul>
                        </details>
                    </template>
                    <x-card title="Eklenecek ürünlerden örnekler" description="İlk 50 ürün. Bilgileri klasör adlarından okundu." :padding="false">
                        <div class="overflow-x-auto">
                            <table class="table-base">
                                <thead><tr><th>Ürün</th><th>Kategori</th><th>Marka</th><th class="text-right">Fiyat</th><th class="text-right">Resim</th></tr></thead>
                                <tbody class="divide-y divide-line">
                                    <template x-for="p in preview" :key="p.path">
                                        <tr>
                                            <td><span class="font-medium" x-text="p.name"></span><p class="text-xs text-muted" x-show="p.options" x-text="p.options"></p></td>
                                            <td class="text-sm" x-text="p.category"></td>
                                            <td class="text-sm" x-text="p.brand"></td>
                                            <td class="text-right tabular-nums" x-text="p.price"></td>
                                            <td class="text-right tabular-nums" x-text="p.images"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                </div>
            </div>
        </div>
    </template>

    {{-- 3) Yükleme ve sonuç --}}
    <template x-if="phase === 'uploading' || phase === 'done'">
        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-lg font-semibold">
                    <span x-show="phase === 'uploading' && !paused">Ürünler ekleniyor…</span>
                    <span x-show="phase === 'uploading' && paused">Duraklatıldı</span>
                    <span x-show="phase === 'done'">Tamamlandı</span>
                </p>
                <p class="text-sm text-muted"><span class="tabular-nums" x-text="progress.done"></span> / <span class="tabular-nums" x-text="progress.total"></span> · <span x-text="eta"></span></p>
            </div>
            <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-primary transition-all" :style="barStyle()"></div></div>
            <p class="mt-2 text-sm text-muted" x-show="message" x-text="message"></p>
            <dl class="mt-5 grid grid-cols-3 gap-4 text-sm">
                <div><dt class="text-muted">Eklendi</dt><dd class="text-2xl font-semibold text-emerald-700 tabular-nums" x-text="progress.created"></dd></div>
                <div><dt class="text-muted">Zaten vardı</dt><dd class="text-2xl font-semibold tabular-nums" x-text="progress.skipped"></dd></div>
                <div><dt class="text-muted">Eklenemedi</dt><dd class="text-2xl font-semibold tabular-nums" :class="progress.failed ? 'text-danger' : ''" x-text="progress.failed"></dd></div>
            </dl>
            <p class="mt-4 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900" x-show="fatal" x-text="fatal"></p>
            <p class="mt-4 text-xs text-muted" x-show="phase === 'uploading'">Yükleme bitene kadar bu sekmeyi kapatmayın. Bilgisayar uyku moduna geçerse yükleme durur; aynı klasörü yeniden bırakarak devam edebilirsiniz.</p>

            <div class="mt-5 flex flex-wrap gap-2">
                <button type="button" class="rounded-lg border border-line bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50" x-show="phase === 'uploading'" @click="togglePause()" x-text="paused ? 'Devam et' : 'Duraklat'"></button>
                <a href="{{ $productsUrl }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:opacity-90" x-show="phase === 'done'">Ürünlere git</a>
                <button type="button" class="rounded-lg border border-line bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50" x-show="phase === 'done'" @click="startOver()">Başka klasör yükle</button>
            </div>

            <template x-if="failures.length">
                <div class="mt-6">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-sm font-semibold">Eklenemeyen ürünler</p>
                        <button type="button" class="text-sm font-medium text-primary hover:underline" @click="copyFailures()">Listeyi kopyala</button>
                    </div>
                    <ul class="max-h-80 divide-y divide-line overflow-y-auto rounded-lg border border-line text-sm">
                        <template x-for="f in failures" :key="f.path">
                            <li class="px-4 py-2.5"><p class="font-mono text-xs break-all text-muted" x-text="f.path"></p><p class="text-red-800" x-text="f.message"></p></li>
                        </template>
                    </ul>
                </div>
            </template>
        </x-card>
    </template>
</div>
