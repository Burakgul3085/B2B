@php use App\Domain\Inventory\Quantity; use App\Models\ProductRequest; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Ürün Talepleri">
    <x-page-header title="Ürün Talepleri" subtitle="Bayilerin stokta olmayan ürünler için talepleri. Talepler stok ayırmaz; stok girişi yapıldığında “gelince haber ver” talepleri otomatik “Stok güncellendi” olur." />

    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Durum">
        @foreach (['open' => 'Açık talepler'] + ProductRequest::STATUSES as $key => $label)
            @continue(in_array($key, ['new', 'in_review', 'available'], true))
            <a href="{{ route('business.product-requests.index', ['status' => $key]) }}" @class([
                'rounded-full px-3 py-1.5 text-sm font-medium',
                'bg-primary text-white' => $status === $key,
                'bg-white text-muted ring-1 ring-line hover:text-ink' => $status !== $key,
            ])>{{ $label }}</a>
        @endforeach
    </nav>

    <x-card :padding="false">
        <form method="GET" class="flex gap-2 border-b border-line p-4">
            <input type="hidden" name="status" value="{{ $status }}">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="field-input max-w-sm" placeholder="Ürün adı veya kodu" aria-label="Ara">
            <x-button variant="secondary" icon="search">Ara</x-button>
        </form>
        @if ($requests->isEmpty())
            <x-empty-state icon="inbox" title="Talep yok" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($requests as $r)
                    @php $d = $demand->get($r->product_id); @endphp
                    <li class="grid gap-4 px-5 py-4 lg:grid-cols-5" x-data="{ edit: false }">
                        <div class="lg:col-span-2">
                            <a href="{{ route('business.products.show', $r->product_id) }}" class="font-medium hover:text-primary">{{ $r->product->name }}</a>
                            <p class="text-xs text-muted">{{ $r->product->sku }} · {{ $r->relationship->dealer->legal_name }}</p>
                            <p class="mt-1 text-sm"><b class="tabular-nums">{{ Quantity::format($r->quantity) }} {{ $r->product->unit->name }}</b> · {{ $r->created_at->timezone('Europe/Istanbul')->format('d.m.Y') }}
                                @if ($r->notify_when_in_stock)<span class="text-xs text-muted">· gelince haber ver</span>@endif</p>
                            @if ($r->note)<p class="mt-1 text-sm text-slate-700">“{{ $r->note }}”</p>@endif
                            @if ($d && $d->dealers > 1)<p class="mt-1 text-xs font-medium text-primary">Bu ürünü {{ $d->dealers }} bayi talep ediyor (toplam {{ Quantity::format($d->qty) }})</p>@endif
                        </div>
                        <div><x-badge :tone="$r->statusTone()">{{ ProductRequest::STATUSES[$r->status] }}</x-badge>
                            @if ($r->response_note)<p class="mt-1 text-sm text-muted">{{ $r->response_note }}</p>@endif</div>
                        <div class="lg:col-span-2">
                            @if ($r->status !== 'closed')
                                <form method="POST" action="{{ route('business.product-requests.respond', $r) }}" class="flex flex-wrap gap-2">
                                    @csrf @method('PUT')
                                    <select name="status" class="field-input w-auto py-1.5" aria-label="Durum">
                                        @foreach (['in_review', 'available', 'unavailable', 'stock_updated', 'closed'] as $s)<option value="{{ $s }}" @selected($r->status === $s)>{{ ProductRequest::STATUSES[$s] }}</option>@endforeach
                                    </select>
                                    <input name="response_note" value="{{ $r->response_note }}" maxlength="1000" class="field-input min-w-40 flex-1 py-1.5" placeholder="Bayiye yanıt" aria-label="Yanıt">
                                    <x-button size="sm" variant="secondary">Kaydet</x-button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($requests->hasPages())<div class="border-t border-line p-4">{{ $requests->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
