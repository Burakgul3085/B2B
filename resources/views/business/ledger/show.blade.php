@php use App\Domain\Support\Money; use App\Models\CollectionEntry; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" :title="'Cari '.$account->code">
    <x-page-header :title="$account->relationship->dealer->legal_name" :subtitle="'Cari kodu '.$account->code.' · vade '.$account->relationship->payment_term_days.' gün'">
        <x-slot:breadcrumb><a href="{{ route('business.accounts.index') }}" class="hover:underline">Cari Hesaplar</a></x-slot:breadcrumb>
        <x-button variant="secondary" size="sm" :href="route('business.dealers.show', $account->relationship)">Bayi</x-button>
    </x-page-header>

    @error('ledger')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi label="Bakiye" :value="Money::format($account->balance)" :hint="bccomp((string) $account->balance, '0', 2) > 0 ? 'Bayi borçlu' : (bccomp((string) $account->balance, '0', 2) < 0 ? 'Bayi alacaklı' : 'Bakiye yok')" />
        <x-kpi label="Vadesi geçmiş" :value="Money::format($aging['overdue'])" />
        <x-kpi label="7 gün içinde vadesi gelen" :value="Money::format($aging['due_soon'])" />
        <x-kpi label="Vadesi gelmemiş" :value="Money::format($aging['not_due'])" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Ekstre" :padding="false">
                @include('partials.statement', ['action' => route('business.accounts.show', $account), 'orderRoute' => 'business.orders.show'])
            </x-card>

            @if ($aging['items']->isNotEmpty())
                <x-card title="Açık borç kalemleri" description="Tahsilatlar en eski vadeli borçtan başlayarak düşülür (FIFO)." :padding="false">
                    <table class="table-base">
                        <thead><tr><th>İşlem</th><th>Vade</th><th class="text-right">Kalan</th><th>Durum</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($aging['items'] as $item)
                                <tr>
                                    <td class="text-sm">{{ $item['movement']->description }}</td>
                                    <td class="tabular-nums">{{ \Illuminate\Support\Carbon::parse($item['due'])->format('d.m.Y') }}</td>
                                    <td class="text-right font-medium tabular-nums">{{ Money::format($item['open']) }}</td>
                                    <td>
                                        @if ($item['bucket'] === 'overdue')<x-badge tone="danger">{{ $item['days_overdue'] }} gün gecikmiş</x-badge>
                                        @elseif ($item['bucket'] === 'due_soon')<x-badge tone="warning">Vadesi yaklaşıyor</x-badge>
                                        @else<x-badge>Vadesi gelmedi</x-badge>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="Tahsilat ekle" description="Bayiden aldığınız ödemeyi kaydedin. Sistem ödeme almaz ve bankayı kontrol etmez.">
                <form method="POST" action="{{ route('business.accounts.collections.store', $account) }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <x-field name="amount" label="Tutar (₺)" type="number" step="0.01" min="0.01" required />
                        <x-field name="collected_on" label="Tarih" type="date" :value="now('Europe/Istanbul')->toDateString()" required />
                    </div>
                    <x-field name="method" label="Yöntem" :options="CollectionEntry::METHODS" required />
                    <x-field name="order_id" label="İlgili sipariş (isteğe bağlı)" :options="['' => 'Seçilmedi'] + $orders->all()" />
                    <x-field name="description" label="Açıklama" placeholder="Ör. dekont no" />
                    <x-button class="w-full" icon="check">Tahsilatı kaydet</x-button>
                </form>
            </x-card>

            @unless ($hasOpening)
                <x-card title="Açılış bakiyesi" description="Sisteme geçmeden önceki bakiye. Bir kez girilir.">
                    <form method="POST" action="{{ route('business.accounts.opening', $account) }}" class="space-y-4" data-confirm="Açılış bakiyesi kaydedilsin mi? Sonradan yalnızca düzeltme hareketiyle değiştirilebilir.">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <x-field name="amount" label="Tutar (₺)" type="number" step="0.01" required hint="Bayi borçluysa pozitif, alacaklıysa negatif." />
                            <x-field name="date" label="Tarih" type="date" :value="now('Europe/Istanbul')->toDateString()" required />
                        </div>
                        <x-field name="note" label="Açıklama" />
                        <x-button variant="secondary" class="w-full">Açılış bakiyesini kaydet</x-button>
                    </form>
                </x-card>
            @endunless

            <x-card title="Düzeltme hareketi" description="İskonto, yuvarlama farkı vb. için. Açıklama zorunludur ve kayıt altına alınır.">
                <form method="POST" action="{{ route('business.accounts.adjust', $account) }}" class="space-y-4" data-confirm="Düzeltme hareketi kaydedilsin mi?">
                    @csrf
                    <x-field name="direction" label="Tür" :options="['credit' => 'Alacak (bayinin borcunu azaltır)', 'debit' => 'Borç (bayinin borcunu artırır)']" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-field name="amount" label="Tutar (₺)" type="number" step="0.01" min="0.01" required />
                        <x-field name="date" label="Tarih" type="date" :value="now('Europe/Istanbul')->toDateString()" required />
                    </div>
                    <x-field name="note" label="Açıklama" required />
                    <x-button variant="secondary" class="w-full">Kaydet</x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.panel>
