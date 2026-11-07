@php use App\Domain\Support\Money; use App\Http\Controllers\Business\InvoiceController; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Faturalar">
    <x-page-header title="Faturalar" subtitle="Bayi siparişlerinin satış ve iade faturaları (e-Arşiv / e-Fatura). Tutarlar siparişten birebir gelir; fatura, bayinin carisine yazılan ve ödediği tutarla aynıdır.">
        <x-button variant="secondary" :href="route('business.invoice-settings.edit')" icon="settings">Fatura Ayarları</x-button>
    </x-page-header>

    @if ($driver === 'deneme')
        <div class="mb-4 rounded-lg border border-amber-200 bg-warning-soft px-4 py-3 text-sm text-amber-900">
            <b>Deneme sürücüsü açık:</b> faturalar hiçbir yere gönderilmez, numaraları "DNM" ile başlar ve geçerli değildir. Gerçek fatura için Fatura Ayarları'ndan Uyumsoft'u seçin.
        </div>
    @endif
    @if ($sellerProblem)
        <div class="mb-4 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $sellerProblem }}</div>
    @endif
    @error('invoice')<div role="alert" class="mb-4 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror
    @error('orders')<div role="alert" class="mb-4 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror
    @error('invoices')<div role="alert" class="mb-4 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Fatura durumu">
        @foreach (InvoiceController::TABS as $key => $label)
            <a href="{{ route('business.invoices.index', ['tab' => $key]) }}" @class([
                'rounded-full px-3 py-1.5 text-sm font-medium',
                'bg-primary text-white' => $tab === $key,
                'bg-white text-muted ring-1 ring-line hover:text-ink' => $tab !== $key,
            ])>{{ $label }} <span class="tabular-nums opacity-75">{{ $counts[$key] ?? 0 }}</span></a>
        @endforeach
    </nav>

    <x-card :padding="false">
        @if ($tab !== 'iade')
            <form method="GET" class="flex gap-2 border-b border-line p-4 sm:max-w-md">
                <input type="hidden" name="tab" value="{{ $tab }}">
                {{-- Düz alan: arama değeri yalnızca sorgu dizgesinden gelir (önceki formun "old" girdisi kullanılmaz). --}}
                <div class="flex-1">
                    <label for="fatura_ara" class="mb-1.5 block text-sm font-medium text-ink">{{ $tab === 'bekleyen' ? 'Sipariş no' : 'Sipariş veya fatura no' }}</label>
                    <input id="fatura_ara" name="q" type="search" value="{{ $q }}" maxlength="40" class="field-input">
                </div>
                <x-button variant="secondary" class="self-end" aria-label="Ara"><x-icon name="search" class="size-4" /></x-button>
            </form>
        @endif

        @if ($tab === 'bekleyen')
            <p class="border-b border-line px-4 py-3 text-sm text-muted">
                Kart / havale siparişleri ödemesi alınınca, sonra öde siparişleri mal yola çıkınca burada görünür.
                {{ $autoIssue ? 'Otomatik kesim açık: yeni siparişler bir dakika içinde kendiliğinden faturalanır; açılmadan önceki siparişleri buradan kesin.' : 'Otomatik kesim kapalı: taslak hazırlayıp kontrol ederek gönderin.' }}
            </p>
            @if ($orders->isEmpty())
                <x-empty-state icon="check-circle" title="Fatura bekleyen sipariş yok" />
            @else
                <form id="toplu-taslak" method="POST" action="{{ route('business.invoices.create-many') }}">@csrf</form>
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr><th class="w-8"><span class="sr-only">Seç</span></th><th>Sipariş</th><th>Bayi</th><th class="hidden sm:table-cell">Ödeme</th><th class="text-right">Tutar</th><th></th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($orders as $order)
                                <tr>
                                    <td><input type="checkbox" name="orders[]" value="{{ $order->id }}" form="toplu-taslak" class="size-4 rounded text-primary" aria-label="{{ $order->order_no }} seç"></td>
                                    <td><a href="{{ route('business.orders.show', $order) }}" class="font-medium text-primary hover:underline">{{ $order->order_no }}</a>
                                        <p class="text-xs text-muted">{{ $order->statusLabel() }}</p></td>
                                    <td>{{ $order->relationship->dealer->legal_name }}</td>
                                    <td class="hidden text-sm text-muted sm:table-cell">{{ $order->paymentMethodLabel() }}
                                        @if (($at = \App\Domain\Invoicing\InvoiceService::triggeredAt($order)))<span class="block text-xs">{{ $at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</span>@endif</td>
                                    <td class="text-right font-medium tabular-nums">{{ Money::format($order->grand_total) }}</td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('business.orders.invoices.sale', $order) }}">@csrf<x-button size="sm" variant="secondary">Taslak hazırla</x-button></form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line p-4">
                    <x-button form="toplu-taslak" size="sm">Seçilenlere taslak hazırla</x-button>
                    @if ($orders->hasPages()){{ $orders->links() }}@endif
                </div>
            @endif

        @elseif ($tab === 'iade')
            <p class="border-b border-line px-4 py-3 text-sm text-muted">
                Satış faturası kesilmiş siparişlerin iadeleri ve ödemesi alınıp iptal edilen siparişler. Sipariş tamamen iade / iptal edildiyse ve satış faturası 7 gün içindeyse iade faturası yerine satış faturasını iptal edin.
                e-Fatura mükellefi bayide iade faturasını bayi keser; burada görünmez, gelen faturayı sipariş sayfasından elle ekleyin.
            </p>
            @if ($refunds->isEmpty())
                <x-empty-state icon="check-circle" title="İade faturası bekleyen kayıt yok" />
            @else
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr><th>Sipariş</th><th>Bayi</th><th>Satış faturası</th><th class="text-right">İade tutarı</th><th></th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($refunds as $row)
                                <tr>
                                    <td><a href="{{ route('business.orders.show', $row['order']) }}" class="font-medium text-primary hover:underline">{{ $row['order']->order_no }}</a>
                                        <p class="text-xs text-muted">{{ $row['return'] ? 'İade kaydı · '.$row['return']->created_at->timezone('Europe/Istanbul')->format('d.m.Y') : 'Sipariş iptal edildi' }}</p></td>
                                    <td>{{ $row['order']->relationship->dealer->legal_name }}</td>
                                    <td><a href="{{ route('business.invoices.show', $row['sale']) }}" class="text-primary hover:underline">{{ $row['sale']->invoice_no }}</a></td>
                                    <td class="text-right tabular-nums">{{ Money::format($row['return'] ? $row['return']->grand_total : $row['sale']->total) }}</td>
                                    <td class="text-right">
                                        @if ($row['cancel_instead'])
                                            <x-button size="sm" variant="secondary" :href="route('business.invoices.show', $row['sale'])">Satış faturasını iptal et</x-button>
                                        @else
                                            <form method="POST" action="{{ route('business.orders.invoices.refund', $row['order']) }}">@csrf
                                                @if ($row['return'])<input type="hidden" name="order_return_id" value="{{ $row['return']->id }}">@endif
                                                <x-button size="sm" variant="secondary">İade faturası hazırla</x-button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        @else
            @if ($invoices->isEmpty())
                <x-empty-state icon="file" title="Bu bölümde fatura yok" />
            @else
                @if ($tab === 'taslak')<form id="toplu-gonder" method="POST" action="{{ route('business.invoices.send-many') }}" data-confirm="Seçilen taslaklar bayilere fatura olarak kesilsin mi? Kesilen fatura değiştirilemez.">@csrf</form>@endif
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr>
                            @if ($tab === 'taslak')<th class="w-8"><span class="sr-only">Seç</span></th>@endif
                            <th>Belge</th><th>Sipariş / bayi</th><th class="hidden sm:table-cell">Tarih</th><th class="text-right">Tutar</th><th>Durum</th>
                        </tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($invoices as $invoice)
                                <tr>
                                    @if ($tab === 'taslak')<td><input type="checkbox" name="invoices[]" value="{{ $invoice->id }}" form="toplu-gonder" class="size-4 rounded text-primary" aria-label="Seç"></td>@endif
                                    <td><a href="{{ route('business.invoices.show', $invoice) }}" class="font-medium text-primary hover:underline">{{ $invoice->invoice_no ?: 'Taslak' }}</a>
                                        <p class="text-xs text-muted">{{ $invoice->label() }}{{ $invoice->isManual() ? ' · elle' : '' }}</p></td>
                                    <td>{{ $invoice->order->order_no }}<p class="text-xs text-muted">{{ $invoice->order->relationship->dealer->legal_name }}</p></td>
                                    <td class="hidden tabular-nums text-muted sm:table-cell">{{ $invoice->issue_date->format('d.m.Y') }}</td>
                                    <td class="text-right font-medium tabular-nums">{{ Money::format($invoice->total) }}</td>
                                    <td><x-badge :tone="$invoice->statusTone()">{{ $invoice->statusLabel() }}</x-badge>
                                        @if ($invoice->error_message && $tab !== 'kesilen')<p class="mt-1 max-w-xs text-xs text-danger">{{ \Illuminate\Support\Str::limit($invoice->error_message, 140) }}</p>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line p-4">
                    @if ($tab === 'taslak')<x-button form="toplu-gonder" size="sm">Seçilenleri gönder</x-button>@else<span></span>@endif
                    @if ($invoices->hasPages()){{ $invoices->links() }}@endif
                </div>
            @endif
        @endif
    </x-card>
</x-layouts.panel>
