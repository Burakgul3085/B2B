<?php

namespace App\Domain\Store\Shipping;

use App\Domain\Audit\AuditLogger;
use App\Domain\Support\Decimal;
use App\Models\Store\StoreCarrier;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Kargo firmaları. Ücreti girilen firma ödeme sayfasında müşteriye seçenek olur. Takip bağlantısı şablonu https ile
 * başlar; {takip} gönderide takip numarasıyla değişir. Bilinen firmalarda boş bırakılabilir (CarrierBrands).
 * Firma entegrasyonu (gönderi oluşturma, durum sorgulama) anlaşma yapıldığında bu kayda bağlanacak.
 */
class CarrierService
{
    public function __construct(private AuditLogger $audit) {}

    public function save(array $input, ?StoreCarrier $carrier = null): StoreCarrier
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:60', Rule::unique('store_carriers', 'name')->ignore($carrier?->id)],
            // {takip} süslü parantez içerdiği için adres, yer tutucu doldurulmuş hâliyle denetlenir. {takip} yoksa
            // bağlantı firmanın takip sayfasını açar (numarayı adresten almayan firmalar için).
            'tracking_url' => ['nullable', 'string', 'max:300', 'starts_with:https://',
                fn ($attr, $value, $fail) => filter_var(str_replace('{takip}', 'X1', (string) $value), FILTER_VALIDATE_URL) ? null : $fail('Geçerli bir https adresi girin.')],
            'fee' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], ['name' => 'firma adı', 'tracking_url' => 'takip bağlantısı', 'fee' => 'kargo ücreti', 'sort_order' => 'sıra'])->validate();

        // Ücret boşsa firma müşteriye seçenek olarak sunulmaz (yalnızca gönderide seçilir).
        $fee = null;
        if (trim((string) ($data['fee'] ?? '')) !== '') {
            $fee = Decimal::parse($data['fee'], 2);
            if ($fee === null || bccomp($fee, '0', 2) < 0 || bccomp($fee, '100000', 2) >= 0) {
                throw ValidationException::withMessages(['fee' => 'Geçerli bir kargo ücreti girin (ör. 89,90).']);
            }
        }

        $values = [
            'name' => trim($data['name']),
            'tracking_url' => trim((string) ($data['tracking_url'] ?? '')) ?: null,
            'fee' => $fee,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOL),
        ];

        if ($carrier) {
            $old = $carrier->only(array_keys($values));
            $carrier->update($values);
            $this->audit->log('store.carrier_updated', $carrier, $old, $carrier->only(array_keys($values)));

            return $carrier;
        }

        $carrier = StoreCarrier::create($values);
        $this->audit->log('store.carrier_created', $carrier, [], ['name' => $carrier->name]);

        return $carrier;
    }

    /** Siparişlerde firma adı ve takip bağlantısı kopya olarak durduğu için silmek eski siparişleri etkilemez. */
    public function delete(StoreCarrier $carrier): void
    {
        $carrier->delete();
        $this->audit->log('store.carrier_deleted', null, ['name' => $carrier->name], []);
    }
}
