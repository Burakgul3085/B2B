<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Ürün içe aktarma">
    <x-page-header title="Ürün içe aktarma" subtitle="Excel (.xlsx) veya CSV dosyasıyla ürünleri toplu ekleyin veya güncelleyin.">
        <x-slot:breadcrumb><a href="{{ route('business.products.index') }}" class="hover:underline">Ürünler</a></x-slot:breadcrumb>
        <x-button variant="secondary" icon="download" :href="route('business.products.import.template')">Şablonu indir</x-button>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Dosya yükle">
                <form method="POST" action="{{ route('business.products.import.store') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <div>
                        <label for="file" class="mb-1.5 block text-sm font-medium">Dosya <span class="text-danger">*</span></label>
                        <input id="file" type="file" name="file" accept=".xlsx,.csv" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
                        @error('file')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                        <p class="mt-1.5 text-xs text-muted">En fazla 10 MB ve 5.000 satır. İlk satır sütun başlıkları olmalıdır.</p>
                    </div>
                    <x-toggle name="create_missing" label="Eksik kategori ve markaları otomatik oluştur" :checked="true" />
                    <x-button icon="upload">İçe aktar</x-button>
                </form>
            </x-card>

            <x-card title="Son içe aktarmalar" :padding="false">
                @if ($jobs->isEmpty())
                    <p class="p-5 text-sm text-muted">Henüz içe aktarma yapılmadı.</p>
                @else
                    <table class="table-base">
                        <thead><tr><th>Dosya</th><th>Tarih</th><th class="text-right">Eklenen</th><th class="text-right">Güncellenen</th><th class="text-right">Hatalı</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($jobs as $job)
                                <tr>
                                    <td><a href="{{ route('business.products.import.show', $job) }}" class="font-medium text-primary hover:underline">{{ $job->original_name }}</a></td>
                                    <td class="text-muted tabular-nums">{{ $job->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                                    <td class="text-right tabular-nums">{{ $job->created_rows }}</td>
                                    <td class="text-right tabular-nums">{{ $job->updated_rows }}</td>
                                    <td class="text-right tabular-nums @if ($job->failed_rows) text-danger font-medium @endif">{{ $job->status === 'failed' ? 'Dosya hatası' : $job->failed_rows }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        </div>

        <x-card title="Nasıl çalışır?">
            <ul class="space-y-3 text-sm text-slate-700">
                <li><b>Ürün Kodu</b> sistemde varsa ürün <b>güncellenir</b>, yoksa <b>yeni ürün</b> oluşturulur.</li>
                <li>Güncellemede yalnızca dosyada dolu olan sütunlar değişir.</li>
                <li>Yeni ürün için <b>Ürün Adı</b>, <b>Liste Fiyatı</b> ve <b>KDV Oranı</b> zorunludur.</li>
                <li>Kategori: <code class="rounded bg-slate-100 px-1">Ana &gt; Alt</code> biçiminde yazılabilir.</li>
                <li>Fiyatlar <code class="rounded bg-slate-100 px-1">8,50</code> veya <code class="rounded bg-slate-100 px-1">8.50</code> olabilir.</li>
                <li><b>Başlangıç Stoğu</b> yalnızca yeni ürünlerde varsayılan sevk deposuna yazılır.</li>
                <li>Hatalı satırlar atlanır, diğerleri kaydedilir; hatalar satır numarasıyla listelenir.</li>
            </ul>
            <p class="mt-4 text-xs text-muted">Sütunlar: {{ implode(', ', $columns) }}</p>
        </x-card>
    </div>
</x-layouts.panel>
