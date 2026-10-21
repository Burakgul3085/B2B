<?php

namespace App\Domain\Store\Shipping;

use App\Domain\Audit\AuditLogger;
use App\Models\Store\StoreCarrier;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Kargo firmaları. Takip bağlantısı şablonu https ile başlar ve {takip} içerir; gönderide takip numarası yerine yazılır.
 * Firma entegrasyonu (gönderi oluşturma, durum sorgulama) anlaşma yapıldığında bu kayda bağlanacak.
 */
class CarrierService
{
    public function __construct(private AuditLogger $audit) {}

    public function save(array $input, ?StoreCarrier $carrier = null): StoreCarrier
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:60', Rule::unique('store_carriers', 'name')->ignore($carrier?->id)],
            // {takip} süslü parantez içerdiği için adres, yer tutucu doldurulmuş hâliyle denetlenir.
            'tracking_url' => ['nullable', 'string', 'max:300', 'starts_with:https://', 'regex:/\{takip\}/',
                fn ($attr, $value, $fail) => filter_var(str_replace('{takip}', 'X1', (string) $value), FILTER_VALIDATE_URL) ? null : $fail('Geçerli bir https adresi girin.')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], ['tracking_url.regex' => 'Takip bağlantısında takip numarasının geleceği yere {takip} yazın.'],
            ['name' => 'firma adı', 'tracking_url' => 'takip bağlantısı', 'sort_order' => 'sıra'])->validate();

        $values = [
            'name' => trim($data['name']),
            'tracking_url' => trim((string) ($data['tracking_url'] ?? '')) ?: null,
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
