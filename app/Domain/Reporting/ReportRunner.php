<?php

namespace App\Domain\Reporting;

use App\Domain\Support\Decimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;

/**
 * Raporu çalıştırır: filtre doğrulama, güvenli sıralama (yalnızca tanımlı sütunlar), sayfalama,
 * toplamlar ve dışa aktarma için parça parça okuma. Ekran ve dosya aynı prepare() çıktısını kullanır.
 */
class ReportRunner
{
    public const PAGE_SIZE = 50;

    /**
     * @return array{filters: array, sort: string, dir: string, columns: string[]}
     */
    public function prepare(Report $report, array $input, ReportContext $context): array
    {
        $rules = [];
        foreach ($report->filters($context) as $name => $f) {
            $rules[$name] = $f['rules'] ?? match ($f['type']) {
                'date' => ['nullable', 'date'],
                'number' => ['nullable', 'integer'],
                'boolean' => ['nullable', 'boolean'],
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
        foreach ($report->filters($context) as $name => $f) {
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

    public function paginate(Report $report, array $prepared, ReportContext $context, int $page = 1): LengthAwarePaginator
    {
        $paginator = $this->sorted($report, $prepared, $context)->paginate(self::PAGE_SIZE, ['*'], 'page', $page);
        $paginator->setCollection($report->transform($paginator->getCollection(), $prepared['filters'], $context));

        return $paginator;
    }

    /** Dışa aktarma için tüm satırlar, bellek sabit kalacak şekilde parça parça. */
    public function rows(Report $report, array $prepared, ReportContext $context): LazyCollection
    {
        $query = $this->sorted($report, $prepared, $context);

        return LazyCollection::make(function () use ($query, $report, $prepared, $context) {
            $page = 1;
            do {
                $chunk = (clone $query)->forPage($page++, 1000)->get();
                foreach ($report->transform($chunk, $prepared['filters'], $context) as $row) {
                    yield $row;
                }
            } while ($chunk->count() === 1000);
        });
    }

    public function count(Report $report, array $prepared, ReportContext $context): int
    {
        return DB::query()->fromSub($this->base($report, $prepared, $context), 'r')->count();
    }

    /** @return array<string, string> toplanabilir sütunların genel toplamı (tüm sayfalar) */
    public function totals(Report $report, array $prepared, ReportContext $context): array
    {
        $totalCols = array_keys(array_filter($report->columns(), fn ($c) => $c['total'] ?? false));
        if ($totalCols === []) {
            return [];
        }

        if ($report->totalsFromRows()) {
            $sums = array_fill_keys($totalCols, '0');
            foreach ($this->rows($report, $prepared, $context) as $row) {
                foreach ($totalCols as $c) {
                    $sums[$c] = bcadd($sums[$c], (string) ($row->{$c} ?? '0'), 4);
                }
            }

            return $sums;
        }

        $select = implode(', ', array_map(fn ($c) => "COALESCE(SUM(r.`{$c}`), 0) as `{$c}`", $totalCols));
        $result = (array) DB::query()->fromSub($this->base($report, $prepared, $context), 'r')->selectRaw($select)->first();

        return array_map(fn ($v) => Decimal::parse((string) $v, 4) ?? '0', $result);
    }

    private function base(Report $report, array $prepared, ReportContext $context)
    {
        $query = $report->query($prepared['filters'], $context);

        return $query instanceof EloquentBuilder ? $query->toBase()->cloneWithout(['orders']) : $query->cloneWithout(['orders']);
    }

    private function sorted(Report $report, array $prepared, ReportContext $context)
    {
        $query = $report->query($prepared['filters'], $context);
        // İşletme kapsamı (global scope) uygulanmış düz sorgu; satırlar model değil, basit nesnedir.
        $query = $query instanceof EloquentBuilder ? $query->toBase() : $query;
        // Sıralama yalnızca tanımlı sütun adlarıyla (doğrulamadan geçmiş) yapılır.
        $query->reorder()->orderBy($prepared['sort'], $prepared['dir']);

        return $query;
    }
}
