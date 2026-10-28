<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Support\LocalTime;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DealerGroup;
use App\Models\DealerRelationship;
use App\Models\Warehouse;

/** Rapor filtrelerinde kullanılan seçenek listeleri (işletme kapsamında). */
final class Options
{
    public static function dealers(): array
    {
        return ['' => 'Tüm bayiler'] + DealerRelationship::with('dealer:id,legal_name')->orderBy('dealer_code')->get()
            ->mapWithKeys(fn ($r) => [$r->id => $r->dealer_code.' · '.$r->dealer->legal_name])->all();
    }

    public static function categories(): array
    {
        return ['' => 'Tüm kategoriler'] + Category::with('parent')->get()->sortBy(fn ($c) => $c->fullName())
            ->mapWithKeys(fn ($c) => [$c->id => $c->fullName()])->all();
    }

    public static function brands(): array
    {
        return ['' => 'Tüm markalar'] + Brand::orderBy('name')->pluck('name', 'id')->all();
    }

    public static function warehouses(bool $all = true): array
    {
        return ($all ? ['' => 'Tüm depolar'] : []) + Warehouse::orderBy('name')->pluck('name', 'id')->all();
    }

    public static function groups(): array
    {
        return ['' => 'Tüm gruplar'] + DealerGroup::orderBy('name')->pluck('name', 'id')->all();
    }

    /** Kategori ve alt kategorileri. */
    public static function categoryIds(int $id): array
    {
        return [$id, ...Category::where('parent_id', $id)->pluck('id')->all()];
    }

    public static function dateFilters(string $defaultFrom = 'month'): array
    {
        return [
            'from' => ['label' => 'Başlangıç', 'type' => 'date', 'default' => $defaultFrom === 'year' ? LocalTime::now()->startOfYear()->toDateString() : LocalTime::now()->startOfMonth()->toDateString()],
            'to' => ['label' => 'Bitiş', 'type' => 'date', 'default' => LocalTime::today()],
        ];
    }

    public static function like(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }
}
