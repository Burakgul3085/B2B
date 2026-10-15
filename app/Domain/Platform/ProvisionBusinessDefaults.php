<?php

namespace App\Domain\Platform;

use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Warehouse;

/**
 * Yeni işletmenin çalışmaya başlaması için gereken başlangıç tanımları:
 * varsayılan sevk deposu, temel birimler ve KDV oranları. İşletme sonradan hepsini düzenleyebilir.
 * KDV oranları işletme tarafından güncel mevzuata göre doğrulanmalıdır.
 */
class ProvisionBusinessDefaults
{
    public const UNITS = [
        ['code' => 'ADET', 'name' => 'Adet', 'allows_fraction' => false],
        ['code' => 'PAKET', 'name' => 'Paket', 'allows_fraction' => false],
        ['code' => 'KOLI', 'name' => 'Koli', 'allows_fraction' => false],
        ['code' => 'DUZINE', 'name' => 'Düzine', 'allows_fraction' => false],
        ['code' => 'KG', 'name' => 'Kilogram', 'allows_fraction' => true],
        ['code' => 'MT', 'name' => 'Metre', 'allows_fraction' => true],
    ];

    public const TAX_RATES = ['0.00' => 'KDV %0', '1.00' => 'KDV %1', '10.00' => 'KDV %10', '20.00' => 'KDV %20'];

    public function __construct(private TenantContext $tenant) {}

    public function handle(Business $business): void
    {
        $this->tenant->runAs($business->id, function () use ($business) {
            if (! Warehouse::exists()) {
                $warehouse = Warehouse::create(['code' => 'MERKEZ', 'name' => 'Merkez Depo']);
                $business->settings()->updateOrCreate([], ['default_warehouse_id' => $warehouse->id]);
            }

            foreach (self::UNITS as $unit) {
                Unit::firstOrCreate(['code' => $unit['code']], $unit);
            }

            foreach (self::TAX_RATES as $rate => $name) {
                TaxRate::firstOrCreate(['rate' => $rate], ['name' => $name, 'is_active' => true]);
            }
        });
    }
}
