<?php

namespace App\Domain\Store\Reports\Definitions;

use App\Domain\Store\Reports\StoreReports;
use App\Models\Store\Province;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCarrier;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreOrder;
use Illuminate\Support\Carbon;

/** Raporlarda ortak filtreler, seçenek listeleri ve SQL etiketleri. */
final class ReportOptions
{
    private const TZ = 'Europe/Istanbul';

    /** Tarih aralığı (Türkiye saatine göre gün). $default: month (bu ay) | year (son 12 ay) | none */
    public static function dates(string $default = 'month', string $label = 'Tarih'): array
    {
        $today = Carbon::now(self::TZ);
        $from = match ($default) {
            'year' => $today->copy()->subYear()->addDay()->toDateString(),
            'none' => null,
            default => $today->copy()->startOfMonth()->toDateString(),
        };

        return [
            'from' => ['label' => "{$label} (başlangıç)", 'type' => 'date', 'default' => $from],
            'to' => ['label' => "{$label} (bitiş)", 'type' => 'date', 'default' => $default === 'none' ? null : $today->toDateString()],
        ];
    }

    /** @return array{0: Carbon, 1: Carbon}|null UTC sınırlar [başlangıç, bitiş) */
    public static function bounds(array $f): ?array
    {
        if (empty($f['from']) && empty($f['to'])) {
            return null;
        }

        return StoreReports::bounds($f['from'] ?? '2000-01-01', $f['to'] ?? Carbon::now(self::TZ)->toDateString());
    }

    /** Varsayılanı olan seçimde "Tümü" boş değer olamaz (boş değer varsayılana döner); bu yüzden 'all'. */
    public static function orderStatus(): array
    {
        return ['label' => 'Durum', 'type' => 'select', 'default' => 'sales', 'options' => [
            'sales' => 'Satış sayılanlar', 'all' => 'Tümü', 'open' => 'Açık (teslim edilmemiş)', 'closed' => 'İptal / teslim alınmadı',
        ] + StoreOrder::STATUSES];
    }

    /** Sipariş durumu filtresini sorguya uygular (store_orders tablosu). */
    public static function applyStatus($query, ?string $status): void
    {
        match (true) {
            $status === 'sales' => $query->whereIn('store_orders.status', StoreReports::SALE_STATUSES),
            $status === 'open' => $query->whereIn('store_orders.status', StoreOrder::OPEN),
            $status === 'closed' => $query->whereIn('store_orders.status', ['cancelled', 'not_delivered']),
            isset(StoreOrder::STATUSES[$status ?? '']) => $query->where('store_orders.status', $status),
            default => null,
        };
    }

    public static function payment(): array
    {
        return ['label' => 'Ödeme', 'type' => 'select', 'options' => ['' => 'Tümü'] + StoreOrder::PAYMENT_METHODS];
    }

    public static function delivery(): array
    {
        return ['label' => 'Teslimat', 'type' => 'select', 'options' => ['' => 'Tümü'] + StoreOrder::DELIVERY_METHODS];
    }

    public static function provinces(): array
    {
        return ['' => 'Tümü'] + Province::orderBy('name')->pluck('name', 'id')->all();
    }

    public static function carriers(): array
    {
        return ['' => 'Tümü'] + StoreCarrier::orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Ana kategoriler ve alt kategorileri (girintili). */
    public static function categories(): array
    {
        $all = StoreCategory::orderBy('sort')->orderBy('name')->get(['id', 'parent_id', 'name'])->groupBy(fn ($c) => (int) $c->parent_id);
        $out = ['' => 'Tümü'];
        $walk = function (int $parent, int $depth) use (&$walk, $all, &$out) {
            foreach ($all[$parent] ?? [] as $c) {
                $out[$c->id] = str_repeat('— ', $depth).$c->name;
                $walk($c->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $out;
    }

    /** @return list<int> kategori ve alt kategorileri */
    public static function categoryIds(int $id): array
    {
        return StoreCategory::find($id)?->descendantIds() ?? [$id];
    }

    public static function brands(): array
    {
        return ['' => 'Tümü'] + StoreBrand::orderBy('name')->pluck('name', 'id')->all();
    }

    /** SQL CASE ifadesi: kod => etiket (etiketler sabitlerden gelir; tırnak kaçırılır). */
    public static function label(string $column, array $labels): string
    {
        $cases = collect($labels)->map(fn ($l, $k) => "WHEN '".str_replace("'", "''", (string) $k)."' THEN '".str_replace("'", "''", (string) $l)."'")->implode(' ');

        return "CASE {$column} {$cases} ELSE {$column} END";
    }

    /** UTC zaman damgasını Türkiye saatine çeviren SQL (sunucu saat dilimi tablolarına bağlı değil). */
    public static function local(string $column): string
    {
        return "DATE_ADD({$column}, INTERVAL 3 HOUR)";
    }
}
