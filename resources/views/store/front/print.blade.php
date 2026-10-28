{{--
    Fotokopi sayfası: lacivert giriş bandı (başlangıç fiyatı), adımlar, yükleme alanı ve belge kartları;
    sağda fiyat listesi ve güvenceler, altta sık sorulanlar. Seçenekler seçim kartlarıdır; fiyat sunucudan gelir (printOrder).
--}}
@php
    use App\Domain\Store\Print\PrintFiles;
    use App\Domain\Store\Storefront\Palette;
    use App\Domain\Support\Money;

    $last = $settings['tiers'][count($settings['tiers']) - 1];
    $config = [
        'urls' => ['start' => route('store.print.start'), 'file' => route('store.print.status', '__T__')],
        'files' => $files,
        'maxBytes' => (int) $settings['max_mb'] * 1024 * 1024,
        'maxFiles' => PrintFiles::MAX_FILES,
        'maxCopies' => (int) $settings['max_copies'],
    ];
    $types = [['PDF', 'bg-red-50 text-red-600'], ['Word', 'bg-puzzle-blue-soft text-puzzle-blue'], ['PowerPoint', 'bg-puzzle-orange-soft text-puzzle-orange'],
        ['Excel', 'bg-puzzle-green-soft text-puzzle-green'], ['JPG · PNG', 'bg-violet-50 text-violet-700']];
    $steps = [['upload', 'Belgeni yükle', 'Sayfa sayısı otomatik çıkarılır.'], ['settings', 'Baskıyı seç', 'Renk, yüz, düzen, cilt ve kopya.'], ['cart', 'Sepete ekle', 'Ürünlerinle birlikte tek ödeme.']];
    $minSpiral = collect($settings['spiral'])->sortBy('to')->first();
    // Seçim kartlarının görünümü (seçili / değil); değer karşılaştırması bileşende (isChoice).
    $choice = fn (string $key, string $value) => "isChoice(f, '{$key}', '{$value}') ? 'border-puzzle-blue bg-puzzle-blue-soft text-ink ring-2 ring-puzzle-blue/25' : 'border-line bg-white text-muted hover:border-slate-300 hover:text-ink'";
    $faq = [
        ['Sayfa sayısı nasıl hesaplanıyor?', 'Belgen yüklendiğinde sunucumuzda açılır ve sayfaları sayılır. Word, PowerPoint ve Excel dosyaları baskı için PDF\'e çevrilir; fiyat bu sayfa sayısına göre hesaplanır.'],
        ['Bir yüze 2 veya 4 sayfa ne demek?', 'Belgenin 2 ya da 4 sayfası küçültülerek kağıdın tek yüzüne yan yana basılır. Ders notu ve sunumlarda kağıttan ve ücretten tasarruf sağlar.'],
        ['Sayfa düzenim bozulur mu?', 'PDF dosyalar olduğu gibi basılır. Word ve PowerPoint dosyalarında yazı tipleri farklılık gösterebileceği için mümkünse belgeyi PDF olarak kaydedip yükle.'],
        ['Baskılarım nasıl teslim edilir?', 'Baskılar siparişinin parçasıdır; ürünlerinle birlikte ödeme adımında seçtiğin teslimat yöntemiyle gönderilir.'],
        ['Belgelerim güvende mi?', 'Belgelerin herkese açık bir adreste tutulmaz; yalnızca sen ve baskıyı hazırlayan ekibimiz erişir. Siparişe dönüşmeyen belgeler '.$settings['unordered_days'].' gün, siparişteki belgeler '.$settings['ordered_days'].' gün sonra kendiliğinden silinir.'],
        ['İade edebilir miyim?', 'Baskılar kişiye özel hazırlandığı için cayma hakkı kapsamında değildir. Baskı hatası olursa ücretsiz olarak yeniden basılır.'],
    ];
@endphp
<x-layouts.store title="Fotokopi ve Belge Baskı" description="Belgenizi yükleyin, sayfa sayısı ve fiyat anında hesaplansın: renkli veya siyah-beyaz, önlü arkalı, zımbalı ya da spiral ciltli baskı. Ürünlerle aynı sepette sipariş verin." :canonical="route('store.print')">
    <div x-data="printOrder(@alpine($config))">
        {{-- Giriş bandı --}}
        <section class="relative isolate overflow-hidden bg-navy text-white">
            <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                <div class="absolute -top-24 -right-16 size-96 rounded-full bg-sky-500/25 blur-3xl"></div>
                <div class="absolute -bottom-32 left-1/4 size-80 rounded-full bg-orange-500/20 blur-3xl"></div>
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgb(255_255_255/0.07)_1px,transparent_0)] [background-size:22px_22px]"></div>
            </div>
            <div class="mx-auto max-w-7xl px-4 pt-5 pb-24 sm:px-6 sm:pb-28 lg:px-8">
                <nav class="text-sm text-white/60" aria-label="Sayfa yolu"><a href="{{ route('store.home') }}" class="hover:text-white">Ana sayfa</a> <span class="mx-1">›</span> <span class="text-white/90">Fotokopi</span></nav>
                <div class="mt-8 grid items-end gap-8 lg:grid-cols-[1.4fr_1fr]">
                    <div>
                        <p class="flex items-center gap-2 text-xs font-bold tracking-[0.25em] text-puzzle-sun uppercase">
                            <span class="inline-flex gap-0.5" aria-hidden="true"><span class="size-2 rounded-[3px] bg-orange-500"></span><span class="size-2 rounded-[3px] bg-green-500"></span><span class="size-2 rounded-[3px] bg-sky-400"></span><span class="size-2 rounded-[3px] bg-puzzle-sun"></span></span>
                            Çevrimiçi fotokopi
                        </p>
                        <h1 class="mt-4 font-display text-3xl leading-[1.1] font-extrabold tracking-tight sm:text-5xl">Belgeni yükle,<br class="hidden sm:block"> <span class="text-puzzle-sun">biz basalım.</span></h1>
                        <p class="mt-4 max-w-xl text-base leading-relaxed text-white/75 sm:text-lg">Ders notu, ödev, tez, sunum… Sayfa sayısını biz sayalım, fiyatı seçeneklerine göre anında gör. Baskılarını kırtasiye ürünleriyle aynı sepette sipariş et.</p>
                    </div>
                    <div class="flex flex-wrap gap-3 lg:justify-end">
                        @foreach (['Siyah-beyaz' => $last['bw_duplex'], 'Renkli' => $last['color_duplex']] as $label => $price)
                            <div class="min-w-36 rounded-2xl bg-white/10 px-5 py-4 ring-1 ring-white/15 backdrop-blur">
                                <p class="text-xs font-medium text-white/60">{{ $label }}</p>
                                <p class="mt-0.5 font-display text-2xl font-extrabold tabular-nums">{{ Money::format($price) }}<span class="text-sm font-semibold text-white/60">'dan</span></p>
                                <p class="text-[11px] text-white/50">yüz başı</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <div class="mx-auto -mt-16 max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
            {{-- Adımlar --}}
            <ol class="relative grid gap-1 rounded-3xl bg-white p-3 shadow-xl shadow-navy/5 ring-1 ring-line sm:grid-cols-3 sm:p-4">
                @foreach ($steps as $i => [$icon, $title, $text])
                    @php $c = Palette::classes(Palette::CYCLE[$i]); @endphp
                    <li class="flex items-center gap-3 rounded-2xl px-3 py-2.5">
                        <span class="relative flex size-11 shrink-0 items-center justify-center rounded-2xl {{ $c['soft'] }} {{ $c['text'] }}">
                            <x-icon :name="$icon" class="size-5" />
                            <span class="absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full bg-navy text-[10px] font-bold text-white ring-2 ring-white">{{ $i + 1 }}</span>
                        </span>
                        <span class="min-w-0"><span class="block text-sm font-bold text-ink">{{ $title }}</span><span class="block text-xs text-muted">{{ $text }}</span></span>
                    </li>
                @endforeach
            </ol>

            <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_22rem] xl:grid-cols-[1fr_24rem]">
                <div class="min-w-0 space-y-6">
                    {{-- Yükleme alanı --}}
                    <section class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-line sm:p-7" aria-labelledby="yukle">
                        <h2 id="yukle" class="font-display text-xl font-extrabold tracking-tight">Belgelerini yükle</h2>
                        <p class="mt-1 text-sm text-muted">Birden fazla belge seçebilirsin; her birinin baskı ayarı ayrıdır.</p>

                        <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-2xl p-3.5 text-sm transition" :class="consent ? 'bg-puzzle-green-soft' : 'bg-surface'">
                            <input type="checkbox" x-model="consent" class="mt-0.5 size-4 shrink-0 rounded text-puzzle-green">
                            <span class="leading-relaxed">Yüklediğim belgeleri çoğaltma hakkım olduğunu, telif hakkıyla korunan eserleri (kitap vb.) izinsiz çoğaltmadığımı onaylıyorum.</span>
                        </label>

                        <label class="group relative mt-4 flex flex-col items-center justify-center overflow-hidden rounded-3xl border-2 border-dashed px-6 py-12 text-center transition duration-300"
                               :class="dragging ? 'scale-[1.01] border-puzzle-blue bg-puzzle-blue-soft' : (consent ? 'cursor-pointer border-slate-300 hover:border-puzzle-blue hover:bg-puzzle-blue-soft' : 'cursor-not-allowed border-line bg-surface opacity-70')"
                               @dragover.prevent="dragOver()" @dragleave.prevent="dragLeave()" @drop.prevent="drop($event)">
                            <span class="flex size-16 items-center justify-center rounded-2xl bg-navy text-white shadow-lg shadow-navy/20 transition duration-300 group-hover:-translate-y-1">
                                <x-icon name="upload" class="size-7" />
                            </span>
                            <span class="mt-5 font-display text-lg font-bold text-ink">Dosyaları buraya sürükle</span>
                            <span class="mt-1 text-sm text-muted">ya da <span class="font-semibold text-puzzle-blue underline decoration-2 underline-offset-4">bilgisayarından seç</span> · dosya başına en fazla {{ $settings['max_mb'] }} MB</span>
                            <span class="mt-5 flex flex-wrap justify-center gap-1.5">
                                @foreach ($types as [$label, $cls])<span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $cls }}">{{ $label }}</span>@endforeach
                            </span>
                            <input type="file" class="sr-only" multiple accept="{{ $accept }}" @change="pick($event)" :disabled="!consent">
                        </label>
                        <p x-show="!consent" class="mt-3 text-center text-xs text-muted">Yüklemeye başlamak için yukarıdaki onay kutusunu işaretle.</p>
                        <p x-show="message" x-text="message" class="mt-3 rounded-xl bg-danger-soft px-3.5 py-2.5 text-sm font-medium text-danger" x-cloak></p>
                        <p class="mt-4 flex items-start gap-2 rounded-2xl bg-puzzle-yellow-soft px-3.5 py-3 text-xs leading-relaxed text-ink">
                            <x-icon name="alert" class="mt-px size-4 shrink-0 text-puzzle-yellow" />
                            <span><strong>İpucu:</strong> Sayfa düzeninin aynen korunması için belgeyi mümkünse PDF olarak yükle. Word ve PowerPoint dosyaları PDF'e çevrilerek basılır; yazı tipleri farklılık gösterebilir.</span>
                        </p>
                    </section>

                    {{-- Belgeler --}}
                    <template x-for="f in files" :key="f.uid">
                        <article class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-line">
                            <div class="flex gap-4 p-5 sm:p-6">
                                <div class="relative flex h-24 w-[4.5rem] shrink-0 items-center justify-center overflow-hidden rounded-xl bg-surface shadow-inner ring-1 ring-line">
                                    <template x-if="f.preview"><img :src="f.preview" alt="" class="h-full w-full object-cover object-top"></template>
                                    <template x-if="!f.preview"><x-icon name="file" class="size-7 text-slate-300" /></template>
                                    <span class="absolute bottom-1 left-1 rounded-md px-1.5 py-0.5 text-[9px] font-extrabold ring-1" :class="extClass(f)" x-text="extLabel(f)"></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate font-display text-base font-bold text-ink" x-text="f.name"></p>
                                            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted">
                                                <span x-text="sizeText(f)"></span>
                                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold"
                                                      :class="isReady(f) ? 'bg-puzzle-green-soft text-puzzle-green' : (isFailed(f) ? 'bg-danger-soft text-danger' : 'bg-puzzle-blue-soft text-puzzle-blue')">
                                                    <span class="size-1.5 rounded-full bg-current" :class="isBusy(f) ? 'animate-pulse' : ''"></span>
                                                    <span x-text="statusText(f)"></span>
                                                </span>
                                            </p>
                                        </div>
                                        <button type="button" class="flex size-9 shrink-0 items-center justify-center rounded-xl text-muted transition hover:bg-danger-soft hover:text-danger" @click="remove(f)" aria-label="Belgeyi kaldır"><x-icon name="trash" class="size-4" /></button>
                                    </div>
                                    <div x-show="isBusy(f)" class="mt-4 h-2 overflow-hidden rounded-full bg-surface">
                                        <div class="h-full rounded-full bg-gradient-to-r from-puzzle-blue to-sky-400 transition-all duration-300" :class="f.status === 'processing' ? 'animate-pulse' : ''" :style="progressStyle(f)"></div>
                                    </div>
                                    <p x-show="f.error" x-text="f.error" class="mt-3 rounded-xl bg-danger-soft px-3 py-2 text-sm text-danger"></p>
                                </div>
                            </div>

                            <div x-show="isReady(f)" class="space-y-6 border-t border-line px-5 py-6 sm:px-6">
                                <div class="grid gap-6 sm:grid-cols-2">
                                    <div role="group" aria-label="Renk">
                                        <p class="text-xs font-bold tracking-wider text-muted uppercase">Renk</p>
                                        <div class="mt-2 grid grid-cols-2 gap-2">
                                            <button type="button" class="flex items-center gap-2.5 rounded-2xl border px-3 py-3 text-left text-sm font-semibold transition" :class="{{ $choice('color', 'bw') }}" @click="choose(f, 'color', 'bw')">
                                                <span class="size-6 shrink-0 rounded-full bg-gradient-to-br from-slate-200 via-slate-500 to-slate-900 ring-2 ring-white" aria-hidden="true"></span> Siyah-beyaz
                                            </button>
                                            <button type="button" class="flex items-center gap-2.5 rounded-2xl border px-3 py-3 text-left text-sm font-semibold transition" :class="{{ $choice('color', 'color') }}" @click="choose(f, 'color', 'color')">
                                                <span class="size-6 shrink-0 rounded-full bg-[conic-gradient(#ea580c,#facc15,#16a34a,#0284c7,#ea580c)] ring-2 ring-white" aria-hidden="true"></span> Renkli
                                            </button>
                                        </div>
                                    </div>
                                    <div role="group" aria-label="Baskı yüzü">
                                        <p class="text-xs font-bold tracking-wider text-muted uppercase">Baskı yüzü</p>
                                        <div class="mt-2 grid grid-cols-2 gap-2">
                                            <button type="button" class="flex items-center gap-2.5 rounded-2xl border px-3 py-3 text-left text-sm font-semibold transition" :class="{{ $choice('duplex', '0') }}" @click="choose(f, 'duplex', '0')">
                                                <span class="relative h-6 w-5 shrink-0" aria-hidden="true"><span class="absolute inset-0 rounded-[3px] border-2 border-current"></span></span> Tek yüz
                                            </button>
                                            <button type="button" class="flex items-center gap-2.5 rounded-2xl border px-3 py-3 text-left text-sm font-semibold transition" :class="{{ $choice('duplex', '1') }}" @click="choose(f, 'duplex', '1')">
                                                <span class="relative h-6 w-6 shrink-0" aria-hidden="true"><span class="absolute top-0 right-0 h-5 w-4 rounded-[3px] border-2 border-current opacity-40"></span><span class="absolute bottom-0 left-0 h-5 w-4 rounded-[3px] border-2 border-current bg-white"></span></span> Önlü arkalı
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div role="group" aria-label="Bir yüze kaç sayfa">
                                    <p class="text-xs font-bold tracking-wider text-muted uppercase">Bir yüze kaç sayfa</p>
                                    <div class="mt-2 grid grid-cols-3 gap-2">
                                        @foreach ([1 => 'grid-cols-1', 2 => 'grid-cols-2', 4 => 'grid-cols-2'] as $n => $grid)
                                            <button type="button" class="flex flex-col items-center gap-2 rounded-2xl border px-2 py-3 text-sm font-semibold transition" :class="{{ $choice('layout', (string) $n) }}" @click="choose(f, 'layout', {{ $n }})">
                                                <span class="grid h-10 w-8 {{ $grid }} gap-0.5 rounded-[4px] border-2 border-current p-0.5" aria-hidden="true">
                                                    @for ($i = 0; $i < $n; $i++)<span class="rounded-[1px] bg-current opacity-30"></span>@endfor
                                                </span>
                                                {{ $n }} sayfa
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <div role="group" aria-label="Ciltleme">
                                    <p class="text-xs font-bold tracking-wider text-muted uppercase">Ciltleme</p>
                                    <div class="mt-2 grid grid-cols-3 gap-2">
                                        @foreach (['none' => ['Ciltsiz', 'Yaprak hâlinde'], 'staple' => ['Zımbalı', Money::format($settings['staple'])], 'spiral' => ['Spiral', $minSpiral ? Money::format($minSpiral['price'])."'dan" : '']] as $value => [$label, $hint])
                                            <button type="button" class="flex flex-col items-center gap-0.5 rounded-2xl border px-2 py-3 text-center transition" :class="{{ $choice('binding', $value) }}" @click="choose(f, 'binding', '{{ $value }}')">
                                                <span class="text-sm font-semibold">{{ $label }}</span>
                                                <span class="text-[11px] font-medium opacity-70">{{ $hint }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="grid gap-5 sm:grid-cols-[auto_1fr]">
                                    <div>
                                        <p class="text-xs font-bold tracking-wider text-muted uppercase">Kopya</p>
                                        <div class="mt-2 inline-flex items-center rounded-2xl border border-line bg-white p-1">
                                            <button type="button" class="flex size-10 items-center justify-center rounded-xl text-lg font-bold text-ink hover:bg-surface" @click="stepCopies(f, -1)" aria-label="Kopya azalt">−</button>
                                            <input type="number" min="1" max="{{ $settings['max_copies'] }}" x-model.number="f.opts.copies" @input="requote(f)" class="w-14 border-0 bg-transparent text-center font-display text-lg font-bold tabular-nums focus:ring-0" aria-label="Kopya adedi">
                                            <button type="button" class="flex size-10 items-center justify-center rounded-xl text-lg font-bold text-ink hover:bg-surface" @click="stepCopies(f, 1)" aria-label="Kopya artır">+</button>
                                        </div>
                                    </div>
                                    <label class="block">
                                        <span class="text-xs font-bold tracking-wider text-muted uppercase">Sayfa aralığı <span class="font-medium tracking-normal normal-case">(boş: tümü)</span></span>
                                        <input type="text" maxlength="200" placeholder="ör. 1-10, 15, 20-25" x-model="f.opts.range" @input="requote(f)" class="field-input mt-2 rounded-2xl py-3">
                                    </label>
                                </div>

                                @if ($settings['a3_enabled'])
                                    <div role="group" aria-label="Kağıt boyutu">
                                        <p class="text-xs font-bold tracking-wider text-muted uppercase">Kağıt boyutu</p>
                                        <div class="mt-2 grid grid-cols-2 gap-2 sm:max-w-xs">
                                            @foreach (['A4', 'A3'] as $size)
                                                <button type="button" class="rounded-2xl border px-3 py-3 text-sm font-semibold transition" :class="{{ $choice('size', $size) }}" @click="choose(f, 'size', '{{ $size }}')">{{ $size }}</button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <label class="block">
                                    <span class="text-xs font-bold tracking-wider text-muted uppercase">Not <span class="font-medium tracking-normal normal-case">(isteğe bağlı)</span></span>
                                    <input type="text" maxlength="300" placeholder="ör. kapak sayfası renkli olsun" x-model="f.opts.note" @input="requote(f)" class="field-input mt-2 rounded-2xl py-3">
                                </label>
                            </div>

                            {{-- Fiyat şeridi --}}
                            <div x-show="isReady(f)" class="flex flex-wrap items-center justify-between gap-4 bg-navy px-5 py-4 text-white sm:px-6">
                                <div class="min-w-0">
                                    <p class="text-sm text-white/75" x-text="f.summary"></p>
                                    <template x-for="l in f.lines" :key="l[0]"><p class="text-xs text-white/50"><span x-text="l[0]"></span> = <span x-text="l[1]"></span></p></template>
                                </div>
                                <div class="flex items-center gap-4">
                                    <p class="font-display text-3xl font-extrabold tabular-nums" x-text="f.total || '—'"></p>
                                    <button type="button" class="inline-flex items-center gap-2 rounded-2xl px-5 py-3 text-sm font-bold text-white shadow-lg transition disabled:opacity-50"
                                            :class="f.added ? 'bg-green-600' : 'bg-orange-600 hover:-translate-y-0.5 hover:bg-orange-500'"
                                            :disabled="!canAdd(f)" @click="addToCart(f)">
                                        <x-icon name="cart" class="size-4" />
                                        <span x-text="f.added ? 'Sepete eklendi' : 'Sepete ekle'"></span>
                                    </button>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>

                {{-- Fiyatlar ve güvenceler --}}
                <aside class="h-fit space-y-5 lg:sticky lg:top-40">
                    <section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-line">
                        <div class="border-b border-line px-5 py-4">
                            <h2 class="font-display text-lg font-extrabold">Fiyat listesi</h2>
                            <p class="text-xs text-muted">A4, yüz başı, KDV dahil. Çok basana daha ucuz.</p>
                        </div>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-[11px] tracking-wider text-muted uppercase">
                                    <th class="px-5 py-2.5 text-left font-bold">Toplam yüz</th>
                                    <th class="px-2 py-2.5 text-right font-bold">S-B</th>
                                    <th class="px-5 py-2.5 text-right font-bold">Renkli</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($settings['tiers'] as $i => $tier)
                                    @php $next = $settings['tiers'][$i + 1]['from'] ?? null; @endphp
                                    <tr class="border-t border-line">
                                        <td class="px-5 py-2.5 font-semibold tabular-nums">{{ $next ? $tier['from'].' – '.($next - 1) : $tier['from'].' +' }}</td>
                                        <td class="px-2 py-2.5 text-right tabular-nums">{{ Money::format($tier['bw']) }}<span class="block text-[11px] text-muted">ö/a {{ Money::format($tier['bw_duplex']) }}</span></td>
                                        <td class="px-5 py-2.5 text-right tabular-nums">{{ Money::format($tier['color']) }}<span class="block text-[11px] text-muted">ö/a {{ Money::format($tier['color_duplex']) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <dl class="space-y-2 border-t border-line bg-surface px-5 py-4 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-muted">Zımba <span class="text-xs">({{ $settings['staple_max_sheets'] }} yaprağa kadar)</span></dt><dd class="font-semibold tabular-nums">{{ Money::format($settings['staple']) }}</dd></div>
                            @foreach ($settings['spiral'] as $row)
                                <div class="flex justify-between gap-3"><dt class="text-muted">Spiral <span class="text-xs">({{ $row['to'] }} yaprağa kadar)</span></dt><dd class="font-semibold tabular-nums">{{ Money::format($row['price']) }}</dd></div>
                            @endforeach
                        </dl>
                        <p class="border-t border-line px-5 py-3 text-[11px] text-muted">ö/a: önlü arkalı baskıda yüz başı fiyat.@if (bccomp((string) $settings['min_total'], '0', 2) > 0) En düşük baskı tutarı {{ Money::format($settings['min_total']) }}.@endif</p>
                    </section>

                    <section class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-line">
                        <ul class="space-y-3.5 text-sm">
                            @if ($settings['prep_text'])
                                <li class="flex gap-3"><span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-puzzle-orange-soft text-puzzle-orange"><x-icon name="clock" class="size-4" /></span><span class="pt-1.5 font-medium">{{ $settings['prep_text'] }}</span></li>
                            @endif
                            <li class="flex gap-3"><span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-puzzle-blue-soft text-puzzle-blue"><x-icon name="truck" class="size-4" /></span><span class="pt-1.5 font-medium">Ürünlerinle aynı siparişte, tek teslimat</span></li>
                            <li class="flex gap-3"><span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-puzzle-green-soft text-puzzle-green"><x-icon name="lock" class="size-4" /></span><span class="pt-1.5 font-medium">Belgelerin yalnızca baskı için kullanılır, süresi dolunca silinir</span></li>
                            <li class="flex gap-3"><span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-puzzle-yellow-soft text-puzzle-yellow"><x-icon name="refresh" class="size-4" /></span><span class="pt-1.5 font-medium">Baskı hatasında ücretsiz yeniden baskı</span></li>
                        </ul>
                    </section>
                </aside>
            </div>

            {{-- Sık sorulanlar --}}
            <section class="mt-14" aria-labelledby="sss">
                <h2 id="sss" class="text-center font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Sık sorulanlar</h2>
                <div class="mx-auto mt-6 grid max-w-5xl items-start gap-3 md:grid-cols-2">
                    @foreach ($faq as [$q, $a])
                        <details class="group rounded-2xl bg-white p-5 shadow-xs ring-1 ring-line open:ring-puzzle-blue/40">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold text-ink">
                                {{ $q }}
                                <x-icon name="chevron-down" class="size-4 shrink-0 text-muted transition group-open:rotate-180" />
                            </summary>
                            <p class="mt-3 text-sm leading-relaxed text-muted">{{ $a }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-layouts.store>
