{{--
    Grafik kartı: Chart.js tuvali + aynı verinin tablo görünümü (erişilebilirlik ve düşük kontrastlı renkler için).
    $config: charts.js'teki kurucuya giden yapı; $rows: [[etiket, değer, (önceki)], ...]; $headers: tablo başlıkları.
--}}
@props(['title', 'description' => null, 'config', 'rows' => [], 'headers' => [], 'height' => 'h-64', 'empty' => false])
<x-card :title="$title" :description="$description" {{ $attributes }} x-data="{ table: false }">
    <x-slot:actions>
        <button type="button" class="text-xs font-medium text-primary hover:underline" x-on:click="table = ! table" x-text="table ? 'Grafik' : 'Tablo'">Tablo</button>
    </x-slot:actions>
    @if ($empty)
        <p class="py-10 text-center text-sm text-muted">Seçilen dönemde veri yok.</p>
    @else
        <div class="{{ $height }}" x-show="! table" role="img" aria-label="{{ $title }}">
            <canvas data-chart='@json($config)'></canvas>
        </div>
        <div x-show="table" x-cloak class="max-h-72 overflow-auto">
            <table class="table-base">
                <thead><tr>@foreach ($headers as $i => $h)<th @class(['text-right' => $i > 0])>{{ $h }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($rows as $row)
                        <tr>@foreach ($row as $i => $cell)<td @class(['text-right tabular-nums' => $i > 0])>{{ $cell }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>
