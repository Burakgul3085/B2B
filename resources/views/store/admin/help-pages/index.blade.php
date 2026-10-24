<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Yardım Sayfaları">
    <x-page-header title="Yardım Sayfaları" subtitle="Alt bilgideki Yardım ve Kurumsal bağlantılarının açtığı sayfalar. Her sayfanın başlığını, giriş metnini ve içeriğini buradan düzenleyin; yayından kaldırılan sayfanın bağlantısı her yerden kendiliğinden gizlenir." />

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($pages as $page)
            <x-card>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold">{{ $page['content']['title'] }}</p>
                        <p class="mt-0.5 font-mono text-xs text-muted">/{{ $page['path'] }}</p>
                    </div>
                    @if ($page['published'])<x-badge tone="success">Yayında</x-badge>@else<x-badge>Kapalı</x-badge>@endif
                </div>
                <p class="mt-3 text-sm text-muted">{{ $page['note'] }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-button size="sm" :href="route('store_admin.help-pages.edit', $page['key'])" icon="pencil">Düzenle</x-button>
                    @if ($page['published'])<x-button size="sm" variant="secondary" :href="route('store.help.'.$page['key'])" icon="eye" target="_blank">Mağazada gör</x-button>@endif
                </div>
            </x-card>
        @endforeach
    </div>
</x-layouts.panel>
