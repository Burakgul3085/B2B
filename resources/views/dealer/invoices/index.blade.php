@php use App\Domain\Support\Money; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Faturalarım">
    <x-page-header title="Faturalarım" :subtitle="$currentBusiness->display_name.' tarafından size kesilen satış ve iade faturaları'" />

    <x-card :padding="false">
        @if ($invoices->isEmpty())
            <x-empty-state icon="file" title="Henüz faturanız yok" text="Siparişinizin faturası kesildiğinde burada ve sipariş sayfasında görünür." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Fatura</th><th>Sipariş</th><th class="hidden sm:table-cell">Tarih</th><th class="text-right">Tutar</th><th></th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td><span class="font-medium text-ink">{{ $invoice->invoice_no }}</span><p class="text-xs text-muted">{{ $invoice->label() }}</p></td>
                                <td><a href="{{ route('dealer.orders.show', $invoice->order) }}" class="text-primary hover:underline">{{ $invoice->order->order_no }}</a></td>
                                <td class="hidden tabular-nums text-muted sm:table-cell">{{ $invoice->issue_date->format('d.m.Y') }}</td>
                                <td class="text-right font-medium tabular-nums">{{ $invoice->kind === 'refund' ? '−' : '' }}{{ Money::format($invoice->total) }}</td>
                                <td class="text-right">
                                    @if ($invoice->pdf_path)
                                        <x-button size="sm" variant="secondary" icon="download" :href="route('dealer.invoices.pdf', $invoice)" target="_blank" rel="noopener">İndir</x-button>
                                    @else
                                        <span class="text-xs text-muted">PDF hazırlanıyor</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($invoices->hasPages())<div class="border-t border-line p-4">{{ $invoices->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
