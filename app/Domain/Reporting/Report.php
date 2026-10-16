<?php

namespace App\Domain\Reporting;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Rapor tanımı. Ekran, Excel ve PDF aynı tanımı (filtreler, sorgu, sütunlar, toplamlar) kullanır;
 * böylece ekrandaki rapor ile indirilen rapor aynı sonucu verir (Bölüm 13).
 *
 * Sorgu, sütun anahtarlarıyla aynı adlı alanları (alias) seçmelidir. İşletme kapsamı modellerin
 * global scope'u ile gelir; bayi raporları ayrıca ReportContext::$relationshipId ile sınırlanır.
 */
abstract class Report
{
    /** money | qty | int | text | date | datetime | percent | badge */
    public const TYPES = ['money', 'qty', 'int', 'text', 'date', 'datetime', 'percent', 'badge'];

    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /** Rapor merkezindeki grup: Ürün, Stok, Sipariş, Cari, Bayi */
    abstract public function group(): string;

    /** @return 'business'|'dealer' */
    public function audience(): string
    {
        return 'business';
    }

    /**
     * @return array<string, array{label: string, type: 'date'|'select'|'text'|'number'|'boolean', options?: array, advanced?: bool, rules?: array, default?: mixed}>
     */
    abstract public function filters(ReportContext $context): array;

    /**
     * @return array<string, array{label: string, type: string, default?: bool, sortable?: bool, total?: bool}>
     */
    abstract public function columns(): array;

    abstract public function query(array $filters, ReportContext $context): EloquentBuilder|QueryBuilder;

    /** @return array{0: string, 1: 'asc'|'desc'} */
    public function defaultSort(): array
    {
        return [array_key_first($this->columns()), 'asc'];
    }

    /** Sorgudan sonra satırlara uygulanacak hesap (ör. vade yaşlandırma). Parçalar halinde de çağrılır. */
    public function transform(Collection $rows, array $filters, ReportContext $context): Collection
    {
        return $rows;
    }

    /** Toplamları sorgu yerine satırlardan hesaplamak gerekiyorsa true (transform ile üretilen sütunlar). */
    public function totalsFromRows(): bool
    {
        return false;
    }

    /** Raporu çalıştırmak için zorunlu filtre eksikse kullanıcıya gösterilecek mesaj. */
    public function missingRequirement(array $filters): ?string
    {
        return null;
    }
}
