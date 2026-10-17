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
    {{-- Dar alanda miktar ve düğme alt alta, genişte yan yana dizilir. --}}
    <div class="@container">
    <form method="POST" action="{{ route('dealer.cart.add') }}" class="flex flex-col gap-2 @[16.5rem]:flex-row"
        x-data="{ q: {{ (float) $product->min_order_qty }}, step: {{ (float) $product->order_step }}, min: {{ (float) $product->min_order_qty }} }">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">
        <div class="flex h-10 w-full shrink-0 items-center overflow-hidden rounded-xl border border-line bg-white @[16.5rem]:w-auto">
            <button type="button" class="flex h-full w-9 items-center justify-center text-lg text-muted transition hover:bg-slate-50 hover:text-ink" @click="q = Math.max(min, +(q - step).toFixed(3))" aria-label="Azalt">−</button>
            <input name="quantity" x-model="q" inputmode="decimal" class="h-full min-w-0 flex-1 border-x border-y-0 border-line p-0 text-center @[16.5rem]:w-14 @[16.5rem]:flex-none text-sm font-semibold tabular-nums focus:ring-0" aria-label="Miktar">
            <button type="button" class="flex h-full w-9 items-center justify-center text-lg text-muted transition hover:bg-slate-50 hover:text-ink" @click="q = +(+q + step).toFixed(3)" aria-label="Artır">+</button>
        </div>
        <x-button class="h-10 min-w-0 flex-1 rounded-xl! whitespace-nowrap" icon="cart">Sepete Ekle</x-button>
    </form>
    </div>
    <p class="mt-1.5 text-[11px] text-muted">En az {{ $min }}, {{ $step }}'er artar · {{ $product->unit->name }}</p>
@endif
