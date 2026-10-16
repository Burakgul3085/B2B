<?php

namespace App\Domain\Reporting;

/** Raporu kimin çalıştırdığı: toptancı (işletme bağlamı) veya bayi (ilişki bağlamı). */
final class ReportContext
{
    public function __construct(
        public readonly string $side,
        public readonly int $businessId,
        public readonly ?int $relationshipId = null,
    ) {}

    public function isDealer(): bool
    {
        return $this->side === 'dealer';
    }
}
