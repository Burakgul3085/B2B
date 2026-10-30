@php
    $money = fn ($v) => $v === null || $v === '' ? '' : number_format((float) $v, 2, ',', '');
    $tiers = array_pad(old('tiers', $s['tiers']), max(5, count($s['tiers']) + 2), ['from' => '', 'bw' => '', 'color' => '']);
    $t = $trial['input'] + ['pages' => '', 'color' => 'bw', 'duplex' => '0', 'layout' => 1, 'size' => 'A4', 'copies' => 1, 'binding' => 'none'];
    $spiral = array_pad(old('spiral', $s['spiral']), max(6, count($s['spiral']) + 2), ['to' => '', 'price' => '']);
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Fotokopi">
    <x-page-header title="Fotokopi" subtitle="Müşterilerin belge yükleyip baskı siparişi verdiği sayfanın fiyatları ve sınırları. Tutarlar KDV dahildir; fiyat her zaman sunucuda bu ayarlarla hesaplanır.">
        @if ($s['enabled'])<x-button variant="secondary" :href="route('store.print')" target="_blank">Sayfayı aç</x-button>@endif
    </x-page-header>

    <form method="POST" action="{{ route('store_admin.print.update') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="grid gap-6 xl:grid-cols-2">
            <x-card title="Yayın" description="Açıkken üst menüde Fotokopi bağlantısı görünür ve sipariş alınır. Kapatırsanız sepetteki baskılar siparişe dönüşemez.">
                <div class="space-y-5">
                    <x-toggle name="enabled" label="Fotokopi siparişi açık" :checked="$s['enabled']" />
                    <x-field name="prep_text" label="Hazırlık bilgisi" :value="$s['prep_text']" maxlength="200" hint="Fotokopi sayfasında gösterilir. Örn. Baskılar 1 iş günü içinde hazırlanır." />
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field name="min_total" label="En düşük baskı tutarı (₺)" :value="$money($s['min_total'])" inputmode="decimal" hint="Sepetteki baskıların toplamı bunun altındaysa sipariş verilemez. 0: sınır yok." />
                        <x-field name="vat_rate" label="KDV oranı" :options="['20' => '%20', '10' => '%10', '1' => '%1', '0' => '%0']" :value="$s['vat_rate']" />
                    </div>
                </div>
            </x-card>

            <x-card title="Sınırlar ve saklama" description="Belgeler yalnızca sizin ve yükleyen müşterinin erişebildiği alanda tutulur; süresi dolunca kendiliğinden silinir.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="max_mb" label="Dosya başına en büyük boyut (MB)" type="number" min="1" max="500" :value="$s['max_mb']" required />
                    <x-field name="max_copies" label="En fazla kopya" type="number" min="1" max="1000" :value="$s['max_copies']" required />
                    <x-field name="unordered_days" label="Siparişe dönüşmeyen belge (gün)" type="number" min="1" max="90" :value="$s['unordered_days']" required />
                    <x-field name="ordered_days" label="Siparişteki belge (gün)" type="number" min="1" max="365" :value="$s['ordered_days']" required hint="Sipariş verildikten sonra bu süre kadar indirilebilir." />
                </div>
            </x-card>
        </div>

        <x-card title="Baskı fiyatları (yaprak başı)" description="Ücret yaprak başınadır. Kademe, siparişteki tüm kopyalardaki toplam yaprak sayısına göre seçilir. Önlü arkalı ve bir yüze çok sayfa baskı ek ücret almaz, yalnızca yaprak sayısını azaltır. Kademe eşiğinin altındaki bir iş, üst kademenin başlangıç tutarından pahalıya gelmez. İlk kademe 1'den başlamalıdır; boş satırlar yok sayılır." :padding="false">
            @error('tiers')<p class="px-5 pt-4 text-sm text-danger">{{ $message }}</p>@enderror
            @foreach ($warnings as $warning)
                <p class="mx-5 mt-4 flex items-start gap-2 rounded-lg bg-warning-soft px-3 py-2 text-sm text-warning"><x-icon name="alert" class="mt-0.5 size-4 shrink-0" /> {{ $warning }}</p>
            @endforeach
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Başlangıç (toplam yaprak)</th><th>Siyah-beyaz yaprak (₺)</th><th>Renkli yaprak (₺)</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach (array_values($tiers) as $i => $row)
                            <tr>
                                <td><x-field :name="'tiers['.$i.'][from]'" label="Başlangıç" class="[&>label]:sr-only" type="number" min="1" :value="$row['from']" /></td>
                                <td><x-field :name="'tiers['.$i.'][bw]'" label="Siyah-beyaz yaprak" class="[&>label]:sr-only" inputmode="decimal" :value="$money($row['bw'] ?? '')" /></td>
                                <td><x-field :name="'tiers['.$i.'][color]'" label="Renkli yaprak" class="[&>label]:sr-only" inputmode="decimal" :value="$money($row['color'] ?? '')" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-card title="Zımba ve spiral cilt" description="Ciltleme fiyatı kopya başınadır. Spiral, kopya başı yaprak sayısına göre ilk uyan satırdan fiyatlanır; en büyük sınırı aşan belgede spiral seçilemez.">
                <div class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field name="staple" label="Zımba (₺)" :value="$money($s['staple'])" inputmode="decimal" required />
                        <x-field name="staple_max_sheets" label="Zımba en fazla yaprak" type="number" min="1" max="1000" :value="$s['staple_max_sheets']" required />
                    </div>
                    <table class="table-base">
                        <thead><tr><th>Yaprak sayısı (en fazla)</th><th>Spiral fiyatı (₺)</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach (array_values($spiral) as $i => $row)
                                <tr>
                                    <td><x-field :name="'spiral['.$i.'][to]'" label="Yaprak" class="[&>label]:sr-only" type="number" min="1" :value="$row['to']" /></td>
                                    <td><x-field :name="'spiral['.$i.'][price]'" label="Fiyat" class="[&>label]:sr-only" inputmode="decimal" :value="$money($row['price'])" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card title="A3 baskı" description="Açıkken müşteri kağıt boyutunu seçebilir. A3 yaprak fiyatı, yukarıdaki A4 yaprak fiyatının çarpanla çarpımıdır.">
                <div class="space-y-5">
                    <x-toggle name="a3_enabled" label="A3 baskı açık" :checked="$s['a3_enabled']" />
                    <x-field name="a3_multiplier" label="A3 fiyat çarpanı" :value="$money($s['a3_multiplier'])" inputmode="decimal" class="max-w-48" hint="Örn. 2,00: A3 yaprak fiyatı A4'ün iki katı." />
                </div>
            </x-card>
        </div>

        <x-button>Kaydet</x-button>
    </form>

    <x-card id="deneme" title="Deneme hesabı" description="Kayıtlı fiyatlarla bir baskının tutarını görün. Fiyatı değiştirdiyseniz önce kaydedin." class="mt-6">
        <form method="GET" action="{{ route('store_admin.print.edit') }}#deneme" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-field name="deneme[pages]" label="Sayfa sayısı" type="number" min="1" :value="$t['pages']" required />
            <x-field name="deneme[color]" label="Renk" :options="\App\Domain\Store\Print\PrintOptions::COLORS" :value="$t['color']" />
            <x-field name="deneme[duplex]" label="Baskı yüzü" :options="['0' => 'Tek yüz', '1' => 'Önlü arkalı']" :value="$t['duplex']" />
            <x-field name="deneme[layout]" label="Bir yüze" :options="\App\Domain\Store\Print\PrintOptions::LAYOUTS" :value="$t['layout']" />
            <x-field name="deneme[copies]" label="Kopya" type="number" min="1" :value="$t['copies']" />
            <x-field name="deneme[binding]" label="Ciltleme" :options="\App\Domain\Store\Print\PrintOptions::BINDINGS" :value="$t['binding']" />
            @if ($s['a3_enabled'])<x-field name="deneme[size]" label="Kağıt" :options="\App\Domain\Store\Print\PrintOptions::SIZES" :value="$t['size']" />@endif
            <div class="flex items-end"><x-button variant="secondary">Hesapla</x-button></div>
        </form>
        @if ($trial['error'])
            <p class="mt-4 rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">{{ $trial['error'] }}</p>
        @elseif ($q = $trial['quote'])
            <div class="mt-4 rounded-lg bg-surface p-4 text-sm">
                <p class="text-muted">{{ $q['summary'] }}</p>
                <p class="mt-1">Kopya başı {{ $q['sheets'] }} yaprak · toplam <strong>{{ $q['all_sheets'] }} yaprak</strong></p>
                @foreach ($q['lines'] as [$label, $amount])<p class="mt-1">{{ $label }} = {{ $amount }}</p>@endforeach
                <p class="mt-2 text-base font-semibold">Toplam: {{ \App\Domain\Support\Money::format($q['total']) }} <span class="text-xs font-normal text-muted">KDV dahil</span></p>
            </div>
        @endif
    </x-card>
</x-layouts.panel>
