@php
    use App\Domain\Invoicing\InvoiceService;
    use App\Domain\Support\Money;
    use App\Models\Invoice;
    $orderInvoices = $order->invoices()->get();
    $openSale = $orderInvoices->first(fn ($i) => $i->kind === 'sale' && ! in_array($i->status, Invoice::CLOSED, true));
    $saleSent = $orderInvoices->first(fn ($i) => $i->kind === 'sale' && $i->status === 'sent');
    $canDraft = ! $openSale && InvoiceService::billable($order);
    $canManual = in_array($order->status, InvoiceService::BILLABLE, true) || $order->status === 'cancelled';
    $manualErrors = $errors->hasAny(['invoice_no', 'issue_date', 'document_type', 'total', 'pdf']);
@endphp
@if ($orderInvoices->isNotEmpty() || $canDraft || $canManual)
    <x-card title="Fatura">
        @error('invoice')<p role="alert" class="mb-3 rounded-lg bg-danger-soft px-3 py-2 text-sm text-red-900">{{ $message }}</p>@enderror
        @if ($orderInvoices->isNotEmpty())
            <ul class="mb-4 divide-y divide-line text-sm">
                @foreach ($orderInvoices as $inv)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <span><a href="{{ route('business.invoices.show', $inv) }}" class="font-medium text-primary hover:underline">{{ $inv->invoice_no ?: 'Taslak' }}</a>
                            <span class="block text-xs text-muted">{{ $inv->label() }}{{ $inv->isManual() ? ' · elle' : '' }} · {{ Money::format($inv->total) }}</span></span>
                        <x-badge :tone="$inv->statusTone()">{{ $inv->statusLabel() }}</x-badge>
                    </li>
                @endforeach
            </ul>
        @elseif (! $canDraft)
            <p class="mb-3 text-sm text-muted">Fatura, kart / havale siparişinde ödeme alınınca, sonra öde siparişinde mal yola çıkınca kesilir.</p>
        @endif

        @if ($canDraft)
            <form method="POST" action="{{ route('business.orders.invoices.sale', $order) }}" class="mb-3">@csrf
                <x-button size="sm" icon="printer">Fatura taslağı hazırla</x-button>
            </form>
        @endif

        @if ($canManual)
            <details class="text-sm" @if ($manualErrors) open @endif>
                <summary class="cursor-pointer text-primary">Uyumsoft'ta elle kesilen faturayı ekle</summary>
                <p class="mt-2 text-xs text-muted">Faturayı Uyumsoft portalında kendiniz kestiyseniz numarasını kaydedin; böylece bu siparişe buradan ikinci fatura kesilmez. e-Fatura mükellefi bayinin size kestiği iade faturası da buradan eklenir.</p>
                <form method="POST" action="{{ route('business.orders.invoices.manual', $order) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                    @csrf
                    <x-field name="kind" label="Belge" :options="['sale' => 'Satış faturası'] + ($saleSent ? ['refund' => 'İade faturası'] : [])" :value="old('kind', $saleSent ? 'refund' : 'sale')" />
                    @if ($saleSent && $returns->isNotEmpty())
                        <x-field name="order_return_id" label="İade kaydı (iade faturasında)" :options="['' => '—'] + $returns->mapWithKeys(fn ($r) => [$r->id => $r->created_at->timezone('Europe/Istanbul')->format('d.m.Y').' · '.Money::format($r->grand_total)])->all()" />
                    @endif
                    <x-field name="invoice_no" label="Fatura numarası" placeholder="BYA2026000000001" maxlength="16" required />
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field name="issue_date" label="Fatura tarihi" type="date" :value="now('Europe/Istanbul')->format('Y-m-d')" required />
                        <x-field name="document_type" label="Belge tipi" :options="Invoice::DOCUMENT_TYPES" />
                    </div>
                    <x-field name="total" label="Fatura toplamı (₺)" inputmode="decimal" required />
                    <label class="flex items-center gap-2"><input type="checkbox" name="confirm_difference" value="1" class="size-4 rounded text-primary"> Toplam sipariş / iade tutarından farklıysa onaylıyorum</label>
                    <div>
                        <label for="manual_pdf" class="mb-1.5 block text-sm font-medium text-ink">Fatura PDF'i (isteğe bağlı, en fazla 5 MB)</label>
                        <input id="manual_pdf" type="file" name="pdf" accept="application/pdf" class="block w-full text-sm">
                        @error('pdf')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                    <x-button size="sm" variant="secondary">Kaydet</x-button>
                </form>
            </details>
        @endif
    </x-card>
@endif
