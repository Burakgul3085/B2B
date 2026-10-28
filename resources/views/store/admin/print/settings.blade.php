@php
    $money = fn ($v) => $v === null || $v === '' ? '' : number_format((float) $v, 2, ',', '');
    $tiers = array_pad(old('tiers', $s['tiers']), max(5, count($s['tiers']) + 2), ['from' => '', 'bw' => '', 'bw_duplex' => '', 'color' => '', 'color_duplex' => '']);
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

        <x-card title="Baskı fiyatları (yüz başı)" description="Kademe, siparişteki tüm kopyalarda toplam basılan yüz sayısına göre seçilir. Önlü arkalı fiyatı her yüz içindir (bir yaprak = 2 yüz). İlk kademe 1'den başlamalıdır; boş satırlar yok sayılır." :padding="false">
            @error('tiers')<p class="px-5 pt-4 text-sm text-danger">{{ $message }}</p>@enderror
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Başlangıç (yüz)</th><th>S-B tek yüz</th><th>S-B önlü arkalı</th><th>Renkli tek yüz</th><th>Renkli önlü arkalı</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach (array_values($tiers) as $i => $row)
                            <tr>
                                <td><x-field :name="'tiers['.$i.'][from]'" label="Başlangıç" class="[&>label]:sr-only" type="number" min="1" :value="$row['from']" /></td>
                                @foreach (['bw', 'bw_duplex', 'color', 'color_duplex'] as $k)
                                    <td><x-field :name="'tiers['.$i.']['.$k.']'" :label="$k" class="[&>label]:sr-only" inputmode="decimal" :value="$money($row[$k] ?? '')" /></td>
                                @endforeach
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

            <x-card title="A3 baskı" description="Açıkken müşteri kağıt boyutunu seçebilir. A3 yüz fiyatı, yukarıdaki A4 fiyatının çarpanla çarpımıdır.">
                <div class="space-y-5">
                    <x-toggle name="a3_enabled" label="A3 baskı açık" :checked="$s['a3_enabled']" />
                    <x-field name="a3_multiplier" label="A3 fiyat çarpanı" :value="$money($s['a3_multiplier'])" inputmode="decimal" class="max-w-48" hint="Örn. 2,00: A3 yüz fiyatı A4'ün iki katı." />
                </div>
            </x-card>
        </div>

        <x-button>Kaydet</x-button>
    </form>
</x-layouts.panel>
