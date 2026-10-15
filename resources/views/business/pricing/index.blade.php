@php use App\Models\DiscountRule; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Fiyatlandırma">
    <x-page-header title="Fiyatlandırma" subtitle="Bayi grubu fiyat listeleri ve indirim kuralları. Bayiye özel fiyatlar bayi sayfasındadır." />

    <x-card class="mb-6">
        <p class="text-sm font-medium">Fiyat nasıl belirlenir?</p>
        <ol class="mt-2 grid gap-2 text-sm text-slate-700 sm:grid-cols-4">
            <li class="rounded-lg bg-slate-50 p-3"><b>1.</b> Bayiye özel fiyat varsa o uygulanır; indirim eklenmez.</li>
            <li class="rounded-lg bg-slate-50 p-3"><b>2.</b> Yoksa bayinin grubunun fiyat listesi.</li>
            <li class="rounded-lg bg-slate-50 p-3"><b>3.</b> Yoksa ürünün standart bayi fiyatı, o da yoksa liste fiyatı.</li>
            <li class="rounded-lg bg-slate-50 p-3"><b>4.</b> 2–3'e tek indirim: önce ürün, yoksa kategori indirimi. İndirimler toplanmaz.</li>
        </ol>
    </x-card>

    <nav class="mb-6 flex gap-1 border-b border-line" aria-label="Sekmeler">
        @foreach (['lists' => 'Fiyat listeleri', 'discounts' => 'İndirimler'] as $key => $label)
            <a href="{{ route('business.pricing', ['tab' => $key]) }}" @class([
                '-mb-px border-b-2 px-4 py-2.5 text-sm font-medium',
                'border-primary text-primary' => $tab === $key,
                'border-transparent text-muted hover:text-ink' => $tab !== $key,
            ])>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($tab === 'lists')
        <div class="grid gap-6 lg:grid-cols-3">
            <x-card title="Fiyat listeleri" class="lg:col-span-2" :padding="false">
                @if ($lists->isEmpty())
                    <x-empty-state icon="list" title="Henüz fiyat listesi yok" text="Bir liste oluşturun, ürün fiyatlarını ekleyin ve Bayi Grupları sayfasından bir gruba atayın." />
                @else
                    <table class="table-base">
                        <thead><tr><th>Liste</th><th class="text-right">Ürün</th><th>Gruplar</th><th>Geçerlilik</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($lists as $list)
                                <tr>
                                    <td><a href="{{ route('business.price-lists.show', $list) }}" class="font-medium hover:text-primary">{{ $list->name }}</a></td>
                                    <td class="text-right tabular-nums">{{ $list->items_count }}</td>
                                    <td class="text-sm text-muted">{{ $list->groups->pluck('name')->join(', ') ?: 'Atanmadı' }}</td>
                                    <td class="text-sm">
                                        @if ($list->isCurrent())<x-badge tone="success">Geçerli</x-badge>@else<x-badge>Geçerli değil</x-badge>@endif
                                        <span class="ml-1 text-xs text-muted">{{ $list->starts_on?->format('d.m.Y') ?? '…' }} – {{ $list->ends_on?->format('d.m.Y') ?? '…' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
            <x-card title="Yeni fiyat listesi">
                <form method="POST" action="{{ route('business.price-lists.store') }}" class="space-y-4">
                    @csrf
                    <x-field name="name" label="Liste adı" required placeholder="Ör. 2026 Okul Sezonu" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-field name="starts_on" label="Başlangıç" type="date" />
                        <x-field name="ends_on" label="Bitiş" type="date" />
                    </div>
                    <x-button icon="plus">Oluştur</x-button>
                </form>
            </x-card>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <x-card title="İndirim kuralları" class="lg:col-span-2" :padding="false">
                @if ($rules->isEmpty())
                    <x-empty-state icon="percent" title="Henüz indirim kuralı yok" />
                @else
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead><tr><th>Kural</th><th>Kapsam</th><th>Kime</th><th class="text-right">Oran</th><th>Geçerlilik</th><th></th></tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($rules as $rule)
                                    <tr @class(['opacity-60' => ! $rule->is_active])>
                                        <td class="font-medium">{{ $rule->name }}</td>
                                        <td class="text-sm">{{ $rule->scope === 'product' ? 'Ürün: '.$rule->product?->name : 'Kategori: '.$rule->category?->fullName() }}</td>
                                        <td class="text-sm">{{ match ($rule->target) { 'all' => 'Tüm bayiler', 'group' => 'Grup: '.$rule->group?->name, 'dealer' => 'Bayi: '.$rule->relationship?->dealer->legal_name } }}</td>
                                        <td class="text-right font-medium tabular-nums">%{{ rtrim(rtrim(number_format((float) $rule->percent, 2, ',', ''), '0'), ',') }}</td>
                                        <td class="text-xs text-muted">{{ $rule->starts_on?->format('d.m.Y') ?? '…' }} – {{ $rule->ends_on?->format('d.m.Y') ?? '…' }}</td>
                                        <td class="text-right"><form method="POST" action="{{ route('business.discounts.toggle', $rule) }}">@csrf @method('PUT')<button class="text-sm text-primary hover:underline">{{ $rule->is_active ? 'Durdur' : 'Etkinleştir' }}</button></form></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
            <x-card title="Yeni indirim">
                <form method="POST" action="{{ route('business.discounts.store') }}" class="space-y-4" x-data="{ scope: '{{ old('scope', 'category') }}', target: '{{ old('target', 'all') }}' }">
                    @csrf
                    <x-field name="name" label="Kural adı" required placeholder="Ör. Defter kampanyası" />
                    <x-field name="scope" label="Kapsam" :options="['category' => 'Kategori', 'product' => 'Tek ürün']" x-model="scope" />
                    <div x-show="scope === 'category'"><x-field name="category_id" label="Kategori" :options="['' => 'Seçin'] + $categories" /></div>
                    <div x-show="scope === 'product'" x-cloak><x-product-picker :required="false" /></div>
                    <x-field name="target" label="Kime" :options="DiscountRule::TARGETS" x-model="target" />
                    <div x-show="target === 'group'" x-cloak><x-field name="dealer_group_id" label="Bayi grubu" :options="['' => 'Seçin'] + $groups->all()" /></div>
                    <div x-show="target === 'dealer'" x-cloak><x-field name="dealer_relationship_id" label="Bayi" :options="['' => 'Seçin'] + $dealers->all()" /></div>
                    <x-field name="percent" label="İndirim oranı (%)" type="number" step="0.01" min="0.01" max="99.99" required />
                    <div class="grid grid-cols-2 gap-3">
                        <x-field name="starts_on" label="Başlangıç" type="date" />
                        <x-field name="ends_on" label="Bitiş" type="date" />
                    </div>
                    <x-button icon="plus">Ekle</x-button>
                </form>
            </x-card>
        </div>
    @endif
</x-layouts.panel>
