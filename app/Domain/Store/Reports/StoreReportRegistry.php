<?php

namespace App\Domain\Store\Reports;

/** Mağaza raporları. Yeni rapor eklemek için sınıfı yazıp buraya eklemek yeterlidir; sıra rapor merkezindeki sıradır. */
final class StoreReportRegistry
{
    private const REPORTS = [
        Definitions\OrdersReport::class,
        Definitions\PeriodSalesReport::class,
        Definitions\OrderItemsReport::class,
        Definitions\PaymentDeliveryReport::class,
        Definitions\ProvinceSalesReport::class,
        Definitions\ProductSalesReport::class,
        Definitions\CategoryBrandSalesReport::class,
        Definitions\StockReport::class,
        Definitions\StockMovementsReport::class,
        Definitions\CustomersReport::class,
        Definitions\DiscountsReport::class,
        Definitions\ReturnsReport::class,
        Definitions\ShipmentsReport::class,
    ];

    /** @return array<string, StoreReport> anahtara göre */
    public static function all(): array
    {
        $reports = [];
        foreach (self::REPORTS as $class) {
            $report = app($class);
            $reports[$report->key()] = $report;
        }

        return $reports;
    }

    public static function find(string $key): ?StoreReport
    {
        return self::all()[$key] ?? null;
    }
}
