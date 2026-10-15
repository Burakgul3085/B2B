{{-- Sepete ekleme veya stok yoksa ürün talebi. $product, $level, $hasOpenRequest --}}
@php use App\Domain\Inventory\Quantity; @endphp
@if ($level['state'] === 'out')
    @if ($hasOpenRequest)
        <p class="rounded-lg bg-slate-50 px-3 py-2 text-center text-xs text-muted">Açık talebiniz var</p>
    @elseif ($currentBusiness->moduleEnabled('product_requests'))
        <div x-data="{ open: false }">
            <x-button type="button" variant="secondary" size="sm" class="w-full" @click="open = !open">Ürün talebi oluştur</x-button>
            <form x-cloak x-show="open" method="POST" action="{{ route('dealer.requests.store') }}" class="mt-2 space-y-2">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input name="quantity" inputmode="decimal" required value="{{ Quantity::format($product->min_order_qty) }}" class="field-input py-1.5" aria-label="Talep edilen miktar">
                <input name="note" maxlength="1000" class="field-input py-1.5" placeholder="Not (isteğe bağlı)" aria-label="Not">
                <label class="flex items-center gap-2 text-xs"><input type="hidden" name="notify" value="0"><input type="checkbox" name="notify" value="1" checked class="size-3.5 rounded"> Stoğa girince haber ver</label>
                <x-button size="sm" class="w-full">Talebi gönder</x-button>
            </form>
        </div>
    @else
        <p class="rounded-lg bg-slate-50 px-3 py-2 text-center text-xs text-muted">Şu an sipariş verilemiyor</p>
    @endif
@else
    @php $step = Quantity::format($product->order_step); $min = Quantity::format($product->min_order_qty); @endphp
    <form method="POST" action="{{ route('dealer.cart.add') }}" class="flex gap-2"
        x-data="{ q: {{ (float) $product->min_order_qty }}, step: {{ (float) $product->order_step }}, min: {{ (float) $product->min_order_qty }} }">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">
        <div class="flex items-center rounded-lg border border-line">
            <button type="button" class="px-2 py-1.5 text-muted hover:text-ink" @click="q = Math.max(min, +(q - step).toFixed(3))" aria-label="Azalt">−</button>
            <input name="quantity" x-model="q" inputmode="decimal" class="w-16 border-0 p-1 text-center text-sm tabular-nums focus:ring-0" aria-label="Miktar">
            <button type="button" class="px-2 py-1.5 text-muted hover:text-ink" @click="q = +(+q + step).toFixed(3)" aria-label="Artır">+</button>
        </div>
        <x-button size="sm" class="flex-1">Sepete ekle</x-button>
    </form>
    <p class="mt-1 text-[11px] text-muted">En az {{ $min }}, {{ $step }}'er artar · {{ $product->unit->name }}</p>
@endif
