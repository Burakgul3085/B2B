@php
    use App\Domain\Support\Money;
    use App\Models\CollectionEntry;
    use Illuminate\Support\Carbon;

    $balance = (string) $account->balance;
    $state = bccomp($balance, '0', 2); // 1: borç, -1: alacak, 0: kapalı
    $open = $aging['items']->sortBy('due')->values();
    $nextDue = $open->first()['due'] ?? null;
    $nextAmount = $nextDue ? $open->where('due', $nextDue)->reduce(fn ($c, $i) => bcadd($c, $i['open'], 2), '0.00') : null;
    $today = Carbon::today();
    $buckets = [
        ['key' => 'overdue', 'label' => 'Gecikmiş', 'hint' => 'Ödeme günü geçmiş', 'bar' => 'bg-red-500', 'text' => 'text-red-700'],
        ['key' => 'due_soon', 'label' => 'Bu hafta ödenecek', 'hint' => 'Önümüzdeki 7 gün içinde', 'bar' => 'bg-amber-400', 'text' => 'text-amber-700'],
        ['key' => 'not_due', 'label' => 'İleri tarihli', 'hint' => 'Ödeme günü henüz gelmedi', 'bar' => 'bg-sky-500', 'text' => 'text-ink'],
    ];
    $total = (float) $aging['open'];
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Cari Hesap">
    <x-page-header title="Cari hesabım" :subtitle="$currentBusiness->display_name.' ile hesabınız · cari kodu '.$account->code" />

    {{-- Durum: tek bakışta ne kadar borç var, gecikme var mı, sıradaki ödeme ne zaman --}}
    <section class="mb-6 overflow-hidden rounded-2xl border border-line bg-white shadow-xs">
        <div class="grid lg:grid-cols-12">
            <div class="border-b border-line p-6 lg:col-span-5 lg:border-r lg:border-b-0">
                <p class="text-sm font-medium text-muted">{{ $state > 0 ? 'Toptancınıza toplam borcunuz' : ($state < 0 ? 'Toptancınızdan alacağınız' : 'Hesap bakiyeniz') }}</p>
                <p @class(['mt-1 text-4xl font-bold tracking-tight tabular-nums', 'text-ink' => $state >= 0, 'text-emerald-700' => $state < 0])>{{ Money::format(ltrim($balance, '-')) }}</p>

                <div class="mt-4">
                    @if (bccomp($aging['overdue'], '0', 2) > 0)
                        <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-red-200"><span class="size-2 rounded-full bg-red-500"></span>{{ Money::format($aging['overdue']) }} tutarında gecikmiş ödemeniz var</span>
                    @elseif (bccomp($aging['due_soon'], '0', 2) > 0)
                        <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-800 ring-1 ring-amber-200"><span class="size-2 rounded-full bg-amber-400"></span>Bu hafta {{ Money::format($aging['due_soon']) }} ödemeniz var</span>
                    @elseif ($state > 0)
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200"><span class="size-2 rounded-full bg-emerald-500"></span>Gecikmiş ödemeniz yok</span>
                    @elseif ($state < 0)
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200"><span class="size-2 rounded-full bg-emerald-500"></span>Fazla ödemeniz sonraki alımlarınızdan düşülür</span>
                    @else
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200"><span class="size-2 rounded-full bg-emerald-500"></span>Borcunuz bulunmuyor</span>
                    @endif
                </div>

                @if ($nextDue)
                    @php $nextDate = Carbon::parse($nextDue); $days = (int) $today->diffInDays($nextDate, false); @endphp
                    <div class="mt-5 flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3">
                        <span class="flex size-10 shrink-0 flex-col items-center justify-center rounded-lg bg-white text-center leading-none shadow-xs ring-1 ring-line">
                            <span class="text-[10px] font-semibold text-primary uppercase">{{ $nextDate->translatedFormat('M') }}</span>
                            <span class="text-base font-bold text-ink">{{ $nextDate->format('d') }}</span>
                        </span>
                        <div class="min-w-0 text-sm">
                            <p class="text-muted">{{ $days < 0 ? 'Geciken ödeme günü' : 'Sıradaki ödeme günü' }}</p>
                            <p class="font-semibold text-ink">{{ $nextDate->translatedFormat('d F Y, l') }} · {{ Money::format($nextAmount) }}
                                <span class="font-normal text-muted">({{ $days < 0 ? abs($days).' gün geçti' : ($days === 0 ? 'bugün' : $days.' gün kaldı') }})</span></p>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex flex-col justify-center p-6 lg:col-span-7">
                <p class="text-sm font-semibold text-ink">Borcunuzun ödeme durumu</p>
                <p class="text-xs text-muted">Ödemeleriniz önce en eski alımlarınızdan düşülür.</p>
                <div class="mt-4 flex h-2.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                    @foreach ($buckets as $b)
                        @if ($total > 0 && (float) $aging[$b['key']] > 0)
                            <span class="{{ $b['bar'] }}" style="width: {{ round((float) $aging[$b['key']] / $total * 100, 2) }}%"></span>
                        @endif
                    @endforeach
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    @foreach ($buckets as $b)
                        @php $has = bccomp($aging[$b['key']], '0', 2) > 0; @endphp
                        <div class="rounded-xl border border-line px-4 py-3">
                            <p class="flex items-center gap-2 text-xs font-semibold text-muted"><span class="size-2 rounded-full {{ $b['bar'] }}"></span>{{ $b['label'] }}</p>
                            <p @class(['mt-1 text-xl font-bold tabular-nums', $has ? $b['text'] : 'text-slate-400'])>{{ Money::format($aging[$b['key']]) }}</p>
                            <p class="text-xs text-muted">{{ $b['hint'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Hesap hareketleri" description="Alımlarınız borcunuza eklenir; ödemeleriniz ve iadeleriniz borcunuzdan düşülür." class="lg:col-span-2 print:col-span-3" :padding="false">
            @include('partials.statement', ['action' => route('dealer.account.statement'), 'orderRoute' => 'dealer.orders.show', 'forDealer' => true])
        </x-card>

        <div class="space-y-6 print:hidden">
            <x-card title="Ödeme takvimi" description="Henüz ödenmemiş alımlarınız ve son ödeme günleri." :padding="false">
                @if ($open->isEmpty())
                    <p class="p-5 text-sm text-muted">Ödenmemiş alımınız yok.</p>
                @else
                    <ul class="divide-y divide-line text-sm">
                        @foreach ($open->take(6) as $item)
                            @php $m = $item['movement']; $d = (int) $today->diffInDays(Carbon::parse($item['due']), false); @endphp
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    @if ($m->order_id)
                                        <a href="{{ route('dealer.orders.show', $m->order_id) }}" class="block truncate font-medium text-ink hover:text-primary">{{ $m->description }}</a>
                                    @else
                                        <span class="block truncate font-medium text-ink">{{ $m->description }}</span>
                                    @endif
                                    <span @class(['text-xs', 'font-semibold text-red-700' => $d < 0, 'font-semibold text-amber-700' => $d >= 0 && $d <= 7, 'text-muted' => $d > 7])>
                                        {{ Carbon::parse($item['due'])->format('d.m.Y') }} · {{ $d < 0 ? abs($d).' gün gecikti' : ($d === 0 ? 'bugün' : $d.' gün kaldı') }}
                                    </span>
                                </div>
                                <span class="shrink-0 font-semibold tabular-nums">{{ Money::format($item['open']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($open->count() > 6)
                        <p class="border-t border-line px-5 py-2.5 text-xs text-muted">ve {{ $open->count() - 6 }} kalem daha</p>
                    @endif
                @endif
            </x-card>

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
                <p class="border-t border-line px-5 py-3 text-xs text-muted">Ödemelerinizi toptancınız kaydeder. Eksik veya hatalı bir kayıt görürseniz toptancınıza <a href="{{ route('dealer.messages.index') }}" class="font-medium text-primary hover:underline">mesaj gönderin</a>.</p>
            </x-card>
        </div>
    </div>
</x-layouts.panel>
