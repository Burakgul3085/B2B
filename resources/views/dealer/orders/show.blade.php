@php
    use App\Domain\Inventory\Quantity;
    use App\Domain\Support\Money;
    use Illuminate\Support\Carbon;

    $placed = $order->created_at->timezone('Europe/Istanbul');
    $closed = in_array($order->status, ['rejected', 'cancelled'], true);
    $pieces = $order->items->reduce(fn ($c, $i) => bcadd($c, $i->effectiveQuantity(), 3), '0');
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" :title="$order->order_no">
    <x-page-header :title="'Sipariş '.$order->order_no" :subtitle="$placed->translatedFormat('d F Y, H:i').' tarihinde verildi'">
        <x-slot:breadcrumb><a href="{{ route('dealer.orders.index') }}" class="hover:underline">Siparişlerim</a></x-slot:breadcrumb>
        <x-badge :tone="$order->statusTone()">{{ $order->statusLabel() }}</x-badge>
        <span class="print:hidden"><x-button variant="secondary" size="sm" icon="file" type="button" data-print>Yazdır</x-button></span>
        @if ($currentBusiness->moduleEnabled('messaging'))
            <span class="print:hidden"><x-button variant="secondary" size="sm" icon="inbox" :href="route('dealer.messages.create', ['context' => 'order', 'id' => $order->id])">Mesaj gönder</x-button></span>
        @endif
        <form method="POST" action="{{ route('dealer.orders.reorder', $order) }}" class="print:hidden">
            @csrf
            <x-button size="sm" icon="refresh">Tekrar sipariş ver</x-button>
        </form>
    </x-page-header>

    @error('order')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    @include('dealer.orders._payment')

    {{-- Sipariş nerede? --}}
    <section class="mb-6 rounded-2xl border border-line bg-white px-5 py-6 shadow-xs sm:px-8">
        @include('dealer.orders._progress', ['order' => $order])
    </section>

    {{-- Kısa bilgiler --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-line bg-white p-5 shadow-xs">
            <p class="text-sm text-muted">Sipariş tutarı</p>
            <p @class(['mt-1 text-2xl font-bold tabular-nums', 'text-ink' => ! $closed, 'text-slate-400 line-through' => $closed])>{{ Money::format($order->grand_total) }}</p>
            <p class="text-xs text-muted">KDV dahil{{ bccomp((string) $order->delivery_fee, '0', 2) > 0 ? ' · teslimat '.Money::format($order->delivery_fee) : '' }}@if ($order->payment_method) · {{ $order->paymentMethodLabel() }}@endif</p>
        </div>
        <div class="rounded-2xl border border-line bg-white p-5 shadow-xs">
            <p class="text-sm text-muted">Ürünler</p>
            <p class="mt-1 text-2xl font-bold text-ink tabular-nums">{{ $order->items->count() }} <span class="text-base font-medium text-muted">kalem</span></p>
            <p class="text-xs text-muted">Toplam {{ Quantity::format($pieces) }} adet/paket</p>
        </div>
        <div class="rounded-2xl border border-line bg-white p-5 shadow-xs">
            <p class="text-sm text-muted">Son ödeme günü</p>
            @if ($dueDate && ! $closed)
                @php $due = Carbon::parse($dueDate); $days = (int) Carbon::today('Europe/Istanbul')->diffInDays($due, false); @endphp
                <p class="mt-1 text-2xl font-bold text-ink tabular-nums">{{ $due->format('d.m.Y') }}</p>
                <p @class(['text-xs', 'font-semibold text-red-700' => $days < 0, 'text-muted' => $days >= 0])>{{ $days < 0 ? abs($days).' gün gecikti' : ($days === 0 ? 'Bugün' : $days.' gün kaldı') }} · <a href="{{ route('dealer.account.statement') }}" class="text-primary hover:underline">Cari hesap</a></p>
            @else
                <p class="mt-1 text-2xl font-bold text-slate-400">—</p>
                <p class="text-xs text-muted">{{ $closed ? 'Borç kaydı oluşmadı' : 'Sipariş onaylanınca belirlenir' }}</p>
            @endif
        </div>
        <div class="rounded-2xl border border-line bg-white p-5 shadow-xs">
            <p class="text-sm text-muted">Planlanan teslim</p>
            @if ($order->delivery_planned_on)
                <p class="mt-1 text-2xl font-bold text-ink tabular-nums">{{ $order->delivery_planned_on->format('d.m.Y') }}</p>
                <p class="text-xs text-muted">{{ $order->delivery_planned_on->translatedFormat('l') }}</p>
            @else
                <p class="mt-1 text-2xl font-bold text-slate-400">—</p>
                <p class="text-xs text-muted">{{ $closed ? 'Teslimat yok' : 'Toptancınız bildirecek' }}</p>
            @endif
        </div>
    </div>

    @if ($changes)
        <div class="mb-6 rounded-xl border-2 border-amber-300 bg-warning-soft px-4 py-4 text-sm text-amber-900">
            <p class="flex items-center gap-2 font-semibold"><x-icon name="alert" class="size-4 shrink-0" /> Siparişinizde değişiklik yapıldı</p>
            <p class="mt-1">Aşağıdaki ürünler istediğinizden farklı miktarda onaylandı. Tutar onaylanan miktarlara göre hesaplandı{{ $payable ? '; yalnızca onaylanan tutarı ödeyin' : '' }}.</p>
            <ul class="mt-3 divide-y divide-amber-200 rounded-lg bg-white/60">
                @foreach ($changes as $c)
                    <li class="flex flex-wrap justify-between gap-2 px-3 py-2">
                        <span class="font-medium text-ink">{{ $c['name'] }}</span>
                        <span class="tabular-nums"><span class="line-through opacity-70">{{ Quantity::format($c['requested']) }}</span> → <b>{{ Quantity::format($c['approved']) }}</b> {{ $c['unit'] }}</span>
                    </li>
                @endforeach
            </ul>
            @if ($order->requested_total !== null && bccomp((string) $order->requested_total, (string) $order->grand_total, 2) !== 0)
                <p class="mt-3">Sipariş tutarınız <span class="line-through">{{ Money::format($order->requested_total) }}</span> yerine <b>{{ Money::format($order->grand_total) }}</b>.</p>
            @endif
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Ürünler" class="lg:col-span-2 print:col-span-3" :padding="false">
            @include('partials.order-items', ['order' => $order, 'imageRoute' => 'dealer.images.show'])
        </x-card>

        <div class="space-y-6">
            <x-card title="Teslimat adresi">
                <div class="flex gap-3 text-sm">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-muted"><x-icon name="map-pin" class="size-4" /></span>
                    <div class="space-y-0.5">
                        <p class="font-semibold text-ink">{{ $order->ship_title }}</p>
                        <p>{{ $order->ship_recipient }}</p>
                        <p class="text-muted">{{ $order->ship_address }}</p>
                        <p class="text-muted">{{ $order->ship_district }} / {{ $order->ship_city }}</p>
                        <p class="flex items-center gap-1.5 pt-1 text-muted"><x-icon name="phone" class="size-3.5" />{{ $order->ship_phone }}</p>
                        @if ($order->ship_note)<p class="pt-1 text-muted">Adres notu: {{ $order->ship_note }}</p>@endif
                    </div>
                </div>
                @if ($order->delivery_note || $order->dealer_note)
                    <div class="mt-4 space-y-2 border-t border-line pt-4 text-sm">
                        @if ($order->dealer_note)<p><span class="block text-xs font-semibold text-muted uppercase">Sizin notunuz</span>{{ $order->dealer_note }}</p>@endif
                        @if ($order->delivery_note)<p><span class="block text-xs font-semibold text-muted uppercase">Toptancı notu</span>{{ $order->delivery_note }}</p>@endif
                    </div>
                @endif
            </x-card>

            <x-card title="Durum geçmişi" class="print:hidden">@include('partials.order-history', ['order' => $order])</x-card>

            @if ($canCancel || $canRequestCancel)
                <div x-data="{ open: {{ $errors->has('reason') ? 'true' : 'false' }} }" class="print:hidden">
                    <button type="button" x-show="!open" @click="open = true" class="w-full rounded-xl border border-dashed border-line px-4 py-3 text-sm font-medium text-muted transition hover:border-red-300 hover:text-red-700">
                        {{ $canCancel ? 'Siparişi iptal et' : 'İptal talebi gönder' }}
                    </button>
                    <x-card x-cloak x-show="open" :title="$canCancel ? 'Siparişi iptal et' : 'İptal talebi'" :description="$canCancel ? 'Sipariş henüz onaylanmadı; doğrudan iptal edebilirsiniz.' : 'Sipariş onaylandı; iptal için toptancınızın onayı gerekir.'">
                        <form method="POST" action="{{ route('dealer.orders.cancel', $order) }}" class="space-y-3" data-confirm="Emin misiniz?">
                            @csrf
                            <x-field name="reason" label="İptal nedeni" textarea required />
                            <div class="flex items-center gap-3">
                                <x-button variant="danger" size="sm">{{ $canCancel ? 'İptal et' : 'İptal talebi gönder' }}</x-button>
                                <button type="button" @click="open = false" class="text-sm text-muted hover:text-ink">Vazgeç</button>
                            </div>
                        </form>
                    </x-card>
                </div>
            @endif
        </div>
    </div>
</x-layouts.panel>
