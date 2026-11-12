<?php

namespace App\Domain\Store\Orders;

use App\Domain\Support\Decimal;
use App\Models\Store\District;
use App\Models\Store\Province;
use App\Models\Store\StoreCarrier;
use App\Models\Store\StoreSetting;
use Illuminate\Database\Eloquent\Collection;

/**
 * Teslimat yöntemleri ve ücretleri (KDV dahil, mağaza ayarlarından):
 *  - Kargo: tüm Türkiye. Müşteri ücreti girilmiş firmalardan birini seçer, o firmanın ücreti uygulanır. Ücretsiz kargo
 *    sınırını geçen siparişte firma seçilmez (yönetici gönderirken seçer). Hiçbir firmanın ücreti girilmemişse
 *    Ödeme ve Teslimat ayarlarındaki genel kargo ücreti kullanılır.
 *  - Mağazadan teslim al (pickup): Eskişehir adreslerinde, her zaman ücretsiz.
 *  - Eve teslim (local, kendi aracımız): Eskişehir'in seçili ilçeleri. En düşük sipariş tutarının altında seçilemez;
 *    ücretsiz sınırın altında eve teslim ücreti uygulanır. Ayar açıksa eve teslim yapılabilen adreste kargo gösterilmez.
 * Tutar karşılaştırmaları indirimli ürün tutarına göre yapılır.
 */
class DeliveryRules
{
    /** @return list<int> eve teslim yapılan ilçe kimlikleri */
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

    public function isEskisehir(int $provinceId): bool
    {
        return $provinceId === Province::ESKISEHIR;
    }

    /**
     * Adrese göre kullanılabilir teslimat yöntemleri ve ücretleri.
     *
     * @return array<string, string> yöntem => ücret
     */
    public function options(int $provinceId, int $districtId, string $subtotal, ?StoreCarrier $carrier = null): array
    {
        $options = [];
        $home = $this->homeAvailable($provinceId, $districtId, $subtotal);
        if (! $home || ! StoreSetting::flag('local_hide_cargo')) {
            $options['cargo'] = $this->cargoFee($subtotal, $carrier);
        }
        if (StoreSetting::flag('pickup_enabled') && $this->isEskisehir($provinceId)) {
            $options['pickup'] = '0.00';
        }
        if ($home) {
            $options['local'] = $this->localFee($subtotal);
        }

        return $options;
    }

    /** Eve teslim bu adrese ve tutara yapılabilir mi (açık, ilçe seçili, en düşük tutar aşıldı). */
    public function homeAvailable(int $provinceId, int $districtId, string $subtotal): bool
    {
        if (! StoreSetting::flag('local_delivery_enabled') || ! $this->isLocal($provinceId, $districtId)) {
            return false;
        }
        $min = $this->localMinTotal();

        return $min === null || bccomp($subtotal, $min, 2) >= 0;
    }

    /** Eve teslim için en düşük sipariş tutarı (girilmemişse veya sıfırsa null). */
    public function localMinTotal(): ?string
    {
        $min = Decimal::parse(StoreSetting::get('local_min_total'), 2);

        return $min !== null && bccomp($min, '0', 2) > 0 ? $min : null;
    }

    /** Bu tutar ve üzeri eve teslim ücretsiz (girilmemişse veya sıfırsa null). */
    public function localFreeThreshold(): ?string
    {
        $threshold = Decimal::parse(StoreSetting::get('local_free_threshold'), 2);

        return $threshold !== null && bccomp($threshold, '0', 2) > 0 ? $threshold : null;
    }

    /** Eve teslim ücreti: ücretsiz sınır aşıldıysa 0, değilse ayarlardaki ücret. */
    public function localFee(string $subtotal): string
    {
        $threshold = $this->localFreeThreshold();
        if ($threshold !== null && bccomp($subtotal, $threshold, 2) >= 0) {
            return '0.00';
        }

        return $this->money(StoreSetting::get('local_delivery_fee'));
    }

    /** Ücretsiz kargo sınırı aşıldı mı (indirimli tutara göre). */
    public function cargoIsFree(string $subtotal): bool
    {
        $threshold = Decimal::parse(StoreSetting::get('shipping_free_threshold'), 2);

        return $threshold !== null && bccomp($threshold, '0', 2) > 0 && bccomp($subtotal, $threshold, 2) >= 0;
    }

    /**
     * Müşterinin seçebileceği kargo firmaları: kullanımda ve ücreti girilmiş, panel sırasıyla.
     *
     * @return Collection<int, StoreCarrier>
     */
    public function carrierChoices(): Collection
    {
        return StoreCarrier::where('is_active', true)->whereNotNull('fee')->orderBy('sort_order')->orderBy('name')->get();
    }

    /** Kargo ücreti: sınır aşıldıysa ücretsiz; seçilen firmanın ücreti; firma yoksa genel kargo ücreti. */
    public function cargoFee(string $subtotal, ?StoreCarrier $carrier = null): string
    {
        if ($this->cargoIsFree($subtotal)) {
            return '0.00';
        }

        return $this->money($carrier ? (string) $carrier->fee : StoreSetting::get('shipping_cargo_fee'));
    }

    /** Ürün ve yardım sayfalarında gösterilen kargo ücreti: firma fiyatlarının en düşüğü ya da genel ücret. */
    public function startingCargoFee(): string
    {
        $lowest = StoreCarrier::where('is_active', true)->whereNotNull('fee')->min('fee');

        return $this->money($lowest !== null ? (string) $lowest : StoreSetting::get('shipping_cargo_fee'));
    }

    private function money(?string $value): string
    {
        return Decimal::parse($value, 2) ?? '0.00';
    }
}
