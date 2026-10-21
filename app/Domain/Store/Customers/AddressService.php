<?php

namespace App\Domain\Store\Customers;

use App\Models\Store\District;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreCustomerAddress;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Müşteri adres defteri. İl ve ilçe sabit listeden seçilir ve ilçenin o ile ait olduğu sunucuda denetlenir
 * (kapıda ödeme bölge kontrolü bu bilgiye dayanır). İlk adres ve tek kalan adres varsayılan olur.
 */
class AddressService
{
    public const MAX_ADDRESSES = 20;

    /** @throws ValidationException|DomainException */
    public function create(StoreCustomer $customer, array $input): StoreCustomerAddress
    {
        if ($customer->addresses()->count() >= self::MAX_ADDRESSES) {
            throw new DomainException('En fazla '.self::MAX_ADDRESSES.' adres kaydedebilirsiniz.');
        }
        $data = self::validate($input);

        return DB::transaction(function () use ($customer, $data, $input) {
            $address = new StoreCustomerAddress($data);
            $address->customer_id = $customer->id;
            $address->is_default = ! $customer->addresses()->exists();
            $address->save();
            if (filter_var($input['is_default'] ?? false, FILTER_VALIDATE_BOOL)) {
                $this->makeDefault($address);
            }

            return $address;
        });
    }

    /** @throws ValidationException */
    public function update(StoreCustomerAddress $address, array $input): void
    {
        $address->update(self::validate($input));
        if (filter_var($input['is_default'] ?? false, FILTER_VALIDATE_BOOL)) {
            $this->makeDefault($address);
        }
    }

    public function delete(StoreCustomerAddress $address): void
    {
        DB::transaction(function () use ($address) {
            $address->delete();
            if ($address->is_default) {
                StoreCustomerAddress::where('customer_id', $address->customer_id)->orderBy('id')->first()?->forceFill(['is_default' => true])->save();
            }
        });
    }

    public function makeDefault(StoreCustomerAddress $address): void
    {
        DB::transaction(function () use ($address) {
            StoreCustomerAddress::where('customer_id', $address->customer_id)->whereKeyNot($address->id)->update(['is_default' => false]);
            $address->forceFill(['is_default' => true])->save();
        });
    }

    /**
     * Adres doğrulaması (adres defteri ve ödeme sayfasındaki misafir adresi için ortak).
     *
     * @throws ValidationException
     */
    public static function validate(array $input): array
    {
        $input['phone'] = PhoneNumber::normalize($input['phone'] ?? null);

        $data = Validator::make($input, [
            'title' => ['required', 'string', 'max:40'],
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::PATTERN],
            'province_id' => ['required', 'integer', 'exists:tr_provinces,id'],
            'district_id' => ['required', 'integer'],
            'neighborhood' => ['nullable', 'string', 'max:100'],
            'address_line' => ['required', 'string', 'min:10', 'max:300'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
        ], [
            'phone.regex' => 'Telefon numarasını 05xx xxx xx xx biçiminde girin.',
            'postal_code.regex' => 'Posta kodu 5 haneli olmalıdır.',
            'address_line.min' => 'Açık adresi sokak, bina ve daire numarasıyla yazın.',
        ], [
            'title' => 'adres başlığı', 'first_name' => 'ad', 'last_name' => 'soyad', 'phone' => 'telefon', 'province_id' => 'il',
            'district_id' => 'ilçe', 'neighborhood' => 'mahalle', 'address_line' => 'açık adres', 'postal_code' => 'posta kodu',
        ])->validate();

        if (! District::whereKey($data['district_id'])->where('province_id', $data['province_id'])->exists()) {
            throw ValidationException::withMessages(['district_id' => 'Seçilen ilçe bu ile ait değil.']);
        }

        return array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data) + ['neighborhood' => null, 'postal_code' => null];
    }
}
