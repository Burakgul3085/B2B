<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Yasal Metinler">
    <x-page-header title="Yasal Metinler" subtitle="Siparişte müşteriye onaylatılan metinler. Her düzenleme yeni sürüm oluşturur; her siparişte onaylanan metin ve sürümü saklanır." />

    <p class="mb-5 rounded-xl border border-amber-200 bg-warning-soft px-4 py-3 text-sm text-amber-900">
        Hazır metinler örnek şablondur. Mağaza yayına çıkmadan önce hukukçunuz veya mali müşavirinizce gözden geçirilmeli; satıcı bilgileri <a href="{{ route('store_admin.settings.edit') }}" class="font-medium underline">Mağaza Ayarları</a>'ndan doldurulur.
    </p>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($documents as $key => $doc)
            <x-card :title="$doc['current']->title">
                <p class="text-sm text-muted">Güncel sürüm {{ $doc['current']->version }} · {{ $doc['current']->created_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} · toplam {{ $doc['versions'] }} sürüm</p>
                <p class="mt-2 text-sm">@if ($doc['reviewed'])<x-badge tone="success">Gözden geçirildi</x-badge>@else<x-badge tone="warning">Gözden geçirilmedi</x-badge>@endif</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <x-button :href="route('store_admin.legal.edit', $key)" icon="pencil" size="sm">Düzenle</x-button>
                    <x-button :href="route('store.legal', $key)" variant="secondary" size="sm" target="_blank" rel="noopener">Mağazada gör</x-button>
                    @unless ($doc['reviewed'])
                        <form method="POST" action="{{ route('store_admin.legal.reviewed', $key) }}" data-confirm="Metin hukukçunuzca kontrol edildi ve değiştirilmeden kullanılacak mı?">
                            @csrf
                            <x-button variant="secondary" size="sm" icon="check">Gözden geçirildi olarak işaretle</x-button>
                        </form>
                    @endunless
                </div>
            </x-card>
        @endforeach
    </div>
</x-layouts.panel>
