@php use App\Domain\Support\Money; use App\Models\CollectionEntry; $sign = bccomp((string) $account->balance, '0', 2); $r = $account->relationship; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" :title="'Cari '.$account->code">
    @include('partials.statement-print', ['business' => $currentBusiness, 'account' => $account, 'aging' => $aging,
        'statementArgs' => ['action' => route('business.accounts.show', $account), 'orderRoute' => null]])
    <div class="print:hidden">
    <x-page-header :title="$r->dealer->legal_name" :subtitle="'Cari kodu '.$account->code.' · '.($r->allow_pay_later ? $r->payLaterLabel() : 'Peşin (kart / havale)').($r->allow_pay_later && $r->credit_limit !== null ? ' · limit '.Money::format($r->credit_limit) : '')">
        <x-slot:breadcrumb><a href="{{ route('business.accounts.index') }}" class="hover:underline">Cari Hesaplar</a></x-slot:breadcrumb>
        <x-button variant="secondary" size="sm" :href="route('business.dealers.show', $r)">Bayi ayarları</x-button>
    </x-page-header>

    @error('ledger')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-kpi label="Toplam sipariş" :value="Money::format($sums['debit'])" hint="Cariye yazılan siparişler (iptaller hariç)" />
        <x-kpi label="Toplam ödeme" :value="Money::format($sums['credit'])" hint="Kart, havale, nakit, POS ve iadeler" />
        <x-kpi label="Bakiye" :value="Money::format(ltrim((string) $account->balance, '-'))"
            :hint="$sign > 0 ? 'Bayi size borçlu' : ($sign < 0 ? 'Bayi fazla ödedi (alacaklı)' : 'Hesap kapalı')" />
    </div>

    @if (bccomp($aging['overdue'], '0', 2) > 0)
        <div role="alert" class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">
            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-red-500"></span>
            <p><b>{{ Money::format($aging['overdue']) }}</b> ödemenin son günü geçti. Bayi ödeyene kadar yeni siparişlerde sonra öde seçemez.</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Ödemesi bekleyen siparişler" :padding="false"
                description="Siparişe bağlı ödeme önce o siparişi kapatır; toplu ödeme en eski borçtan başlar.">
                @if ($aging['items']->isEmpty())
                    <x-empty-state icon="check-circle" title="Bekleyen ödeme yok" text="Bu bayinin ödenmemiş siparişi bulunmuyor." />
                @else
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead><tr><th>Sipariş</th><th>Son ödeme</th><th class="text-right">Kalan</th><th class="text-right">Durum</th></tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($aging['items'] as $item)
                                    @include('partials.ledger-open-item', ['item' => $item, 'orderRoute' => 'business.orders.show'])
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            <x-card title="Hesap hareketleri" :padding="false">
                @include('partials.statement', ['action' => route('business.accounts.show', $account), 'orderRoute' => 'business.orders.show'])
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Ödeme ekle" description="Bayinin havale, nakit veya POS ile yaptığı ödemeyi kaydedin. Kart ödemeleri iyzico'dan kendiliğinden gelir.">
                <form method="POST" action="{{ route('business.accounts.collections.store', $account) }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <x-field name="amount" label="Tutar (₺)" type="number" step="0.01" min="0.01" required />
                        <x-field name="collected_on" label="Tarih" type="date" :value="now('Europe/Istanbul')->toDateString()" required />
                    </div>
                    <x-field name="method" label="Yöntem" :options="array_intersect_key(CollectionEntry::METHODS, array_flip(CollectionEntry::SELECTABLE))" required />
                    <x-field name="order_id" label="Sipariş (isteğe bağlı)" :options="['' => 'Seçmeyin: en eski borçtan düşülür'] + $orders->all()" />
                    <x-field name="description" label="Açıklama" placeholder="Ör. dekont no" />
                    <x-button class="w-full" icon="check">Ödemeyi kaydet</x-button>
                </form>
                <p class="mt-3 text-xs text-muted">Borcu tamamen kapanan siparişler kendiliğinden "Ödendi" olur.</p>
            </x-card>

            <details class="group rounded-xl border border-line bg-white shadow-xs" @if ($errors->hasAny(['date', 'note', 'direction'])) open @endif>
                <summary class="flex cursor-pointer items-center justify-between px-5 py-4 text-sm font-semibold">
                    Diğer işlemler
                    <x-icon name="chevron-down" class="size-4 text-muted transition group-open:rotate-180" />
                </summary>
                <div class="space-y-6 border-t border-line px-5 py-5">
                    <p class="text-xs text-muted">Kayıtlar silinmez; yanlış bir kaydı düzeltmek için burayı kullanın.</p>
                    @unless ($hasOpening)
                        <form method="POST" action="{{ route('business.accounts.opening', $account) }}" class="space-y-3" data-confirm="Açılış bakiyesi kaydedilsin mi? Sonradan yalnızca düzeltme hareketiyle değiştirilebilir.">
                            @csrf
                            <p class="text-sm font-medium">Açılış bakiyesi <span class="font-normal text-muted">· sisteme geçmeden önceki bakiye, bir kez girilir</span></p>
                            <div class="grid grid-cols-2 gap-3">
                                <x-field name="amount" label="Tutar (₺)" type="number" step="0.01" required hint="Bayi borçluysa pozitif, alacaklıysa negatif." />
                                <x-field name="date" label="Tarih" type="date" :value="now('Europe/Istanbul')->toDateString()" required />
                            </div>
                            <x-field name="note" label="Açıklama" />
                            <x-button variant="secondary" size="sm" class="w-full">Açılış bakiyesini kaydet</x-button>
                        </form>
                    @endunless
                    <form method="POST" action="{{ route('business.accounts.adjust', $account) }}" class="space-y-3" data-confirm="Düzeltme hareketi kaydedilsin mi?">
                        @csrf
                        <p class="text-sm font-medium">Düzeltme <span class="font-normal text-muted">· iskonto, yuvarlama farkı vb.</span></p>
                        <x-field name="direction" label="Tür" :options="['credit' => 'Alacak (bayinin borcunu azaltır)', 'debit' => 'Borç (bayinin borcunu artırır)']" />
                        <div class="grid grid-cols-2 gap-3">
                            <x-field name="amount" label="Tutar (₺)" type="number" step="0.01" min="0.01" required />
                            <x-field name="date" label="Tarih" type="date" :value="now('Europe/Istanbul')->toDateString()" required />
                        </div>
                        <x-field name="note" label="Açıklama" required />
                        <x-button variant="secondary" size="sm" class="w-full">Düzeltmeyi kaydet</x-button>
                    </form>
                </div>
            </details>
        </div>
    </div>
    </div>
</x-layouts.panel>
