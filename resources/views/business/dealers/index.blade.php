@php use App\Models\DealerRelationship; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Bayiler">
    <x-page-header title="Bayiler" subtitle="Sizinle çalışan bayiler ve ticari koşulları.">
        <x-button icon="plus" :href="route('business.dealers.create')">Yeni bayi</x-button>
    </x-page-header>

    @if (! $hasAny)
        <x-card>
            <x-empty-state icon="users" title="Henüz bayiniz yok" text="Başvuru formu bağlantınızı paylaşın veya bayi hesabını doğrudan açın.">
                <div class="flex flex-wrap justify-center gap-2">
                    <x-button icon="plus" :href="route('business.dealers.create')">Bayi hesabı aç</x-button>
                    <x-button variant="secondary" :href="route('business.applications.index')">Başvurular</x-button>
                </div>
            </x-empty-state>
        </x-card>
    @else
        <x-card :padding="false">
            <form method="GET" class="grid gap-3 border-b border-line p-4 sm:grid-cols-3 sm:items-end">
                <x-field name="q" label="Ara" :value="$filters['q'] ?? ''" placeholder="Unvan, bayi kodu, e-posta" class="sm:col-span-2" />
                <div class="flex gap-2">
                    <x-field name="status" label="Durum" :options="['' => 'Tümü'] + DealerRelationship::STATUSES" :value="$filters['status'] ?? ''" class="flex-1" />
                    <x-button variant="secondary" class="self-end" aria-label="Filtrele"><x-icon name="search" class="size-4" /></x-button>
                </div>
            </form>
            @if ($relationships->isEmpty())
                <x-empty-state icon="search" title="Filtreye uyan bayi yok" />
            @else
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr><th>Bayi</th><th class="hidden sm:table-cell">Ödeme</th><th>Hesap</th><th>Durum</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($relationships as $r)
                                <tr>
                                    <td>
                                        <a href="{{ route('business.dealers.show', $r) }}" class="font-medium hover:text-primary">{{ $r->dealer->legal_name }}</a>
                                        <p class="text-xs text-muted">{{ $r->dealer_code }} · {{ $r->dealer->city }}</p>
                                    </td>
                                    <td class="hidden sm:table-cell">{{ $r->allow_pay_later ? $r->payLaterLabel() : 'Peşin' }}</td>
                                    <td>@if ($r->dealer->user?->status === 'pending_activation')<x-badge tone="warning">Şifre bekliyor</x-badge>@elseif ($r->dealer->user?->status === 'disabled')<x-badge>Kapalı</x-badge>@else<x-badge tone="success">Etkin</x-badge>@endif</td>
                                    <td><x-badge :tone="['active' => 'success', 'suspended' => 'warning', 'closed' => 'neutral'][$r->status]">{{ DealerRelationship::STATUSES[$r->status] }}</x-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($relationships->hasPages())<div class="border-t border-line p-4">{{ $relationships->links() }}</div>@endif
            @endif
        </x-card>
    @endif
</x-layouts.panel>
