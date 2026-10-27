@php
    use App\Domain\Store\Reports\ReportCell;
    $allColumns = $report->columns();
    $visible = array_replace(array_flip($prepared['columns']), array_intersect_key($allColumns, array_flip($prepared['columns'])));
    $filters = $report->filters();
    $basic = array_filter($filters, fn ($f) => ! ($f['advanced'] ?? false));
    $advanced = array_filter($filters, fn ($f) => $f['advanced'] ?? false);
    $exportQuery = collect($query)->except('page')->all();
    $sortUrl = fn ($col) => request()->fullUrlWithQuery(['sort' => $col, 'dir' => $prepared['sort'] === $col && $prepared['dir'] === 'asc' ? 'desc' : 'asc', 'page' => null]);
    // Üstteki özet kartları: kayıt sayısı ve görünen toplam sütunlarının sondan üçü (en önemlileri sonda: tutar, net)
    $cards = collect($visible)->filter(fn ($c, $k) => isset($totals[$k]))->slice(-3);
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" :title="$report->title()">
    <x-page-header :title="$report->title()" :subtitle="$report->description()">
        <x-slot:breadcrumb><a href="{{ route('store_admin.reports.index') }}" class="hover:underline">Raporlar</a> › {{ $report->group() }}</x-slot:breadcrumb>
        <x-button variant="secondary" icon="download" :href="route('store_admin.reports.export', [$report->key(), 'xlsx'] + $exportQuery)">Excel</x-button>
        <x-button variant="secondary" icon="download" :href="route('store_admin.reports.export', [$report->key(), 'pdf'] + $exportQuery)">PDF</x-button>
    </x-page-header>

    @error('report')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    {{-- Kayıtlı filtreler: tek tıkla aç; mevcut filtreleri adıyla kaydet --}}
    <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        @foreach ($saved as $s)
            <span class="inline-flex items-center gap-1 rounded-full bg-white py-1 pr-1 pl-3 ring-1 ring-line">
                <a href="{{ route('store_admin.reports.show', [$report->key()] + $s->filters) }}" class="font-medium text-primary hover:underline">{{ $s->name }}</a>
                <form method="POST" action="{{ route('store_admin.reports.saved.destroy', $s->id) }}" data-confirm="&quot;{{ $s->name }}&quot; silinsin mi?">@csrf @method('DELETE')
                    <button class="rounded-full p-1 text-muted hover:bg-slate-100 hover:text-danger" aria-label="Sil"><x-icon name="x" class="size-3.5" /></button></form>
            </span>
        @endforeach
        <details class="relative">
            <summary class="cursor-pointer list-none rounded-full px-3 py-1.5 font-medium text-primary ring-1 ring-line hover:ring-primary">+ Bu filtreleri kaydet</summary>
            <form method="POST" action="{{ route('store_admin.reports.save', $report->key()) }}" class="absolute z-10 mt-2 flex w-72 gap-2 rounded-xl border border-line bg-white p-3 shadow-lg">
                @csrf
                <input type="hidden" name="query" value="{{ json_encode($exportQuery, JSON_UNESCAPED_UNICODE) }}">
                <label class="sr-only" for="kayit_adi">Ad</label>
                <input id="kayit_adi" name="name" required maxlength="80" placeholder="ör. Bu ayın kargo siparişleri" class="field-input min-w-0 flex-1">
                <x-button size="sm">Kaydet</x-button>
            </form>
        </details>
        @error('name')<span class="text-danger">{{ $message }}</span>@enderror
    </div>

    @if ($rows->total() > 0 && $cards->isNotEmpty())
        <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-line bg-white px-4 py-3">
                <p class="text-xs text-muted">Kayıt</p>
                <p class="mt-0.5 text-xl font-bold tabular-nums">{{ number_format($rows->total(), 0, ',', '.') }}</p>
            </div>
            @foreach ($cards as $key => $col)
                <div class="rounded-xl border border-l-4 border-line border-l-primary bg-white px-4 py-3">
                    <p class="text-xs text-muted">{{ $col['label'] }} (toplam)</p>
                    <p class="mt-0.5 text-xl font-bold tabular-nums">{{ ReportCell::display($col['type'], $totals[$key]) }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <x-card :padding="false">
        <form method="GET" class="border-b border-line p-4" x-data="{ adv: {{ collect($advanced)->keys()->contains(fn ($k) => filled($query[$k] ?? null)) ? 'true' : 'false' }}, cols: false }">
            <input type="hidden" name="sort" value="{{ $prepared['sort'] }}">
            <input type="hidden" name="dir" value="{{ $prepared['dir'] }}">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                @foreach ($basic as $name => $f)
                    @include('store.admin.reports._filter', ['name' => $name, 'f' => $f, 'value' => $prepared['filters'][$name] ?? ''])
                @endforeach
            </div>
            @if ($advanced)
                <div x-show="adv" x-cloak class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                    @foreach ($advanced as $name => $f)
                        @include('store.admin.reports._filter', ['name' => $name, 'f' => $f, 'value' => $prepared['filters'][$name] ?? ''])
                    @endforeach
                </div>
            @endif
            <div x-show="cols" x-cloak class="mt-3 flex flex-wrap gap-x-5 gap-y-2 rounded-lg bg-slate-50 p-3">
                @foreach ($allColumns as $key => $col)
                    <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" name="cols[]" value="{{ $key }}" @checked(in_array($key, $prepared['columns'], true)) class="size-4 rounded"> {{ $col['label'] }}</label>
                @endforeach
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <x-button variant="secondary" icon="search">Uygula</x-button>
                @if ($advanced)<button type="button" class="text-sm font-medium text-primary hover:underline" @click="adv = !adv" x-text="adv ? 'Gelişmiş filtreleri gizle' : 'Gelişmiş filtreler'">Gelişmiş filtreler</button>@endif
                <button type="button" class="text-sm font-medium text-primary hover:underline" @click="cols = !cols">Sütunlar</button>
                <a href="{{ route('store_admin.reports.show', $report->key()) }}" class="text-sm text-muted hover:underline">Sıfırla</a>
            </div>
        </form>

        @if ($rows->isEmpty())
            <x-empty-state icon="file" title="Bu filtrelerle kayıt yok" text="Tarih aralığını genişletmeyi veya filtreleri değiştirmeyi deneyin." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            @foreach ($visible as $key => $col)
                                <th @class(['text-right' => ReportCell::isNumeric($col['type']), 'whitespace-nowrap'])>
                                    @if ($col['sortable'] ?? true)
                                        <a href="{{ $sortUrl($key) }}" class="hover:text-ink">{{ $col['label'] }}@if ($prepared['sort'] === $key) {{ $prepared['dir'] === 'asc' ? '↑' : '↓' }}@endif</a>
                                    @else{{ $col['label'] }}@endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($visible as $key => $col)
                                    <td @class(['whitespace-nowrap', 'tabular-nums text-right' => ReportCell::isNumeric($col['type']), 'text-muted' => in_array($col['type'], ['date', 'datetime'], true)])>{{ ReportCell::display($col['type'], $row->{$key} ?? null) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($totals)
                        <tfoot class="border-t-2 border-line bg-slate-50 font-semibold">
                            <tr>
                                @foreach ($visible as $key => $col)
                                    <td class="px-4 py-3 @if (isset($totals[$key])) text-right tabular-nums whitespace-nowrap @endif">
                                        {{ isset($totals[$key]) ? ReportCell::display($col['type'], $totals[$key]) : ($loop->first ? 'Toplam' : '') }}
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line p-4 text-sm text-muted">
                <span>{{ number_format($rows->total(), 0, ',', '.') }} kayıt · Tutarlar KDV dahildir.</span>
                {{ $rows->links() }}
            </div>
        @endif
    </x-card>
</x-layouts.panel>
