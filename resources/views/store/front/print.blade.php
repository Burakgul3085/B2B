@php
    use App\Domain\Store\Print\PrintFiles;
    use App\Domain\Store\Print\PrintOptions;
    use App\Domain\Support\Money;

    $start = $settings['tiers'][0];
    $config = [
        'urls' => ['start' => route('store.print.start'), 'file' => route('store.print.status', '__T__')],
        'files' => $files,
        'maxBytes' => (int) $settings['max_mb'] * 1024 * 1024,
        'maxFiles' => PrintFiles::MAX_FILES,
    ];
@endphp
<x-layouts.store title="Fotokopi ve Belge Baskı" description="Belgenizi yükleyin, sayfa sayısı ve fiyat anında hesaplansın: renkli veya siyah-beyaz, önlü arkalı, zımbalı ya da spiral ciltli baskı. Ürünlerle aynı sepette sipariş verin." :canonical="route('store.print')">
    <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8" x-data="printOrder(@alpine($config))">
        @include('store.front._breadcrumbs', ['items' => [['Fotokopi', null]]])

        <div class="mt-4">
            <h1 class="flex items-center gap-3 font-display text-2xl font-extrabold tracking-tight sm:text-3xl">
                <span class="flex size-10 items-center justify-center rounded-2xl bg-puzzle-blue/10 text-puzzle-blue"><x-icon name="printer" class="size-5" /></span>
                Fotokopi ve belge baskı
            </h1>
            <p class="mt-1.5 max-w-3xl text-muted">Belgenizi yükleyin; sayfa sayısını biz sayalım, fiyatı seçeneklerinize göre anında görün. Baskılarınızı kırtasiye ürünleriyle aynı sepette sipariş edebilirsiniz.</p>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                {{-- Yükleme alanı --}}
                <section class="rounded-3xl border border-line bg-white p-5 shadow-xs sm:p-6">
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" x-model="consent" class="mt-0.5 size-4 rounded">
                        <span>Yüklediğim belgeleri çoğaltma hakkım olduğunu, telif hakkıyla korunan eserleri (kitap vb.) izinsiz çoğaltmadığımı onaylıyorum.</span>
                    </label>

                    <label class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-10 text-center transition"
                           :class="dragging ? 'border-puzzle-blue bg-puzzle-blue/5' : (consent ? 'border-line hover:border-puzzle-blue' : 'border-line opacity-60')"
                           @dragover.prevent="dragOver()" @dragleave.prevent="dragLeave()" @drop.prevent="drop($event)">
                        <x-icon name="upload" class="size-8 text-puzzle-blue" />
                        <span class="mt-3 font-semibold">Belgeleri sürükleyip bırakın veya seçin</span>
                        <span class="mt-1 text-sm text-muted">PDF, Word, PowerPoint, Excel, OpenDocument, JPG, PNG · dosya başına en fazla {{ $settings['max_mb'] }} MB</span>
                        <input type="file" class="sr-only" multiple accept="{{ $accept }}" @change="pick($event)" :disabled="!consent">
                    </label>
                    <p class="mt-3 text-xs text-muted">İpucu: Sayfa düzeninin bozulmaması için belgeyi mümkünse PDF olarak yükleyin. Word ve PowerPoint dosyaları PDF'e çevrilerek basılır; yazı tipleri farklılık gösterebilir.</p>
                    <p x-show="message" x-text="message" class="mt-3 rounded-xl bg-danger-soft px-3 py-2 text-sm text-danger" x-cloak></p>
                </section>

                {{-- Belgeler --}}
                <template x-for="f in files" :key="f.uid">
                    <article class="rounded-3xl border border-line bg-white p-5 shadow-xs sm:p-6">
                        <div class="flex gap-4">
                            <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-line bg-slate-50">
                                <template x-if="f.preview"><img :src="f.preview" alt="" class="max-h-full max-w-full object-contain"></template>
                                <template x-if="!f.preview"><x-icon name="file" class="size-7 text-slate-300" /></template>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold" x-text="f.name"></p>
                                        <p class="text-sm text-muted"><span x-text="sizeText(f)"></span> · <span x-text="statusText(f)" :class="isFailed(f) ? 'text-danger' : ''"></span></p>
                                    </div>
                                    <button type="button" class="text-sm text-muted hover:text-danger" @click="remove(f)">Kaldır</button>
                                </div>
                                <div x-show="isBusy(f)" class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-puzzle-blue transition-all" :class="f.status === 'processing' ? 'animate-pulse' : ''" :style="progressStyle(f)"></div>
                                </div>
                                <p x-show="f.error" x-text="f.error" class="mt-2 text-sm text-danger"></p>
                            </div>
                        </div>

                        <div x-show="isReady(f)" class="mt-5 grid gap-4 border-t border-line pt-5 sm:grid-cols-2">
                            <fieldset>
                                <legend class="text-sm font-medium">Renk</legend>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    @foreach (PrintOptions::COLORS as $value => $label)
                                        <label class="flex cursor-pointer items-center justify-center rounded-xl border px-3 py-2 text-sm font-medium has-checked:border-puzzle-blue has-checked:bg-puzzle-blue/5 has-checked:text-puzzle-blue">
                                            <input type="radio" value="{{ $value }}" x-model="f.opts.color" @change="requote(f)" class="sr-only">{{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <fieldset>
                                <legend class="text-sm font-medium">Baskı yüzü</legend>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <label class="flex cursor-pointer items-center justify-center rounded-xl border px-3 py-2 text-sm font-medium has-checked:border-puzzle-blue has-checked:bg-puzzle-blue/5 has-checked:text-puzzle-blue">
                                        <input type="radio" value="0" x-model="f.opts.duplex" @change="requote(f)" class="sr-only">Tek yüz
                                    </label>
                                    <label class="flex cursor-pointer items-center justify-center rounded-xl border px-3 py-2 text-sm font-medium has-checked:border-puzzle-blue has-checked:bg-puzzle-blue/5 has-checked:text-puzzle-blue">
                                        <input type="radio" value="1" x-model="f.opts.duplex" @change="requote(f)" class="sr-only">Önlü arkalı
                                    </label>
                                </div>
                            </fieldset>
                            <label class="block">
                                <span class="text-sm font-medium">Sayfa düzeni</span>
                                <select x-model.number="f.opts.layout" @change="requote(f)" class="field-input mt-2">
                                    @foreach (PrintOptions::LAYOUTS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium">Ciltleme</span>
                                <select x-model="f.opts.binding" @change="requote(f)" class="field-input mt-2">
                                    @foreach (PrintOptions::BINDINGS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            </label>
                            @if ($settings['a3_enabled'])
                                <label class="block">
                                    <span class="text-sm font-medium">Kağıt boyutu</span>
                                    <select x-model="f.opts.size" @change="requote(f)" class="field-input mt-2">
                                        @foreach (PrintOptions::SIZES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                    </select>
                                </label>
                            @endif
                            <label class="block">
                                <span class="text-sm font-medium">Kopya</span>
                                <input type="number" min="1" max="{{ $settings['max_copies'] }}" x-model.number="f.opts.copies" @input="requote(f)" class="field-input mt-2">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium">Sayfa aralığı <span class="font-normal text-muted">(boş: tümü)</span></span>
                                <input type="text" maxlength="200" placeholder="ör. 1-10, 15, 20-25" x-model="f.opts.range" @input="requote(f)" class="field-input mt-2">
                            </label>
                            <label class="block sm:col-span-2">
                                <span class="text-sm font-medium">Not <span class="font-normal text-muted">(isteğe bağlı)</span></span>
                                <input type="text" maxlength="300" placeholder="ör. kapak sayfası renkli olsun" x-model="f.opts.note" @input="requote(f)" class="field-input mt-2">
                            </label>
                        </div>

                        <div x-show="isReady(f)" class="mt-5 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-slate-50 p-4">
                            <div class="min-w-0 text-sm">
                                <p class="text-muted" x-text="f.summary"></p>
                                <template x-for="l in f.lines" :key="l[0]"><p class="text-xs text-muted"><span x-text="l[0]"></span> = <span x-text="l[1]"></span></p></template>
                            </div>
                            <div class="flex items-center gap-4">
                                <p class="font-display text-2xl font-extrabold tabular-nums" x-text="f.total || '—'"></p>
                                <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-navy px-5 py-2.5 text-sm font-semibold text-white hover:bg-puzzle-orange disabled:opacity-50"
                                        :disabled="!canAdd(f)" @click="addToCart(f)">
                                    <x-icon name="cart" class="size-4" />
                                    <span x-text="f.added ? 'Sepete eklendi' : 'Sepete ekle'"></span>
                                </button>
                            </div>
                        </div>
                    </article>
                </template>
            </div>

            {{-- Fiyatlar ve bilgiler --}}
            <aside class="h-fit space-y-5 lg:sticky lg:top-40">
                <section class="rounded-3xl border border-line bg-white p-5 shadow-xs">
                    <h2 class="font-display text-lg font-extrabold">Fiyatlar</h2>
                    <p class="mt-1 text-xs text-muted">A4, yüz başı, KDV dahil. Toplam baskı yüzü arttıkça fiyat düşer.</p>
                    <table class="mt-3 w-full text-sm">
                        <thead class="text-xs text-muted"><tr><th class="py-1 text-left font-medium">Yüz</th><th class="py-1 text-right font-medium">S-B</th><th class="py-1 text-right font-medium">Renkli</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($settings['tiers'] as $i => $tier)
                                @php $next = $settings['tiers'][$i + 1]['from'] ?? null; @endphp
                                <tr>
                                    <td class="py-1.5 tabular-nums">{{ $next ? $tier['from'].'–'.($next - 1) : $tier['from'].'+' }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ Money::format($tier['bw']) }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ Money::format($tier['color']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="mt-2 text-xs text-muted">Önlü arkalıda yüz başı {{ Money::format($start['bw_duplex']) }} (S-B) / {{ Money::format($start['color_duplex']) }} (renkli)'dan başlar.</p>
                    <dl class="mt-3 space-y-1 border-t border-line pt-3 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">Zımba (en fazla {{ $settings['staple_max_sheets'] }} yaprak)</dt><dd class="tabular-nums">{{ Money::format($settings['staple']) }}</dd></div>
                        @foreach ($settings['spiral'] as $row)
                            <div class="flex justify-between"><dt class="text-muted">Spiral cilt ({{ $row['to'] }} yaprağa kadar)</dt><dd class="tabular-nums">{{ Money::format($row['price']) }}</dd></div>
                        @endforeach
                    </dl>
                    @if (bccomp((string) $settings['min_total'], '0', 2) > 0)
                        <p class="mt-3 text-xs text-muted">En düşük sipariş tutarı: {{ Money::format($settings['min_total']) }}.</p>
                    @endif
                </section>
                <section class="rounded-3xl border border-line bg-white p-5 text-sm shadow-xs">
                    <h2 class="font-display text-lg font-extrabold">Nasıl çalışır?</h2>
                    <ol class="mt-3 list-decimal space-y-1.5 pl-5 text-muted">
                        <li>Belgenizi yükleyin; sayfa sayısı otomatik çıkarılır.</li>
                        <li>Renk, baskı yüzü, düzen, ciltleme ve kopya seçin.</li>
                        <li>Sepete ekleyin, dilerseniz ürünlerle birlikte ödeyin.</li>
                    </ol>
                    @if ($settings['prep_text'])<p class="mt-3 rounded-xl bg-puzzle-blue/5 px-3 py-2 text-puzzle-blue">{{ $settings['prep_text'] }}</p>@endif
                    <p class="mt-3 text-xs text-muted">Belgeleriniz yalnızca baskı için kullanılır; siparişe dönüşmeyenler {{ $settings['unordered_days'] }} gün, siparişe dönüşenler {{ $settings['ordered_days'] }} gün sonra silinir. Kişiye özel hazırlandığı için baskı siparişlerinde cayma hakkı bulunmaz; baskı hatası varsa yeniden basılır.</p>
                </section>
            </aside>
        </div>
    </div>
</x-layouts.store>
