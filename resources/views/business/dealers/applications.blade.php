@php use App\Models\DealerApplication; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Bayilik Başvuruları">
    <x-page-header title="Bayilik Başvuruları" subtitle="Başvuru formunuzdan gelen talepler.">
        <div x-data="{ copied: false }" class="flex items-center gap-2">
            <input readonly value="{{ route('public.application', $currentBusiness->slug) }}" class="field-input w-72 font-mono text-xs" x-ref="url" aria-label="Başvuru formu adresi">
            <x-button type="button" variant="secondary" icon="copy" @click="navigator.clipboard.writeText($refs.url.value); copied = true"><span x-text="copied ? 'Kopyalandı' : 'Form linki'">Form linki</span></x-button>
        </div>
    </x-page-header>

    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Durum filtresi">
        @php $openCount = collect(DealerApplication::OPEN)->sum(fn ($s) => $counts[$s] ?? 0); @endphp
        @foreach (['open' => 'Açık başvurular', 'approved' => 'Onaylanan', 'rejected' => 'Reddedilen', 'cancelled' => 'İptal'] as $key => $label)
            <a href="{{ route('business.applications.index', ['status' => $key]) }}" @class([
                'rounded-full px-3 py-1.5 text-sm font-medium',
                'bg-primary text-white' => $status === $key,
                'bg-white text-muted ring-1 ring-line hover:text-ink' => $status !== $key,
            ])>{{ $label }} <span class="tabular-nums opacity-75">{{ $key === 'open' ? $openCount : ($counts[$key] ?? 0) }}</span></a>
        @endforeach
    </nav>

    <x-card :padding="false">
        <form method="GET" class="flex gap-2 border-b border-line p-4">
            <input type="hidden" name="status" value="{{ $status }}">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="field-input max-w-sm" placeholder="Unvan, yetkili veya e-posta" aria-label="Ara">
            <x-button variant="secondary" icon="search">Ara</x-button>
        </form>

        @if ($applications->isEmpty())
            <x-empty-state icon="inbox" title="{{ $status === 'open' ? 'Bekleyen başvuru yok' : 'Kayıt yok' }}"
                text="Başvuru formu bağlantısını müşterilerinizle paylaşın; gelen başvurular burada listelenir." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>İşletme</th><th class="hidden md:table-cell">İl / ilçe</th><th class="hidden sm:table-cell">Tarih</th><th>Durum</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($applications as $application)
                            <tr>
                                <td>
                                    <a href="{{ route('business.applications.show', $application) }}" class="font-medium hover:text-primary">{{ $application->legal_name }}</a>
                                    <p class="text-xs text-muted">{{ $application->contact_name }} · {{ $application->email }}</p>
                                </td>
                                <td class="hidden text-muted md:table-cell">{{ $application->city }} / {{ $application->district }}</td>
                                <td class="hidden text-muted tabular-nums sm:table-cell">{{ $application->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                                <td><x-badge :tone="$application->statusTone()">{{ DealerApplication::STATUSES[$application->status] }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($applications->hasPages())<div class="border-t border-line p-4">{{ $applications->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
