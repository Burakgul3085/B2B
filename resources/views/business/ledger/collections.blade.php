@php use App\Domain\Support\Money; use App\Models\CollectionEntry; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Tahsilatlar">
    <x-page-header title="Tahsilatlar" subtitle="Elle kaydedilen tahsilatlar. İptal edilen tahsilat silinmez; cariye ters kayıt yazılır." />

    @error('ledger')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <x-card :padding="false">
        <form method="GET" class="grid gap-3 border-b border-line p-4 sm:grid-cols-6 sm:items-end">
            <x-field name="from" label="Başlangıç" type="date" :value="$filters['from']" />
            <x-field name="to" label="Bitiş" type="date" :value="$filters['to']" />
            <x-field name="method" label="Yöntem" :options="['' => 'Tümü'] + CollectionEntry::METHODS" :value="$filters['method'] ?? ''" />
            <x-field name="account" label="Bayi" :options="['' => 'Tümü'] + $accounts->all()" :value="$filters['account'] ?? ''" class="sm:col-span-2" />
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" name="cancelled" value="1" @checked($filters['cancelled'] ?? false) class="size-4 rounded"> İptaller</label>
                <x-button variant="secondary" aria-label="Filtrele"><x-icon name="search" class="size-4" /></x-button>
            </div>
        </form>
        <div class="flex items-center justify-between border-b border-line bg-slate-50/60 px-4 py-3 text-sm">
            <span class="text-muted">Seçili dönemde geçerli tahsilat toplamı</span>
            <span class="font-semibold tabular-nums">{{ Money::format($total) }}</span>
        </div>
        @if ($collections->isEmpty())
            <x-empty-state icon="check-circle" title="Bu dönemde tahsilat yok" text="Tahsilatı ilgili bayinin cari hesap sayfasından ekleyin." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Tarih</th><th>Bayi</th><th>Yöntem</th><th class="text-right">Tutar</th><th class="hidden md:table-cell">Açıklama</th><th></th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($collections as $c)
                            <tr @class(['opacity-60' => $c->isCancelled()])>
                                <td class="tabular-nums">{{ $c->collected_on->format('d.m.Y') }}</td>
                                <td><a href="{{ route('business.accounts.show', $c->account) }}" class="hover:text-primary">{{ $c->account->relationship->dealer->legal_name }}</a></td>
                                <td>{{ CollectionEntry::METHODS[$c->method] }}</td>
                                <td class="text-right font-medium tabular-nums">{{ Money::format($c->amount) }}</td>
                                <td class="hidden max-w-64 text-sm text-muted md:table-cell">{{ $c->description }}@if ($c->order) · {{ $c->order->order_no }}@endif</td>
                                <td class="text-right">
                                    @if ($c->isCancelled())
                                        <x-badge>İptal: {{ $c->cancel_reason }}</x-badge>
                                    @else
                                        <details class="text-left">
                                            <summary class="cursor-pointer text-sm text-danger">İptal</summary>
                                            <form method="POST" action="{{ route('business.collections.cancel', $c) }}" class="mt-2 flex gap-2" data-confirm="Tahsilat iptal edilsin mi?">
                                                @csrf
                                                <input name="reason" required maxlength="500" class="field-input w-40 py-1" placeholder="Neden" aria-label="İptal nedeni">
                                                <x-button variant="danger" size="sm">İptal et</x-button>
                                            </form>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($collections->hasPages())<div class="border-t border-line p-4">{{ $collections->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
