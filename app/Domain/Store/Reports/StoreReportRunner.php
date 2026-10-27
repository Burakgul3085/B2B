<?php

namespace App\Domain\Store\Reports;

use App\Domain\Support\Decimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;

/**
 * Raporu çalıştırır: filtre doğrulama, güvenli sıralama (yalnızca tanımlı sütunlar), sayfalama, toplamlar ve
 * dışa aktarma için parça parça okuma. Ekran ve dosya aynı prepare() çıktısını kullanır.
 */
class StoreReportRunner
{
    public const PAGE_SIZE = 50;

    /** @return array{filters: array, sort: string, dir: string, columns: string[]} */
    public function prepare(StoreReport $report, array $input): array
    {
        $rules = [];
        foreach ($report->filters() as $name => $f) {
            $rules[$name] = $f['rules'] ?? match ($f['type']) {
                'date' => ['nullable', 'date'],
                'number' => ['nullable', 'integer'],
                'select' => ['nullable', 'in:'.implode(',', array_map('strval', array_keys($f['options'] ?? [])))],
                default => ['nullable', 'string', 'max:100'],
            };
        }
        $columns = $report->columns();
        $rules += [
            'sort' => ['nullable', 'in:'.implode(',', array_keys(array_filter($columns, fn ($c) => $c['sortable'] ?? true)))],
            'dir' => ['nullable', 'in:asc,desc'],
            'cols' => ['nullable', 'array'],
            'cols.*' => ['in:'.implode(',', array_keys($columns))],
        ];
        $data = Validator::make($input, $rules)->validate();

        $filters = [];
        foreach ($report->filters() as $name => $f) {
            $value = $data[$name] ?? null;
            $filters[$name] = ($value === null || $value === '') ? ($f['default'] ?? null) : $value;
        }
        if (! empty($filters['from']) && ! empty($filters['to']) && $filters['from'] > $filters['to']) {
            [$filters['from'], $filters['to']] = [$filters['to'], $filters['from']];
        }

        [$defaultSort, $defaultDir] = $report->defaultSort();
        $visible = ! empty($data['cols'])
            ? array_values(array_intersect(array_keys($columns), $data['cols']))
            : array_keys(array_filter($columns, fn ($c) => $c['default'] ?? true));

        return [
            'filters' => $filters,
            'sort' => $data['sort'] ?? $defaultSort,
            'dir' => $data['dir'] ?? $defaultDir,
            'columns' => $visible ?: array_keys($columns),
        ];
    }

    public function paginate(StoreReport $report, array $prepared, int $page = 1): LengthAwarePaginator
    {
        $paginator = $this->sorted($report, $prepared)->paginate(self::PAGE_SIZE, ['*'], 'page', $page);
        $paginator->setCollection($report->transform($paginator->getCollection(), $prepared['filters']));

        return $paginator;
    }

    /** Dışa aktarma için tüm satırlar, bellek sabit kalacak şekilde parça parça. */
    public function rows(StoreReport $report, array $prepared): LazyCollection
    {
        $query = $this->sorted($report, $prepared);

        return LazyCollection::make(function () use ($query, $report, $prepared) {
            $page = 1;
            do {
                $chunk = (clone $query)->forPage($page++, 1000)->get();
                foreach ($report->transform($chunk, $prepared['filters']) as $row) {
                    yield $row;
                }
            } while ($chunk->count() === 1000);
        });
    }

    public function count(StoreReport $report, array $prepared): int
    {
        return DB::query()->fromSub($this->base($report, $prepared), 'r')->count();
    }

    /** @return array<string, string> toplanabilir sütunların genel toplamı (tüm sayfalar) */
    public function totals(StoreReport $report, array $prepared): array
    {
        $totalCols = array_keys(array_filter($report->columns(), fn ($c) => $c['total'] ?? false));
        if ($totalCols === []) {
            return [];
        }
        $select = implode(', ', array_map(fn ($c) => "COALESCE(SUM(r.`{$c}`), 0) as `{$c}`", $totalCols));
        $result = (array) DB::query()->fromSub($this->base($report, $prepared), 'r')->selectRaw($select)->first();

        return array_map(fn ($v) => Decimal::parse((string) $v, 4) ?? '0', $result);
    }

    /** Dosyalarda ve ekranda gösterilen filtre özeti: etiket => okunur değer. @return array<string, string> */
    public function summary(StoreReport $report, array $prepared): array
    {
        $summary = [];
        foreach ($report->filters() as $name => $f) {
            $value = $prepared['filters'][$name] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $summary[$f['label']] = match ($f['type']) {
                'select' => (string) ($f['options'][$value] ?? $value),
                'date' => Carbon::parse($value)->format('d.m.Y'),
                default => (string) $value,
            };
        }

        return $summary;
    }

    private function base(StoreReport $report, array $prepared)
    {
        $query = $report->query($prepared['filters']);

        return $query instanceof EloquentBuilder ? $query->toBase()->cloneWithout(['orders']) : $query->cloneWithout(['orders']);
    }

    private function sorted(StoreReport $report, array $prepared)
    {
        $query = $report->query($prepared['filters']);
        $query = $query instanceof EloquentBuilder ? $query->toBase() : $query;
        // Sıralama yalnızca tanımlı sütun adlarıyla (doğrulamadan geçmiş) yapılır; eşitlikte sıra sabit kalsın.
        $query->reorder()->orderBy($prepared['sort'], $prepared['dir']);

        return $query;
    }
}
