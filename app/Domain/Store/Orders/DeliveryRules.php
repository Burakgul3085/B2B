<?php

namespace App\Domain\Store\Orders;

use App\Domain\Support\Decimal;
use App\Models\Store\District;
use App\Models\Store\Province;
use App\Models\Store\StoreSetting;

/**
 * Teslimat yöntemleri ve ücretleri (KDV dahil, mağaza ayarlarından):
 *  - Kargo: tüm Türkiye; sabit ücret, eşik tutar ve üzerinde ücretsiz.
 *  - Mağaza teslimatı (kendi aracımız): yalnızca Eskişehir'in seçili ilçeleri.
 */
class DeliveryRules
{
    /** @return list<int> mağaza teslimatı yapılan ilçe kimlikleri */
    public function localDistrictIds(): array
    {
        $configured = array_filter(array_map('intval', explode(',', (string) StoreSetting::get('local_districts'))));
        $eskisehir = District::where('province_id', Province::ESKISEHIR)->pluck('id')->all();

        return $configured ? array_values(array_intersect($configured, $eskisehir)) : $eskisehir;
    }

    public function isLocal(int $provinceId, int $districtId): bool
    {
        return $provinceId === Province::ESKISEHIR && in_array($districtId, $this->localDistrictIds(), true);
    }

    /**
     * Adrese göre kullanılabilir teslimat yöntemleri ve ücretleri.
     *
     * @return array<string, string> yöntem => ücret
     */
    public function options(int $provinceId, int $districtId, string $subtotal): array
    {
        $options = ['cargo' => $this->cargoFee($subtotal)];
        if (StoreSetting::flag('local_delivery_enabled') && $this->isLocal($provinceId, $districtId)) {
            $options['local'] = $this->money(StoreSetting::get('local_delivery_fee'));
        }

        return $options;
    }

    public function cargoFee(string $subtotal): string
    {
        $threshold = Decimal::parse(StoreSetting::get('shipping_free_threshold'), 2);
        if ($threshold !== null && bccomp($threshold, '0', 2) > 0 && bccomp($subtotal, $threshold, 2) >= 0) {
            return '0.00';
        }

        return $this->money(StoreSetting::get('shipping_cargo_fee'));
    }

    private function money(?string $value): string
    {
        return Decimal::parse($value, 2) ?? '0.00';
    }
}
