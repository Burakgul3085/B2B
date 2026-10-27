<?php

namespace App\Domain\Store\Reports;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Mağaza rapor tanımı. Ekran, Excel ve PDF aynı tanımı (filtreler, sorgu, sütunlar, toplamlar) kullanır; böylece
 * ekrandaki rapor ile indirilen dosya aynı sonucu verir. Sorgu, sütun anahtarlarıyla aynı adlı alanları seçmelidir.
 * Tarihler Türkiye saatine göre gün olarak seçilir; tutarlar KDV dahildir.
 */
abstract class StoreReport
{
    /** money | qty | int | text | date | datetime | percent */
    public const TYPES = ['money', 'qty', 'int', 'text', 'date', 'datetime', 'percent'];

    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /** Rapor merkezindeki grup: Satış, Ürün, Stok, Müşteri, Pazarlama, İade ve kargo */
    abstract public function group(): string;

    /** Rapor kartındaki simge (x-icon adı). */
    public function icon(): string
    {
        return 'file';
    }

    /**
     * @return array<string, array{label: string, type: 'date'|'select'|'text'|'number', options?: array, advanced?: bool, rules?: array, default?: mixed}>
     */
    abstract public function filters(): array;

    /**
     * @return array<string, array{label: string, type: string, default?: bool, sortable?: bool, total?: bool}>
     */
    abstract public function columns(): array;

    abstract public function query(array $filters): EloquentBuilder|QueryBuilder;

    /** @return array{0: string, 1: 'asc'|'desc'} */
    public function defaultSort(): array
    {
        return [array_key_first($this->columns()), 'asc'];
    }

    /** Sorgudan sonra satırlara uygulanacak hesap; parçalar halinde de çağrılır. */
    public function transform(Collection $rows, array $filters): Collection
    {
        return $rows;
    }
}
