<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="İçe aktarma sonucu">
    <x-page-header :title="$job->original_name" subtitle="İçe aktarma sonucu">
        <x-slot:breadcrumb><a href="{{ route('business.products.import') }}" class="hover:underline">İçe aktarma</a></x-slot:breadcrumb>
        <x-button variant="secondary" :href="route('business.products.index')">Ürünlere git</x-button>
    </x-page-header>

    @if ($job->status === 'failed')
        <div role="alert" class="mb-6 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">Dosya işlenemedi. Ayrıntı aşağıda.</div>
    @else
        <div class="mb-6 grid gap-4 sm:grid-cols-4">
            <x-kpi label="İşlenen satır" :value="$job->total_rows" />
            <x-kpi label="Eklenen ürün" :value="$job->created_rows" />
            <x-kpi label="Güncellenen ürün" :value="$job->updated_rows" />
            <x-kpi label="Hatalı satır" :value="$job->failed_rows" />
        </div>
    @endif

    <x-card title="Hatalar" :padding="false">
        @if ($importErrors->isEmpty())
            <x-empty-state icon="check-circle" title="Hata yok" text="Tüm satırlar başarıyla işlendi." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th class="w-20">Satır</th><th class="w-44">Sütun</th><th>Hata</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($importErrors as $error)
                            <tr>
                                <td class="tabular-nums">{{ $error->row_no ?: '—' }}</td>
                                <td class="text-muted">{{ $error->column ?? '—' }}</td>
                                <td>{{ $error->message }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.panel>
