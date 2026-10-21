<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Sayfalar">
    <x-page-header title="Sayfalar" subtitle="Hakkımızda, İletişim, Teslimat ve İade koşulları gibi sabit sayfalar. Alt bilgide bağlantıları görünür.">
        <x-button icon="plus" :href="route('store_admin.pages.create')">Yeni sayfa</x-button>
    </x-page-header>

    <x-card :padding="false">
        @if ($pages->isEmpty())
            <x-empty-state icon="file" title="Henüz sayfa yok" text="Örneğin Hakkımızda ve İletişim sayfalarını ekleyin. Yasal metinler (mesafeli satış sözleşmesi vb.) satış öncesinde eklenecek." />
        @else
            <table class="table-base">
                <thead><tr><th>Başlık</th><th>Adres</th><th>Alt bilgide</th><th>Durum</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($pages as $page)
                        <tr>
                            <td><a href="{{ route('store_admin.pages.edit', $page) }}" class="font-medium hover:text-primary">{{ $page->title }}</a></td>
                            <td class="text-sm text-muted">/sayfa/{{ $page->slug }}</td>
                            <td>{{ $page->show_in_footer ? 'Evet' : 'Hayır' }}</td>
                            <td>@if ($page->is_active)<x-badge tone="success">Yayında</x-badge>@else<x-badge>Pasif</x-badge>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
</x-layouts.panel>
