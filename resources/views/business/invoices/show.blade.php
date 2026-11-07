@php
    use App\Domain\Invoicing\InvoiceMath;
    use App\Domain\Support\Money;
    use App\Models\Invoice;
    $qty = fn ($v) => str_replace('.', ',', rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.'));
    $totals = InvoiceMath::totals($invoice->lines->map(fn ($l) => $l->only(['net_amount', 'vat_amount', 'line_total', 'vat_rate'])));
    $draft = $invoice->isDraft() && ! $invoice->isManual();
    $title = $invoice->invoice_no ?: ($invoice->kind === 'refund' ? 'İade faturası taslağı' : 'Fatura taslağı');
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" :title="$title">
    <x-page-header :title="$title" :subtitle="$invoice->label().' · Sipariş '.$invoice->order->order_no.' · '.$invoice->order->relationship->dealer->legal_name">
        <x-slot:breadcrumb><a href="{{ route('business.invoices.index') }}" class="hover:underline">Faturalar</a></x-slot:breadcrumb>
        <x-badge :tone="$invoice->statusTone()">{{ $invoice->statusLabel() }}</x-badge>
        <x-button variant="secondary" size="sm" :href="route('business.orders.show', $invoice->order)">Siparişe git</x-button>
    </x-page-header>

    @error('invoice')<div role="alert" class="mb-4 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror
    @if ($invoice->error_message && $invoice->status !== 'sent')
        <div class="mb-4 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900"><b>Sorun:</b> {{ $invoice->error_message }}</div>
    @endif
    @if ($draft && $sellerProblem)
        <div class="mb-4 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $sellerProblem }}</div>
    @endif
    @if ($draft && $driver === 'deneme')
        <div class="mb-4 rounded-lg border border-amber-200 bg-warning-soft px-4 py-3 text-sm text-amber-900">Deneme sürücüsü açık: bu taslak gönderilirse geçerli olmayan bir deneme faturası oluşur.</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Kalemler" description="Siparişin {{ $invoice->kind === 'refund' ? 'iade kaydındaki' : 'onaylanan' }} miktar ve tutarlarından gelir; değiştirilemez. Birim fiyatlar KDV hariçtir." :padding="false">
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr><th>Mal / hizmet</th><th class="text-right">Miktar</th><th class="text-right">Birim fiyat</th><th class="text-right">İskonto</th><th class="text-right">KDV</th><th class="text-right">Tutar (KDV hariç)</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($invoice->lines as $line)
                                <tr>
                                    <td>{{ $line->name }}@if ($line->sku)<p class="text-xs text-muted">{{ $line->sku }}</p>@endif</td>
                                    <td class="text-right tabular-nums">{{ $qty($line->quantity) }} {{ $line->unit_code }}</td>
                                    <td class="text-right tabular-nums">{{ Money::formatUnit($line->unit_price) }}</td>
                                    <td class="text-right tabular-nums">{{ bccomp((string) $line->discount, '0', 2) > 0 ? Money::format($line->discount) : '—' }}</td>
                                    <td class="text-right tabular-nums">%{{ InvoiceMath::rate((string) $line->vat_rate) }} · {{ Money::format($line->vat_amount) }}</td>
                                    <td class="text-right font-medium tabular-nums">{{ Money::format($line->net_amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <dl class="ml-auto grid max-w-sm grid-cols-2 gap-x-6 gap-y-1 border-t border-line p-5 text-sm">
                    <dt class="text-muted">KDV matrahı</dt><dd class="text-right tabular-nums">{{ Money::format($totals['subtotal']) }}</dd>
                    @foreach ($totals['by_rate'] as $rate => $sum)
                        <dt class="text-muted">KDV %{{ $rate }}</dt><dd class="text-right tabular-nums">{{ Money::format($sum['vat']) }}</dd>
                    @endforeach
                    <dt class="font-semibold">Fatura toplamı</dt><dd class="text-right font-semibold tabular-nums">{{ Money::format($invoice->total) }}</dd>
                    <dt class="text-muted">{{ $invoice->kind === 'refund' ? 'İade edilen tutar' : 'Sipariş tutarı' }}</dt><dd class="text-right tabular-nums">{{ Money::format($invoice->expected_total) }}</dd>
                </dl>
                @if (bccomp((string) $invoice->total, (string) $invoice->expected_total, 2) !== 0)
                    <p class="border-t border-line px-5 py-3 text-sm text-danger">Fatura toplamı {{ $invoice->kind === 'refund' ? 'iade' : 'sipariş' }} tutarından farklı{{ $invoice->isManual() ? ' (elle kaydedilirken onaylandı)' : '' }}.</p>
                @endif
            </x-card>

            @if ($draft)
                <x-card title="Alıcı ve fatura bilgileri" description="Bayinin kayıtlı bilgilerinden gelir. Yalnızca bu faturada geçerli olmak üzere düzeltebilirsiniz.">
                    <form method="POST" action="{{ route('business.invoices.update', $invoice) }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf @method('PUT')
                        <x-field name="buyer_name" label="Unvan" :value="$invoice->buyer_name" required class="sm:col-span-2" />
                        <x-field name="buyer_tax_number" label="VKN / TCKN" :value="$invoice->buyer_tax_number" inputmode="numeric" required />
                        <x-field name="buyer_tax_office" label="Vergi dairesi" :value="$invoice->buyer_tax_office" />
                        <x-field name="buyer_address" label="Adres" :value="$invoice->buyer_address" required class="sm:col-span-2" />
                        <x-field name="buyer_district" label="İlçe" :value="$invoice->buyer_district" />
                        <x-field name="buyer_city" label="İl" :value="$invoice->buyer_city" required />
                        <x-field name="buyer_email" label="E-posta" type="email" :value="$invoice->buyer_email" />
                        <x-field name="buyer_phone" label="Telefon" :value="$invoice->buyer_phone" />
                        <x-field name="issue_date" label="Fatura tarihi" type="date" :value="$invoice->issue_date->format('Y-m-d')" required hint="En fazla 7 gün geriye verilebilir." />
                        <x-field name="note" label="Açıklama (faturaya yazılır)" :value="$invoice->note" />
                        <x-toggle name="notify_dealer" label="Kesilince bayiye bildirim ve e-posta gönder" :checked="$invoice->notify_dealer" class="sm:col-span-2" />
                        <div class="sm:col-span-2"><x-button variant="secondary">Taslağı kaydet</x-button></div>
                    </form>
                </x-card>
            @else
                <x-card title="Alıcı">
                    <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-muted">Unvan</dt><dd>{{ $invoice->buyer_name }}</dd></div>
                        <div><dt class="text-muted">VKN / TCKN</dt><dd>{{ $invoice->buyer_tax_number }}{{ $invoice->buyer_tax_office ? ' · '.$invoice->buyer_tax_office : '' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-muted">Adres</dt><dd>{{ $invoice->buyer_address }} {{ $invoice->buyer_district }} / {{ $invoice->buyer_city }}</dd></div>
                        @if ($invoice->note)<div class="sm:col-span-2"><dt class="text-muted">Açıklama</dt><dd>{{ $invoice->note }}</dd></div>@endif
                    </dl>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="Belge">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted">Tür</dt><dd>{{ Invoice::KINDS[$invoice->kind] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Belge tipi</dt><dd>{{ Invoice::DOCUMENT_TYPES[$invoice->document_type] ?? 'Gönderimde belirlenir' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Fatura tarihi</dt><dd>{{ $invoice->issue_date->format('d.m.Y') }}</dd></div>
                    @if ($invoice->provider)<div class="flex justify-between gap-3"><dt class="text-muted">Sağlayıcı</dt><dd>{{ Invoice::PROVIDERS[$invoice->provider] ?? $invoice->provider }}</dd></div>@endif
                    @if ($invoice->ettn)<div class="flex justify-between gap-3"><dt class="text-muted">ETTN</dt><dd class="break-all text-right text-xs">{{ $invoice->ettn }}</dd></div>@endif
                    @if ($invoice->provider_status)<div class="flex justify-between gap-3"><dt class="text-muted">Uyumsoft durumu</dt><dd>{{ $invoice->provider_status }}</dd></div>@endif
                    @if ($invoice->sent_at)<div class="flex justify-between gap-3"><dt class="text-muted">Kesildi</dt><dd>{{ $invoice->sent_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</dd></div>@endif
                    @if ($invoice->dealer_notified_at)<div class="flex justify-between gap-3"><dt class="text-muted">Bayiye bildirildi</dt><dd>{{ $invoice->dealer_notified_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</dd></div>@endif
                    @if ($invoice->cancelled_at)<div class="flex justify-between gap-3"><dt class="text-muted">İptal</dt><dd class="text-right">{{ $invoice->cancelled_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}<br><span class="text-xs text-muted">{{ $invoice->cancel_reason }}</span></dd></div>@endif
                </dl>

                <div class="mt-5 space-y-3">
                    @if ($draft)
                        <x-button variant="secondary" :href="route('business.invoices.preview', $invoice)" target="_blank" rel="noopener" icon="eye" class="w-full">Önizleme (PDF)</x-button>
                        <form method="POST" action="{{ route('business.invoices.send', $invoice) }}" data-confirm="Fatura {{ Money::format($invoice->total) }} tutarıyla bayiye kesilsin mi? Kesilen fatura değiştirilemez.">@csrf
                            <x-button class="w-full" icon="check">Faturayı kes ve gönder</x-button>
                        </form>
                        <form method="POST" action="{{ route('business.invoices.destroy', $invoice) }}" data-confirm="Taslak silinsin mi?">@csrf @method('DELETE')
                            <x-button variant="ghost" class="w-full text-danger">Taslağı sil</x-button>
                        </form>
                    @elseif (in_array($invoice->status, Invoice::UNSETTLED, true))
                        <p class="text-sm text-muted">Gönderimin sonucu alınamadı; fatura kesilmiş olabilir. Önce Uyumsoft'tan sorgulayın (sistem de birkaç dakikada bir kendiliğinden sorgular).</p>
                        <form method="POST" action="{{ route('business.invoices.check', $invoice) }}">@csrf<x-button class="w-full" icon="refresh">Uyumsoft'tan sorgula</x-button></form>
                        <form method="POST" action="{{ route('business.invoices.release', $invoice) }}" data-confirm="Uyumsoft portalında bu faturanın kesilmediğini kontrol ettiniz mi? Kesildiyse taslağa almak çift faturaya yol açar.">@csrf
                            <x-button variant="secondary" class="w-full">Kesilmediğini kontrol ettim, taslağa al</x-button>
                        </form>
                    @elseif ($invoice->status === 'sent')
                        @if ($invoice->pdf_path)
                            <x-button :href="route('business.invoices.pdf', $invoice)" target="_blank" rel="noopener" icon="download" class="w-full">Faturayı indir (PDF)</x-button>
                        @elseif (! $invoice->isManual())
                            <form method="POST" action="{{ route('business.invoices.refetch', $invoice) }}">@csrf<x-button variant="secondary" class="w-full" icon="download">PDF'i yeniden al</x-button></form>
                        @endif
                        @if ($invoice->provider === 'uyumsoft')
                            <form method="POST" action="{{ route('business.invoices.status', $invoice) }}">@csrf<x-button variant="secondary" class="w-full" icon="refresh">Uyumsoft durumunu sorgula</x-button></form>
                        @endif
                    @elseif ($invoice->pdf_path)
                        <x-button variant="secondary" :href="route('business.invoices.pdf', $invoice)" target="_blank" rel="noopener" icon="download" class="w-full">Belgeyi indir (PDF)</x-button>
                    @endif
                </div>
            </x-card>

            @if ($invoice->status === 'sent' && ($cancellable || $invoice->isManual()))
                <x-card title="Faturayı iptal et" :description="$invoice->isManual()
                    ? 'Elle kesilen fatura yalnızca bu kayıtta iptal edilir; Uyumsoft portalında ayrıca iptal edin.'
                    : 'e-Arşiv faturası '.$cancelDeadline->format('d.m.Y').' gün sonuna kadar iptal edilebilir. İptal sonrası sipariş yeniden faturalanabilir.'">
                    <form method="POST" action="{{ route('business.invoices.cancel', $invoice) }}" class="space-y-3" data-confirm="Fatura {{ $invoice->invoice_no }} iptal edilsin mi?">
                        @csrf
                        <x-field name="cancel_reason" label="İptal nedeni" required maxlength="300" />
                        <x-button variant="danger" class="w-full">İptal et</x-button>
                    </form>
                </x-card>
            @elseif ($invoice->status === 'sent' && $invoice->document_type === 'e_arsiv' && $cancelDeadline)
                <x-card title="İptal">
                    <p class="text-sm text-muted">İptal süresi {{ $cancelDeadline->format('d.m.Y') }} tarihinde doldu. Düzeltme için iade faturası kesin.</p>
                </x-card>
            @elseif ($invoice->status === 'sent' && $invoice->document_type === 'e_fatura')
                <x-card title="İptal">
                    <p class="text-sm text-muted">e-Fatura buradan iptal edilemez: bayi faturayı reddedebilir, ya da iadeyi bayi size iade faturasıyla yapar.</p>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.panel>
