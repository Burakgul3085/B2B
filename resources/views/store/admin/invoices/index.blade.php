@php
    use App\Http\Controllers\Store\Admin\InvoiceController;
    use App\Domain\Support\Money;
    $at = fn ($t) => $t?->timezone('Europe/Istanbul')->format('d.m.Y H:i');
    $orderTabs = in_array($tab, ['bekleyen', 'iade'], true);
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Faturalar">
    <x-page-header title="Faturalar" subtitle="Ödeme alınınca fatura kendiliğinden kesilmez. Siparişi seçip taslak hazırlayın; kalemleri, miktarı ve fiyatı kontrol edip kendiniz gönderin.">
        @if (auth('store_admin')->user()->is_super)<x-button variant="secondary" :href="route('store_admin.invoice-settings.edit')">Fatura ayarları</x-button>@endif
    </x-page-header>

    @if ($driver !== 'uyumsoft')
        <div class="mb-5 flex items-start gap-3 rounded-lg border border-amber-200 bg-warning-soft px-4 py-3 text-sm text-amber-900">
            <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
            <p><span class="font-semibold">Deneme sürücüsü açık.</span> Gönderilen faturalar hiçbir yere gitmez; "DNM" numaralı, üzerinde "DENEME" yazan PDF üretilir. Gerçek fatura için Fatura Ayarları'ndan Uyumsoft bilgilerini girin.</p>
        </div>
    @endif
    @error('invoice')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach (InvoiceController::TABS as $key => $label)
            @php $count = $counts[$key] ?? null; @endphp
            <a href="{{ route('store_admin.invoices.index', ['tab' => $key]) }}" @class([
                'rounded-full px-3.5 py-1.5 font-medium ring-1',
                'bg-primary text-white ring-primary' => $tab === $key,
                'bg-white text-ink ring-line hover:ring-primary' => $tab !== $key,
            ])>{{ $label }}@if ($count) <span @class(['ml-1 tabular-nums', 'opacity-80' => $tab === $key, 'text-danger' => $key === 'sorunlu' && $tab !== $key])>{{ $count }}</span>@endif</a>
        @endforeach
    </div>

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line p-4">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <x-field name="q" label="Ara" :value="$q" placeholder="Sipariş no, müşteri adı, e-posta{{ $orderTabs ? '' : ', fatura no' }}" class="min-w-64 flex-1" />
            <x-button variant="secondary" aria-label="Ara"><x-icon name="search" class="size-4" /></x-button>
        </form>

        @if ($rows->isEmpty())
            <x-empty-state icon="file" :title="match ($tab) {
                'bekleyen' => 'Fatura bekleyen sipariş yok',
                'iade' => 'İade faturası bekleyen sipariş yok',
                'taslak' => 'Taslak yok',
                'sorunlu' => 'Sorunlu fatura yok',
                'gonderilen' => 'Henüz fatura kesilmedi',
                default => 'İptal edilen fatura yok',
            }" :text="$tab === 'bekleyen' ? 'Ödemesi alınan (kapıda ödemede onaylanan) siparişler burada listelenir.' : ''" />
        @elseif ($orderTabs)
            {{-- Siparişler: taslak oluştur --}}
            <form method="POST" action="{{ route('store_admin.invoices.create-many') }}" x-data="pickList">
                @csrf
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr>
                            @if ($tab === 'bekleyen')<th class="w-10"><span class="sr-only">Seç</span></th>@endif
                            <th>Sipariş</th><th>Müşteri</th><th>Ödeme</th><th class="text-right">Tutar</th><th class="text-right"><span class="sr-only">İşlem</span></th>
                        </tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($rows as $order)
                                <tr>
                                    @if ($tab === 'bekleyen')
                                        <td><input type="checkbox" name="orders[]" value="{{ $order->id }}" x-model="picked" class="size-4 rounded border-line text-primary" aria-label="{{ $order->order_no }} seç"></td>
                                    @endif
                                    <td>
                                        <a href="{{ route('store_admin.orders.show', $order) }}" class="font-semibold text-ink hover:text-primary">{{ $order->order_no }}</a>
                                        <p class="text-xs text-muted">{{ $at($order->paid_at ?? $order->created_at) }} · {{ $order->statusLabel() }}</p>
                                    </td>
                                    <td>{{ $order->billing_name }}<p class="text-xs text-muted">{{ $order->billing_type === 'corporate' ? 'Kurumsal · VKN '.$order->billing_tax_number : 'Bireysel' }}</p></td>
                                    <td class="text-sm">{{ \App\Models\Store\StoreOrder::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method }}
                                        @if ($tab === 'iade')<p class="text-xs text-muted">{{ bccomp((string) $order->refunded_total, '0', 2) > 0 ? 'İade: '.Money::format($order->refunded_total) : 'İptal edildi' }} · Fatura {{ $order->invoice?->invoice_no }}</p>@endif
                                    </td>
                                    <td class="text-right tabular-nums">{{ Money::format($order->total) }}</td>
                                    <td class="text-right">
                                        <button type="submit" form="create-{{ $order->id }}" class="text-sm font-medium text-primary hover:underline">{{ $tab === 'iade' ? 'İade faturası hazırla' : 'Fatura hazırla' }}</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($tab === 'bekleyen')
                    <div class="flex flex-wrap items-center gap-3 border-t border-line p-4">
                        <x-button x-bind:disabled="none">Seçilenler için taslak hazırla</x-button>
                        <span class="text-xs text-muted">Taslaklar siparişten dolu gelir; göndermeden önce Taslaklar sekmesinde kontrol edin.</span>
                    </div>
                @endif
            </form>
            @foreach ($rows as $order)
                <form id="create-{{ $order->id }}" method="POST" action="{{ route($tab === 'iade' ? 'store_admin.invoices.create-refund' : 'store_admin.invoices.create-sale', $order) }}" class="hidden">@csrf</form>
            @endforeach
        @else
            {{-- Fatura belgeleri --}}
            <form method="POST" action="{{ route('store_admin.invoices.send-many') }}" x-data="pickList" data-confirm="Seçilen faturalar kesilecek. Devam edilsin mi?">
                @csrf
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr>
                            @if ($tab === 'taslak')<th class="w-10"><span class="sr-only">Seç</span></th>@endif
                            <th>Fatura</th><th>Sipariş / alıcı</th><th>Durum</th><th class="text-right">Fatura tutarı</th><th class="text-right">Ödenen</th>
                        </tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($rows as $invoice)
                                <tr>
                                    @if ($tab === 'taslak')
                                        <td><input type="checkbox" name="invoices[]" value="{{ $invoice->id }}" x-model="picked" class="size-4 rounded border-line text-primary" aria-label="Seç"></td>
                                    @endif
                                    <td>
                                        <a href="{{ route('store_admin.invoices.edit', $invoice) }}" class="font-semibold text-ink hover:text-primary">{{ $invoice->invoice_no ?: \App\Models\Store\StoreEInvoice::KINDS[$invoice->kind] }}</a>
                                        <p class="text-xs text-muted">
                                            @if ($invoice->invoice_no){{ \App\Models\Store\StoreEInvoice::KINDS[$invoice->kind] }} · @endif
                                            @if ($invoice->document_type){{ \App\Models\Store\StoreEInvoice::DOCUMENT_TYPES[$invoice->document_type] }} · @endif
                                            {{ $at($invoice->sent_at ?? $invoice->updated_at) }}
                                        </p>
                                    </td>
                                    <td>{{ $invoice->order->order_no }}<p class="text-xs text-muted">{{ $invoice->buyer_name }}</p></td>
                                    <td>
                                        <x-badge :tone="$invoice->statusTone()">{{ $invoice->statusLabel() }}</x-badge>
                                        @if ($invoice->error_message && $invoice->status !== 'sent')<p class="mt-1 max-w-xs text-xs text-danger">{{ \Illuminate\Support\Str::limit($invoice->error_message, 120) }}</p>@endif
                                    </td>
                                    <td class="text-right tabular-nums">{{ Money::format($invoice->total) }}
                                        @if ($invoice->hasDifference())<p class="text-xs font-medium text-warning">Fark {{ (bccomp($invoice->difference(), '0', 2) > 0 ? '+' : '').Money::format($invoice->difference()) }}</p>@endif
                                    </td>
                                    <td class="text-right tabular-nums text-muted">{{ Money::format($invoice->expected_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($tab === 'taslak')
                    <div class="flex flex-wrap items-center gap-3 border-t border-line p-4">
                        <x-button x-bind:disabled="none">Seçilenleri gönder</x-button>
                        <span class="text-xs text-muted">Tutarı ödenenden farklı olup farkı onaylanmamış taslaklar gönderilmez; tek tek açıp onaylayın.</span>
                    </div>
                @endif
            </form>
        @endif
        @if ($rows->hasPages())<div class="border-t border-line p-4">{{ $rows->links() }}</div>@endif
    </x-card>
</x-layouts.panel>
