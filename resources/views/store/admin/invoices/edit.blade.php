@php
    use App\Domain\Support\Money;
    use App\Models\Store\StoreEInvoice;
    use App\Models\Store\StoreOrder;
    $draft = $invoice->isDraft();
    $comma = fn ($v, $scale = 2) => $v === null || $v === '' ? '' : str_replace('.', ',', rtrim(rtrim(number_format((float) $v, $scale, '.', ''), '0'), '.') ?: '0');
    $money = fn ($v) => $v === null || $v === '' ? '' : number_format((float) $v, 2, ',', '');
    $rows = old('lines') !== null
        ? array_values(array_map(fn ($l) => array_merge(['order_item_id' => '', 'name' => '', 'sku' => '', 'quantity' => '', 'unit_price' => '', 'discount' => '', 'vat_rate' => '20'], (array) $l), (array) old('lines')))
        : $invoice->lines->map(fn ($l) => [
            'order_item_id' => (string) ($l->order_item_id ?? ''), 'name' => $l->name, 'sku' => (string) $l->sku,
            'quantity' => $comma($l->quantity, 3), 'unit_price' => $money($l->unit_price),
            'discount' => bccomp((string) $l->discount, '0', 2) > 0 ? $money($l->discount) : '', 'vat_rate' => $comma($l->vat_rate),
        ])->all();
    $lineErrors = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'lines.'))->map(fn ($m) => $m[0])->all();
    $title = $invoice->invoice_no ?: StoreEInvoice::KINDS[$invoice->kind].($draft ? ' taslağı' : '');
    $at = fn ($t) => $t?->timezone('Europe/Istanbul')->format('d.m.Y H:i');
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" :title="$title">
    <x-page-header :title="$title" :subtitle="'Sipariş '.$order->order_no.' · '.$order->billing_name.' · '.StoreEInvoice::KINDS[$invoice->kind].($invoice->return ? ' (iade '.$invoice->return->return_no.')' : '')">
        <x-button variant="secondary" :href="route('store_admin.orders.show', $order)">Siparişe dön</x-button>
        @if ($invoice->status === 'sent' && $invoice->pdf_path)
            <x-button :href="route('store_admin.invoices.pdf', $invoice)" icon="download">PDF</x-button>
        @else
            <x-button variant="secondary" :href="route('store_admin.invoices.preview', $invoice)" target="_blank">Önizleme (PDF)</x-button>
        @endif
    </x-page-header>

    @error('invoice')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror
    @if ($driver !== 'uyumsoft' && $draft)
        <div class="mb-5 rounded-lg border border-amber-200 bg-warning-soft px-4 py-3 text-sm text-amber-900"><span class="font-semibold">Deneme sürücüsü:</span> gönderilen fatura hiçbir yere gitmez, geçerli değildir.</div>
    @endif
    @if ($draft && $invoice->error_message)
        <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900"><span class="font-semibold">Son gönderim:</span> {{ $invoice->error_message }}</div>
    @endif

    @if (in_array($invoice->status, ['unknown', 'sending'], true))
        <x-card title="Gönderimin sonucu belirsiz" class="mb-6">
            <p class="text-sm text-muted">{{ $invoice->error_message ?: 'Sağlayıcıdan yanıt alınamadı.' }} Fatura kesilmiş olabilir; çift fatura olmaması için yeniden göndermeden önce sağlayıcıdan sorgulayın.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('store_admin.invoices.check', $invoice) }}">@csrf<x-button icon="refresh">Sağlayıcıdan sorgula</x-button></form>
                <form method="POST" action="{{ route('store_admin.invoices.release', $invoice) }}" data-confirm="Uyumsoft panelinde bu faturanın kesilmediğini kontrol ettiniz mi? Fatura taslağa alınır ve yeniden gönderilebilir.">@csrf<x-button variant="secondary">Kesilmediğini kontrol ettim, taslağa al</x-button></form>
            </div>
        </x-card>
    @endif

    @if ($invoice->status === 'sent' || $invoice->status === 'cancelled')
        <div class="grid gap-6 xl:grid-cols-3">
            <x-card title="Fatura bilgileri" class="xl:col-span-1">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted">Durum</dt><dd><x-badge :tone="$invoice->statusTone()">{{ $invoice->statusLabel() }}</x-badge></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Fatura no</dt><dd class="font-semibold">{{ $invoice->invoice_no }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Tür</dt><dd>{{ StoreEInvoice::DOCUMENT_TYPES[$invoice->document_type] ?? '—' }} · {{ StoreEInvoice::PROVIDERS[$invoice->provider] ?? $invoice->provider }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Fatura tarihi</dt><dd>{{ $invoice->issue_date->format('d.m.Y') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Gönderildi</dt><dd>{{ $at($invoice->sent_at) }}</dd></div>
                    <div><dt class="text-muted">ETTN</dt><dd class="font-mono text-xs break-all">{{ $invoice->ettn }}</dd></div>
                    @if ($invoice->status === 'cancelled')
                        <div class="flex justify-between gap-3"><dt class="text-muted">İptal</dt><dd>{{ $at($invoice->cancelled_at) }}</dd></div>
                        <div><dt class="text-muted">İptal nedeni</dt><dd>{{ $invoice->cancel_reason }}</dd></div>
                    @endif
                </dl>
                @if ($invoice->status === 'sent' && ! $invoice->pdf_path)
                    <form method="POST" action="{{ route('store_admin.invoices.refetch-pdf', $invoice) }}" class="mt-4">@csrf<x-button variant="secondary" icon="refresh">PDF'i sağlayıcıdan al</x-button></form>
                @endif
                @if ($invoice->status === 'sent' && $invoice->kind === 'sale' && \App\Domain\Store\Invoicing\EInvoiceService::awaitingRefund()->whereKey($order->id)->exists())
                    <form method="POST" action="{{ route('store_admin.invoices.create-refund', $order) }}" class="mt-3">@csrf<x-button variant="secondary">İade faturası hazırla</x-button></form>
                @endif
            </x-card>

            <x-card title="Alıcı" class="xl:col-span-2">
                <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-muted">{{ $invoice->buyer_type === 'corporate' ? 'Unvan' : 'Ad soyad' }}</dt><dd class="font-medium">{{ $invoice->buyer_name }}</dd></div>
                    <div><dt class="text-muted">{{ $invoice->buyer_type === 'corporate' ? 'VKN / vergi dairesi' : 'TCKN' }}</dt><dd>{{ $invoice->buyer_tax_number ?: '11111111111' }}{{ $invoice->buyer_tax_office ? ' · '.$invoice->buyer_tax_office : '' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-muted">Adres</dt><dd>{{ $invoice->buyer_address }}@unless (mb_stripos($invoice->buyer_address, $invoice->buyer_city) !== false) {{ trim(($invoice->buyer_district ? $invoice->buyer_district.' / ' : '').$invoice->buyer_city) }}@endunless</dd></div>
                    <div><dt class="text-muted">E-posta</dt><dd>{{ $invoice->buyer_email ?: '—' }}</dd></div>
                    <div><dt class="text-muted">Açıklama</dt><dd>{{ $invoice->note ?: '—' }}</dd></div>
                </dl>
            </x-card>
        </div>

        <x-card title="Kalemler" :padding="false" class="mt-6">
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Kalem</th><th class="text-right">Miktar</th><th class="text-right">Birim fiyat (KDV dahil)</th><th class="text-right">İndirim</th><th class="text-right">KDV</th><th class="text-right">Tutar</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($invoice->lines as $line)
                            <tr>
                                <td>{{ $line->name }}@if ($line->sku)<p class="text-xs text-muted">{{ $line->sku }}</p>@endif</td>
                                <td class="text-right tabular-nums">{{ $comma($line->quantity, 3) }}</td>
                                <td class="text-right tabular-nums">{{ Money::format($line->unit_price) }}</td>
                                <td class="text-right tabular-nums">{{ bccomp((string) $line->discount, '0', 2) > 0 ? '−'.Money::format($line->discount) : '—' }}</td>
                                <td class="text-right tabular-nums">%{{ $comma($line->vat_rate) }}</td>
                                <td class="text-right font-medium tabular-nums">{{ Money::format($line->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-line text-sm">
                        <tr><td colspan="5" class="text-right text-muted">KDV hariç</td><td class="text-right tabular-nums">{{ Money::format($invoice->subtotal) }}</td></tr>
                        <tr><td colspan="5" class="text-right text-muted">KDV</td><td class="text-right tabular-nums">{{ Money::format($invoice->vat_total) }}</td></tr>
                        <tr><td colspan="5" class="text-right font-semibold">Genel toplam</td><td class="text-right font-semibold tabular-nums">{{ Money::format($invoice->total) }}</td></tr>
                        @if ($invoice->hasDifference())<tr><td colspan="5" class="text-right text-warning">{{ $invoice->kind === 'refund' ? 'İade edilen' : 'Müşterinin ödediği' }} {{ Money::format($invoice->expected_total) }} · fark</td><td class="text-right font-medium text-warning tabular-nums">{{ (bccomp($invoice->difference(), '0', 2) > 0 ? '+' : '').Money::format($invoice->difference()) }}</td></tr>@endif
                    </tfoot>
                </table>
            </div>
        </x-card>

        @if ($invoice->status === 'sent')
            <x-card title="Faturayı iptal et" class="mt-6" :description="$invoice->document_type === 'e_arsiv'
                ? 'e-Arşiv faturası iptal edilir; müşterinin sipariş sayfasındaki fatura kaldırılır ve yeni taslak hazırlanabilir. GİB\'in iptal süresine dikkat edin.'
                : 'e-Fatura sistemden iptal edilemez. Düzeltme için iade faturası kesin ya da alıcıyla GİB portalından iptal sürecini yürütün.'">
                @if ($invoice->document_type === 'e_arsiv')
                    <form method="POST" action="{{ route('store_admin.invoices.cancel', $invoice) }}" class="flex flex-wrap items-end gap-3" data-confirm="Fatura iptal edilsin mi? Bu işlem geri alınamaz.">
                        @csrf
                        <x-field name="cancel_reason" label="İptal nedeni" required maxlength="300" class="min-w-72 flex-1" />
                        <x-button variant="danger">İptal et</x-button>
                    </form>
                @endif
            </x-card>
        @endif
    @elseif ($draft)
        <form method="POST" action="{{ route('store_admin.invoices.update', $invoice) }}" id="invoice-form"
            x-data="invoiceEditor(@alpine(['lines' => $rows, 'expected' => (string) $invoice->expected_total, 'buyerType' => old('buyer_type', $invoice->buyer_type), 'errors' => (object) $lineErrors, 'max' => \App\Domain\Store\Invoicing\EInvoiceService::MAX_LINES, 'defaultVat' => '20']))">
            @csrf @method('PUT')
            <div class="space-y-6">
                <div>
                    <x-card title="Kalemler" description="Fiyatlar KDV dahildir (mağazadaki gibi). Miktar, fiyat, indirim ve KDV oranını değiştirebilir, kalem ekleyip silebilirsiniz. Sipariş, ödeme ve stok değişmez; yalnızca fatura değişir." :padding="false">
                        <div class="overflow-x-auto">
                            <table class="table-base min-w-[820px]">
                                <thead><tr><th class="w-[34%]">Kalem adı</th><th class="w-24">Miktar</th><th class="w-32">Birim fiyat (₺)</th><th class="w-28">İndirim (₺)</th><th class="w-28">KDV</th><th class="w-28 text-right">Tutar</th><th class="w-10"><span class="sr-only">Sil</span></th></tr></thead>
                                <tbody class="divide-y divide-line">
                                    <template x-for="(row, i) in rows" :key="i">
                                        <tr class="align-top">
                                            <td>
                                                <input type="hidden" :name="'lines[' + i + '][order_item_id]'" :value="row.order_item_id">
                                                <input type="hidden" :name="'lines[' + i + '][sku]'" :value="row.sku">
                                                <input type="text" class="field-input" :name="'lines[' + i + '][name]'" x-model="row.name" maxlength="200" required aria-label="Kalem adı">
                                                <span class="mt-1 block text-xs text-muted" x-show="row.sku" x-text="row.sku"></span>
                                                <span class="mt-1 block text-xs text-danger" x-show="error(i, 'name')" x-text="error(i, 'name')"></span>
                                            </td>
                                            <td>
                                                <input type="text" class="field-input text-right tabular-nums" :name="'lines[' + i + '][quantity]'" x-model="row.quantity" inputmode="decimal" required aria-label="Miktar">
                                                <span class="mt-1 block text-xs text-danger" x-show="error(i, 'quantity')" x-text="error(i, 'quantity')"></span>
                                            </td>
                                            <td>
                                                <input type="text" class="field-input text-right tabular-nums" :name="'lines[' + i + '][unit_price]'" x-model="row.unit_price" inputmode="decimal" required aria-label="Birim fiyat">
                                                <span class="mt-1 block text-xs text-danger" x-show="error(i, 'unit_price')" x-text="error(i, 'unit_price')"></span>
                                            </td>
                                            <td>
                                                <input type="text" class="field-input text-right tabular-nums" :name="'lines[' + i + '][discount]'" x-model="row.discount" inputmode="decimal" placeholder="0" aria-label="İndirim">
                                                <span class="mt-1 block text-xs text-danger" x-show="error(i, 'discount')" x-text="error(i, 'discount')"></span>
                                            </td>
                                            <td>
                                                <select class="field-input" :name="'lines[' + i + '][vat_rate]'" x-model="row.vat_rate" aria-label="KDV oranı">
                                                    @foreach ($vatRates as $rate => $label)<option value="{{ $rate }}">{{ $label }}</option>@endforeach
                                                </select>
                                            </td>
                                            <td class="pt-4 text-right font-medium tabular-nums" x-text="lineText(row)"></td>
                                            <td><button type="button" class="mt-1 rounded-lg p-2 text-muted hover:bg-danger-soft hover:text-danger" @click="remove(i)" aria-label="Kalemi sil" title="Kalemi sil"><x-icon name="trash" class="size-4" /></button></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex flex-wrap items-start justify-between gap-4 border-t border-line p-5">
                            <button type="button" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline disabled:opacity-50" @click="add()" :disabled="!canAdd()"><x-icon name="plus" class="size-4" /> Kalem ekle</button>
                            <dl class="w-full max-w-xs space-y-1.5 text-sm">
                                <div class="flex justify-between"><dt class="text-muted">KDV hariç</dt><dd class="tabular-nums" x-text="subtotalText()"></dd></div>
                                <div class="flex justify-between"><dt class="text-muted">KDV</dt><dd class="tabular-nums" x-text="vatText()"></dd></div>
                                <div class="flex justify-between border-t border-line pt-1.5 text-base font-semibold"><dt>Genel toplam</dt><dd class="tabular-nums" x-text="totalText()"></dd></div>
                                <div class="flex justify-between text-muted"><dt>{{ $invoice->kind === 'refund' ? 'İade edilen' : 'Müşterinin ödediği' }}</dt><dd class="tabular-nums">{{ Money::format($invoice->expected_total) }}</dd></div>
                            </dl>
                        </div>
                        <div x-show="hasDifference()" class="border-t border-amber-200 bg-warning-soft px-5 py-4 text-sm text-amber-900">
                            <p><span class="font-semibold">Fatura tutarı {{ $invoice->kind === 'refund' ? 'iade edilen' : 'ödenen' }} tutardan farklı:</span> fark <span class="font-semibold tabular-nums" x-text="differenceText()"></span>. Satış ile fatura tutarının farklı olması vergi açısından risklidir; emin değilseniz mali müşavirinize danışın.</p>
                            <label class="mt-2 flex items-center gap-2 font-medium">
                                <input type="checkbox" name="confirm_difference" value="1" class="size-4 rounded border-line text-primary" @checked(old('confirm_difference', $invoice->difference_confirmed_at !== null))>
                                Farkı biliyorum, bu tutarla fatura kesilsin
                            </label>
                        </div>
                        <p class="border-t border-line px-5 py-3 text-xs text-muted">Toplamlar ekranda yaklaşık gösterilir; kesin tutarlar kaydederken sunucuda hesaplanır.</p>
                    </x-card>
                </div>

                <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
                    <x-card title="Alıcı">
                        <div class="space-y-4">
                            <div>
                                <label for="f_buyer_type" class="mb-1.5 block text-sm font-medium text-ink">Fatura türü</label>
                                <select id="f_buyer_type" name="buyer_type" class="field-input" x-model="buyerType">
                                    <option value="individual">Bireysel</option>
                                    <option value="corporate">Kurumsal</option>
                                </select>
                            </div>
                            <x-field name="buyer_name" label="Ad soyad / unvan" :value="$invoice->buyer_name" required maxlength="160" />
                            <x-field name="buyer_tax_number" label="VKN / TCKN" :value="$invoice->buyer_tax_number" maxlength="11" inputmode="numeric"
                                hint="Kurumsalda 10 haneli VKN. Bireyselde TCKN; boşsa 11111111111. e-Fatura mükellefi olan alıcıya kendiliğinden e-Fatura kesilir." />
                            <div x-show="isCorporate()">
                                <x-field name="buyer_tax_office" label="Vergi dairesi" :value="$invoice->buyer_tax_office" maxlength="80" />
                            </div>
                            <x-field name="buyer_address" label="Adres" :value="$invoice->buyer_address" required maxlength="500" textarea />
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field name="buyer_district" label="İlçe" :value="$invoice->buyer_district" maxlength="60" />
                                <x-field name="buyer_city" label="İl" :value="$invoice->buyer_city" required maxlength="40" />
                            </div>
                            <x-field name="buyer_email" label="E-posta" type="email" :value="$invoice->buyer_email" maxlength="160" />
                            <x-field name="buyer_phone" label="Telefon" :value="$invoice->buyer_phone" maxlength="20" />
                        </div>
                    </x-card>

                    <x-card title="Fatura">
                        <div class="space-y-4">
                            <x-field name="issue_date" label="Fatura tarihi" type="date" :value="$invoice->issue_date->toDateString()" required hint="En fazla 7 gün geriye verilebilir." />
                            <x-field name="note" label="Açıklama (faturada görünür)" :value="$invoice->note" maxlength="500" textarea />
                            <x-toggle name="notify_customer" label="Kesilince müşteriye e-postayla bildir" :checked="(bool) old('notify_customer', $invoice->notify_customer)" />
                        </div>
                    </x-card>

                    <x-card title="Sipariş">
                        <dl class="space-y-1.5 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-muted">Sipariş</dt><dd><a href="{{ route('store_admin.orders.show', $order) }}" class="font-medium text-primary hover:underline">{{ $order->order_no }}</a></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted">Durum</dt><dd>{{ $order->statusLabel() }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted">Ödeme</dt><dd>{{ StoreOrder::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted">Ödenen</dt><dd class="tabular-nums">{{ Money::format($order->total) }}</dd></div>
                            @if (bccomp((string) $order->refunded_total, '0', 2) > 0)<div class="flex justify-between gap-3"><dt class="text-muted">İade edilen</dt><dd class="tabular-nums">{{ Money::format($order->refunded_total) }}</dd></div>@endif
                        </dl>
                    </x-card>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-button name="action" value="save" variant="secondary">Taslağı kaydet</x-button>
                <x-button name="action" value="send" icon="check" data-confirm="Fatura {{ $driver === 'uyumsoft' ? 'Uyumsoft\'a gönderilip kesilecek' : 'deneme sürücüsüyle kesilecek' }}. Kesilen fatura değiştirilemez. Devam edilsin mi?">Kaydet ve faturayı kes</x-button>
                <button type="submit" form="delete-draft" class="ml-auto text-sm font-medium text-danger hover:underline">Taslağı sil</button>
            </div>
        </form>
        <form id="delete-draft" method="POST" action="{{ route('store_admin.invoices.destroy', $invoice) }}" data-confirm="Taslak silinsin mi?" class="hidden">@csrf @method('DELETE')</form>
    @endif
</x-layouts.panel>
