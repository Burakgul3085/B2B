<?php

namespace App\Domain\Store\Discounts;

use App\Domain\Audit\AuditLogger;
use App\Domain\Support\Decimal;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreDiscount;
use App\Models\Store\StoreDiscountCode;
use App\Models\Store\StoreProductVariant;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Kampanya ve kupon yönetimi. Kullanılmış kuponun kodu ve türü değiştirilemez, kendisi silinemez (pasife alınır);
 * siparişlerde indirim adı ve tutarı kopya olarak durduğu için diğer değişiklikler eski siparişleri etkilemez.
 */
class DiscountAdmin
{
    public function __construct(private AuditLogger $audit) {}

    /** @throws ValidationException|DomainException */
    public function save(array $input, ?StoreDiscount $discount = null): StoreDiscount
    {
        $isCoupon = ($input['kind'] ?? 'coupon') === 'coupon';
        $input['code'] = $isCoupon ? DiscountService::normalizeCode($input['code'] ?? null) : null;

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'in:coupon,campaign'],
            'code' => [$isCoupon ? 'required' : 'nullable', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('store_discounts', 'code')->ignore($discount?->id)],
            'type' => ['required', Rule::in(array_keys(StoreDiscount::TYPES))],
            'value' => ['nullable', 'string', 'max:20'],
            'min_subtotal' => ['nullable', 'string', 'max:20'],
            'max_discount' => ['nullable', 'string', 'max:20'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer'],
            'product_skus' => ['nullable', 'string', 'max:5000'],
        ], ['code.regex' => 'Kupon kodu yalnızca harf, rakam, - ve _ içerebilir (Türkçe karakter olmadan).'], [
            'name' => 'ad', 'code' => 'kupon kodu', 'type' => 'indirim türü', 'value' => 'indirim', 'min_subtotal' => 'en düşük sepet tutarı',
            'max_discount' => 'en fazla indirim', 'starts_at' => 'başlangıç', 'ends_at' => 'bitiş', 'usage_limit' => 'toplam kullanım', 'per_customer_limit' => 'kişi başı kullanım',
        ])->validate();

        $errors = [];
        $money = fn (string $key) => filled($data[$key] ?? null) ? Decimal::parse($data[$key], 2) : null;
        $value = $data['type'] === 'free_shipping' ? null : $money('value');
        if ($data['type'] !== 'free_shipping' && ($value === null || bccomp($value, '0', 2) <= 0)) {
            $errors['value'] = 'İndirim değerini girin.';
        } elseif ($data['type'] === 'percent' && bccomp($value, '100', 2) > 0) {
            $errors['value'] = 'Yüzde indirim en fazla 100 olabilir.';
        }
        foreach (['min_subtotal', 'max_discount'] as $key) {
            if (filled($data[$key] ?? null) && ($money($key) === null || bccomp($money($key), '0', 2) <= 0)) {
                $errors[$key] = 'Geçerli bir tutar girin (ör. 250,00).';
            }
        }
        // Kapsam: kategoriler (alt kategorileriyle) ve SKU ile ürünler.
        $categoryIds = StoreCategory::whereIn('id', array_map('intval', $data['category_ids'] ?? []))->pluck('id')->all();
        $skus = array_values(array_unique(array_filter(array_map(fn ($v) => strtoupper(trim($v)), preg_split('/[\s,;]+/', (string) ($data['product_skus'] ?? ''))))));
        $productIds = $skus ? StoreProductVariant::whereIn('sku', $skus)->pluck('product_id')->unique()->values()->all() : [];
        $unknown = $skus ? array_diff($skus, StoreProductVariant::whereIn('sku', $skus)->pluck('sku')->map(fn ($v) => strtoupper($v))->all()) : [];
        if ($unknown) {
            $errors['product_skus'] = 'Bulunamayan SKU: '.implode(', ', array_slice($unknown, 0, 10)).'.';
        }
        $codesOnly = $isCoupon && filter_var($input['codes_only'] ?? false, FILTER_VALIDATE_BOOL);

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $used = $discount && $discount->redemptions()->exists();
        if ($used && ($discount->code !== $data['code'] || $discount->type !== $data['type'])) {
            throw new DomainException('Kullanılmış bir indirimin kodu ve türü değiştirilemez. Yeni bir kupon oluşturun.');
        }

        $values = [
            'name' => trim($data['name']),
            'code' => $data['code'],
            'type' => $data['type'],
            'value' => $value,
            'min_subtotal' => $money('min_subtotal'),
            'max_discount' => $data['type'] === 'percent' ? $money('max_discount') : null,
            'starts_at' => filled($data['starts_at'] ?? null) ? Carbon::parse($data['starts_at'], 'Europe/Istanbul') : null,
            'ends_at' => filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at'], 'Europe/Istanbul') : null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'per_customer_limit' => $data['per_customer_limit'] ?? null,
            'first_order_only' => filter_var($input['first_order_only'] ?? false, FILTER_VALIDATE_BOOL),
            'category_ids' => $categoryIds ?: null,
            'product_ids' => $productIds ?: null,
            'codes_only' => $codesOnly,
            'is_active' => filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOL),
            // Ana sayfa kartı yalnızca herkesin kullanabileceği kodlu kuponlarda anlamlıdır.
            'show_on_home' => $isCoupon && ! $codesOnly && filter_var($input['show_on_home'] ?? false, FILTER_VALIDATE_BOOL),
        ];

        if ($discount) {
            $old = $discount->only(['name', 'code', 'type', 'value', 'is_active']);
            $discount->update($values);
            $this->audit->log('store.discount_updated', $discount, $old, $discount->only(array_keys($old)));

            return $discount;
        }

        $discount = StoreDiscount::create($values);
        $this->audit->log('store.discount_created', $discount, [], ['name' => $discount->name, 'code' => $discount->code]);

        return $discount;
    }

    /**
     * Toplu, tek kullanımlık kodlar üretir: ÖNEK-XXXXXXXX (karışabilecek 0/O, 1/I harfleri kullanılmaz).
     *
     * @return int üretilen kod sayısı
     *
     * @throws ValidationException|DomainException
     */
    public function generateCodes(StoreDiscount $discount, array $input): int
    {
        if (! $discount->isCoupon()) {
            throw new DomainException('Toplu kod yalnızca kuponlar için üretilir.');
        }
        $data = Validator::make($input, [
            'count' => ['required', 'integer', 'min:1', 'max:1000'],
            'prefix' => ['nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9]+$/'],
        ], ['prefix.regex' => 'Önek yalnızca harf ve rakam içerebilir.'], ['count' => 'kod sayısı', 'prefix' => 'önek'])->validate();
        if ($discount->codes()->count() + (int) $data['count'] > 10000) {
            throw new DomainException('Bir kupona en fazla 10.000 kod üretilebilir.');
        }

        $prefix = strtoupper((string) ($data['prefix'] ?? '')) ?: $discount->code;
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codes = [];
        while (count($codes) < (int) $data['count']) {
            $random = '';
            for ($i = 0; $i < 8; $i++) {
                $random .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = mb_substr($prefix, 0, 31).'-'.$random;
            if (! isset($codes[$code]) && ! StoreDiscountCode::where('code', $code)->exists() && ! StoreDiscount::where('code', $code)->exists()) {
                $codes[$code] = ['discount_id' => $discount->id, 'code' => $code, 'created_at' => now()];
            }
        }
        DB::transaction(fn () => collect($codes)->chunk(500)->each(fn ($chunk) => StoreDiscountCode::insert($chunk->values()->all())));
        $this->audit->log('store.discount_codes_generated', $discount, [], ['name' => $discount->name, 'count' => count($codes)]);

        return count($codes);
    }

    /** @throws DomainException */
    public function delete(StoreDiscount $discount): void
    {
        if ($discount->redemptions()->exists()) {
            throw new DomainException('Bu indirim siparişlerde kullanıldığı için silinemez; pasife alabilirsiniz.');
        }
        $discount->delete();
        $this->audit->log('store.discount_deleted', null, ['name' => $discount->name, 'code' => $discount->code], []);
    }
}
