@php use App\Domain\Store\Catalog\ProductImporter; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Excel ile ürün aktarma">
    <x-page-header title="Excel ile ürün aktarma" subtitle="Yeni ürünler ekleyin veya stok koduna göre mevcut ürünlerin fiyat ve bilgilerini güncelleyin.">
        <x-slot:breadcrumb><a href="{{ route('store_admin.products.index') }}" class="hover:underline">Ürünler</a></x-slot:breadcrumb>
        <x-button variant="secondary" icon="download" :href="route('store_admin.products.import.template')">Şablonu indir</x-button>
    </x-page-header>

    @if ($result)
        <x-card class="mb-6" :title="$result['failed'] ? 'Aktarım tamamlandı, bazı satırlar aktarılamadı' : 'Aktarım tamamlandı'">
            <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                <div><dt class="text-muted">Satır</dt><dd class="text-2xl font-semibold tabular-nums">{{ $result['total'] }}</dd></div>
                <div><dt class="text-muted">Eklenen</dt><dd class="text-2xl font-semibold text-success tabular-nums">{{ $result['created'] }}</dd></div>
                <div><dt class="text-muted">Güncellenen</dt><dd class="text-2xl font-semibold text-primary tabular-nums">{{ $result['updated'] }}</dd></div>
                <div><dt class="text-muted">Hatalı</dt><dd @class(['text-2xl font-semibold tabular-nums', 'text-danger' => $result['failed']])>{{ $result['failed'] }}</dd></div>
            </dl>
            @if ($result['errors'])
                <div class="mt-5 max-h-96 overflow-auto rounded-lg border border-line">
                    <table class="table-base">
                        <thead><tr><th class="w-20">Satır</th><th>Sorun</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($result['errors'] as $error)
                                <tr><td class="tabular-nums">{{ $error['row'] }}</td><td>{{ $error['message'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-muted">Hatalı satırları düzeltip dosyayı yeniden yükleyebilirsiniz; doğru satırlar tekrar yüklendiğinde güncellenir, çoğalmaz.</p>
            @endif
        </x-card>
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        <x-card title="Dosya yükle" class="lg:col-span-2">
            <form method="POST" action="{{ route('store_admin.products.import.store') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                <div>
                    <input type="file" name="file" accept=".xlsx,.csv" required aria-label="Excel veya CSV dosyası"
                        class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">
                    @error('file')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                    <p class="mt-1.5 text-xs text-muted">.xlsx veya .csv; en fazla {{ ProductImporter::MAX_ROWS }} satır, 10 MB.</p>
                </div>
                <x-toggle name="create_missing" label="Eksik kategori ve markaları oluştur" :checked="true" hint="Kapalıysa dosyadaki kategori ve markalar mağazada önceden tanımlı olmalı." />
                <x-button icon="upload">Aktar</x-button>
            </form>
        </x-card>

        <x-card title="Sütunlar nasıl doldurulur?" class="lg:col-span-3">
            <ul class="space-y-2 text-sm text-slate-700">
                <li><b>Stok Kodu</b> ve <b>Ürün Adı</b> zorunludur. Stok kodu mağazada varsa o ürün güncellenir, yoksa yeni ürün eklenir.</li>
                <li><b>Satış Fiyatı</b> KDV dahildir (ör. 24,90). <b>KDV Oranı</b>: 0, 1, 10 veya 20.</li>
                <li><b>Seçenekli ürünler</b> (renk, boyut): her seçenek ayrı satırdır. Aynı ürünün satırlarına aynı <b>Ürün Grubu</b> değerini yazın (ör. ilk seçeneğin stok kodu), <b>Seçenek Adı</b>na "Renk", <b>Seçenek</b>e "Mavi" gibi değer girin.</li>
                <li><b>Kategori</b> alt kategorilerle yazılabilir: <span class="font-mono text-xs">Kalemler &gt; Jel Kalem</span>.</li>
                <li><b>Yayında</b>: "Evet" yazılmazsa yeni ürünler taslak olarak eklenir; görselleri ekledikten sonra yayına alabilirsiniz.</li>
                <li>Güncellemede dosyada olmayan sütunlar değişmez. Görseller panelden eklenir.</li>
            </ul>
        </x-card>
    </div>
</x-layouts.panel>
