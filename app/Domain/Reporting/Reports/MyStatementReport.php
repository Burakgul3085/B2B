<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\ReportContext;

/** Bayi: cari ekstre (dışa aktarılabilir). */
class MyStatementReport extends AccountMovementReport
{
    public function key(): string
    {
        return 'my-statement';
    }

    public function title(): string
    {
        return 'Cari hesap ekstrem';
    }

    public function audience(): string
    {
        return 'dealer';
    }

    public function filters(ReportContext $context): array
    {
        return Options::dateFilters('year');
    }

    public function columns(): array
    {
        $cols = parent::columns();
        unset($cols['dealer']);

        return $cols;
    }
}
