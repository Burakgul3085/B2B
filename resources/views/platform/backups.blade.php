<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" title="Yedekler">
    <x-page-header title="Yedekler" :subtitle="'Veritabanı ve yüklenen dosyalar tek zip dosyasında. Her gece 03:30\'da otomatik alınır; son '.$keep.' yedek saklanır.'">
        <form method="POST" action="{{ route('platform.backups.store') }}">@csrf<x-button icon="refresh">Şimdi yedek al</x-button></form>
    </x-page-header>

    <div class="mb-6 rounded-xl border border-amber-200 bg-warning-soft px-5 py-4 text-sm text-amber-900">
        Yedekler sunucunun kendi diskindedir. Sunucu kaybına karşı düzenli olarak bilgisayarınıza indirin veya ayrı bir depolamaya kopyalayın.
        Geri yükleme: <span class="font-mono text-xs">veritabani.sql.gz</span> açılıp MySQL'e yüklenir, <span class="font-mono text-xs">dosyalar/</span> klasörü <span class="font-mono text-xs">storage/app/private</span> altına kopyalanır.
    </div>

    <x-card :padding="false">
        @if ($backups === [])
            <x-empty-state icon="archive" title="Henüz yedek yok" text="İlk yedeği almak için “Şimdi yedek al” düğmesini kullanın." />
        @else
            <table class="table-base">
                <thead><tr><th>Yedek</th><th>Tarih</th><th class="text-right">Boyut</th><th></th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($backups as $b)
                        <tr>
                            <td class="font-mono text-sm">{{ $b['name'] }}</td>
                            <td class="text-sm text-muted">{{ $b['created_at']->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                            <td class="text-right tabular-nums">{{ number_format($b['size'] / 1048576, 1, ',', '.') }} MB</td>
                            <td class="text-right">
                                <div class="flex justify-end gap-3">
                                    <a href="{{ route('platform.backups.download', $b['name']) }}" class="text-sm font-medium text-primary hover:underline">İndir</a>
                                    <form method="POST" action="{{ route('platform.backups.destroy', $b['name']) }}" data-confirm="{{ $b['name'] }} silinsin mi?">@csrf @method('DELETE')<button class="text-sm text-muted hover:text-danger">Sil</button></form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
</x-layouts.panel>
