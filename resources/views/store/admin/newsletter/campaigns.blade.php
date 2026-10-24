<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Toplu E-posta">
    <x-page-header title="Toplu E-posta" subtitle="E-bülten abonelerinize kampanya, yeni ürün ve duyuru e-postası gönderin. Önce taslak yazıp kendinize deneme gönderin; “Gönder” dediğinizde e-postalar arka planda parti parti gider.">
        <x-button :href="route('store_admin.campaigns.create')" icon="plus">Yeni e-posta</x-button>
    </x-page-header>

    <p class="mb-4 text-sm text-muted">Şu an <span class="font-semibold text-ink">{{ number_format($activeCount, 0, ',', '.') }}</span> onaylı abone var. <a href="{{ route('store_admin.newsletter.index') }}" class="font-medium text-primary hover:underline">Aboneleri gör</a></p>

    <x-card :padding="false">
        @if ($campaigns->isEmpty())
            <x-empty-state icon="mail" title="Henüz e-posta yok" text="İlk kampanya e-postanızı hazırlayın: ör. “Okula dönüş fırsatları başladı”." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Konu</th><th>Durum</th><th class="text-right">Gönderilen</th><th>Tarih</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($campaigns as $campaign)
                            <tr>
                                <td><a href="{{ route('store_admin.campaigns.edit', $campaign) }}" class="font-semibold hover:text-primary">{{ $campaign->subject }}</a></td>
                                <td><x-badge :tone="$campaign->statusTone()">{{ $campaign->statusLabel() }}</x-badge></td>
                                <td class="text-right text-sm tabular-nums">@if (! $campaign->isDraft()){{ $campaign->sent_count }} / {{ $campaign->recipients_count }}@if ($campaign->failed_count) <span class="text-danger">({{ $campaign->failed_count }} hata)</span>@endif @else — @endif</td>
                                <td class="text-sm text-muted">{{ ($campaign->started_at ?? $campaign->updated_at)->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($campaigns->hasPages())<div class="border-t border-line p-4">{{ $campaigns->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
