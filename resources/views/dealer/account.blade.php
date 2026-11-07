@php
    use App\Domain\Support\Money;
    use App\Domain\Support\Payments\Iban;
    use App\Models\CollectionEntry;
    use Illuminate\Support\Carbon;

    $balance = (string) $account->balance;
    $state = bccomp($balance, '0', 2); // 1: borç, -1: alacak, 0: kapalı
    $open = $aging['items'];
    $next = $open->filter(fn ($i) => $i['due'] !== null)->sortBy('due')->first();
    $nextDays = $next ? (int) Carbon::today('Europe/Istanbul')->diffInDays(Carbon::parse($next['due']), false) : null;
    $rel = $account->relationship;
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Cari Hesap">
    @include('partials.statement-print', ['business' => $currentBusiness, 'account' => $account, 'aging' => $aging,
        'statementArgs' => ['action' => route('dealer.account.statement'), 'orderRoute' => null, 'forDealer' => true]])
    <div class="print:hidden">
    <x-page-header title="Cari hesabım" :subtitle="$currentBusiness->display_name.' ile hesabınız · cari kodu '.$account->code" />

    {{-- Tek cümlelik durum --}}
    <section @class([
        'mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-6 shadow-xs',
        'border-red-200 bg-danger-soft' => bccomp($aging['overdue'], '0', 2) > 0,
        'border-line bg-white' => bccomp($aging['overdue'], '0', 2) === 0,
    ])>
        <div class="min-w-0">
            @if (bccomp($aging['overdue'], '0', 2) > 0)
                <p class="text-2xl font-bold text-red-800"><span class="tabular-nums">{{ Money::format($aging['overdue']) }}</span> ödemenizin son günü geçti</p>
                <p class="mt-1 text-sm text-red-900">Lütfen en kısa sürede ödeyin. Ödeyene kadar yeni siparişlerde sonra öde seçilemez.</p>
            @elseif ($state > 0)
                <p class="text-2xl font-bold text-ink"><span class="tabular-nums">{{ Money::format($balance) }}</span> ödemeniz var</p>
                <p class="mt-1 text-sm text-muted">
                    @if ($next)
                        En yakın son ödeme günü <b class="text-ink">{{ Carbon::parse($next['due'])->format('d.m.Y') }}</b> ({{ $nextDays === 0 ? 'bugün' : $nextDays.' gün kaldı' }}).
                    @else
                        Son ödeme günü teslimatla belli olur.
                    @endif
                </p>
            @elseif ($state < 0)
                <p class="text-2xl font-bold text-emerald-700"><span class="tabular-nums">{{ Money::format(ltrim($balance, '-')) }}</span> fazla ödemeniz var</p>
                <p class="mt-1 text-sm text-muted">Sonraki alımlarınızdan düşülür.</p>
            @else
                <p class="flex items-center gap-2 text-2xl font-bold text-emerald-700"><x-icon name="check-circle" class="size-6" />Borcunuz yok</p>
                <p class="mt-1 text-sm text-muted">Bütün ödemeleriniz alındı.</p>
            @endif
        </div>
        <div class="text-sm text-muted sm:text-right">
            <p>Ödeme şekliniz</p>
            <p class="font-semibold text-ink">{{ $rel->payLaterEnabled() ? $rel->payLaterLabel() : 'Kart veya havale (peşin)' }}</p>
            @if ($rel->payLaterEnabled() && $rel->credit_limit !== null)
                <p class="text-xs">Borç limiti {{ Money::format($rel->credit_limit) }}</p>
            @endif
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Ödemesi bekleyen siparişlerim" :padding="false"
                description="Ödemek için siparişe tıklayın: kartla ödeyebilir veya havale bilgilerini görebilirsiniz.">
                @if ($open->isEmpty())
                    <x-empty-state icon="check-circle" title="Bekleyen ödemeniz yok" />
                @else
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead><tr><th>Sipariş</th><th>Son ödeme</th><th class="text-right">Kalan</th><th class="text-right">Durum</th></tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($open as $item)
                                    @include('partials.ledger-open-item', ['item' => $item, 'orderRoute' => 'dealer.orders.show'])
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            <x-card title="Hesap hareketleri" description="Siparişleriniz borcunuza eklenir; ödemeleriniz ve iadeleriniz borcunuzdan düşülür." :padding="false">
                @include('partials.statement', ['action' => route('dealer.account.statement'), 'orderRoute' => 'dealer.orders.show', 'forDealer' => true])
            </x-card>
        </div>

        <div class="space-y-6">
            @if ($bankAccounts !== [])
                <x-card title="Havale / EFT bilgileri" :description="'Toplu ödemede açıklamaya cari kodunuzu ('.$account->code.'), tek sipariş için sipariş numarasını yazın.'">
                    <ul class="space-y-3">
                        @foreach ($bankAccounts as $b)
                            <li class="flex items-center gap-3 rounded-xl border border-line p-3">
                                <span class="flex h-9 w-20 shrink-0 items-center justify-center rounded-lg bg-[#fff] px-2 ring-1 ring-line">
                                    @if ($b['logo'])<img src="{{ $b['logo'] }}" alt="{{ $b['bank'] }}" class="max-h-5 max-w-full object-contain">@else<span class="text-xs font-bold text-muted">{{ mb_substr($b['bank'], 0, 2) }}</span>@endif
                                </span>
                                <span class="min-w-0 text-sm">
                                    <span class="block font-semibold">{{ $b['bank'] }} · <span class="font-normal text-muted">{{ $b['holder'] }}</span></span>
                                    <span class="block font-mono text-xs break-all">{{ Iban::format($b['iban']) }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-card title="Son ödemeleriniz" :padding="false">
                @if ($collections->isEmpty())
                    <p class="p-5 text-sm text-muted">Kayıtlı ödemeniz yok.</p>
                @else
                    <ul class="divide-y divide-line text-sm">
                        @foreach ($collections as $c)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <span class="flex items-center gap-3">
                                    <span class="flex size-8 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"><x-icon name="check" class="size-4" /></span>
                                    <span>{{ $c->collected_on->format('d.m.Y') }}<span class="block text-xs text-muted">{{ CollectionEntry::METHODS[$c->method] }}</span></span>
                                </span>
                                <span class="font-semibold text-emerald-700 tabular-nums">{{ Money::format($c->amount) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="border-t border-line px-5 py-3 text-xs text-muted">Kart ödemeleriniz kendiliğinden işlenir; havale ve teslimattaki ödemeleri toptancınız kaydeder. Eksik bir kayıt görürseniz <a href="{{ route('dealer.messages.index') }}" class="font-medium text-primary hover:underline">mesaj gönderin</a>.</p>
            </x-card>
        </div>
    </div>
    </div>
</x-layouts.panel>
