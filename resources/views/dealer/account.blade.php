@php use App\Domain\Support\Money; use App\Models\CollectionEntry; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Cari Hesap">
    <x-page-header title="Cari hesabım" :subtitle="$currentBusiness->display_name.' · cari kodu '.$account->code" />

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi label="Bakiye" :value="Money::format($account->balance)" :hint="bccomp((string) $account->balance, '0', 2) > 0 ? 'Borç bakiyesi' : 'Borcunuz yok'" />
        <x-kpi label="Vadesi geçmiş" :value="Money::format($aging['overdue'])" />
        <x-kpi label="7 gün içinde vadesi gelen" :value="Money::format($aging['due_soon'])" />
        <x-kpi label="Vadesi gelmemiş" :value="Money::format($aging['not_due'])" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Ekstre" class="lg:col-span-2" :padding="false">
            @include('partials.statement', ['action' => route('dealer.account.statement'), 'orderRoute' => 'dealer.orders.show'])
        </x-card>
        <x-card title="Son tahsilatlar" :padding="false">
            @if ($collections->isEmpty())
                <p class="p-5 text-sm text-muted">Kayıtlı tahsilat yok.</p>
            @else
                <ul class="divide-y divide-line text-sm">
                    @foreach ($collections as $c)
                        <li class="flex justify-between gap-3 px-5 py-3">
                            <span>{{ $c->collected_on->format('d.m.Y') }}<span class="block text-xs text-muted">{{ CollectionEntry::METHODS[$c->method] }}</span></span>
                            <span class="font-medium tabular-nums">{{ Money::format($c->amount) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <p class="border-t border-line px-5 py-3 text-xs text-muted">Ödemeleriniz toptancınız tarafından kaydedilir. Uyuşmazlık için toptancınıza mesaj gönderin.</p>
        </x-card>
    </div>
</x-layouts.panel>
