<?php

namespace App\Domain\Reporting;

/** Kullanılabilir raporlar. Yeni rapor eklemek için sınıfı yazıp buraya eklemek yeterlidir. */
final class ReportRegistry
{
    private const REPORTS = [
        Reports\ProductListReport::class,
        Reports\DealerPriceReport::class,
        Reports\CriticalStockReport::class,
        Reports\WarehouseStockReport::class,
        Reports\StockMovementReport::class,
        Reports\OrderReport::class,
        Reports\OrderItemReport::class,
        Reports\TopProductsReport::class,
        Reports\DealerVolumeReport::class,
        Reports\ApplicationReport::class,
        Reports\AccountBalanceReport::class,
        Reports\AccountMovementReport::class,
        Reports\CollectionReport::class,
        Reports\MyOrdersReport::class,
        Reports\MyProductsReport::class,
        Reports\MyStatementReport::class,
    ];

    /** @return Report[] anahtara göre */
    public static function for(string $audience): array
    {
        $reports = [];
        foreach (self::REPORTS as $class) {
            $report = app($class);
            if ($report->audience() === $audience) {
                $reports[$report->key()] = $report;
            }
        }

        return $reports;
    }

    public static function find(string $audience, string $key): ?Report
    {
        return self::for($audience)[$key] ?? null;
    }
}
