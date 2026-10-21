<?php

namespace App\Domain\Catalog;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\ImageUploader;
use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Unit;
use App\Models\VariantGroup;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Ürün iş kuralları. Ürün formu ve Excel/CSV içe aktarma aynı doğrulamayı kullanır.
 */
class ProductService
{
    public const MAX_IMAGES = 8;

    /** Ürün geçmişinde izlenen alanlar. */
    private const TRACKED = [
        'sku', 'barcode', 'name', 'category_id', 'brand_id', 'unit_id', 'pack_quantity', 'purchase_price', 'list_price',
        'dealer_price', 'tax_rate_id', 'price_includes_tax', 'min_order_qty', 'order_step', 'critical_stock_level',
        'is_active', 'visible_to_dealers',
    ];

    public function __construct(
        private TenantContext $tenant,
        private AuditLogger $audit,
        private ImageUploader $images,
    ) {}

    public function rules(?Product $product = null): array
    {
        $businessId = $this->tenant->businessId();
        $scoped = fn (string $table) => Rule::exists($table, 'id')->where('business_id', $businessId);
        $money = ['decimal:0,4', 'min:0', 'max:99999999999'];
        $qty = ['decimal:0,3', 'gt:0', 'max:99999999999'];

        return [
            'sku' => ['required', 'string', 'max:64', 'regex:/^[\pL\pN._\-\/]+$/u',
                Rule::unique('products', 'sku')->where('business_id', $businessId)->ignore($product?->id)],
            'barcode' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('products', 'barcode')->where('business_id', $businessId)->ignore($product?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', $scoped('categories')],
            'brand_id' => ['nullable', 'integer', $scoped('brands')],
            'model' => ['nullable', 'string', 'max:120'],
            'unit_id' => ['required', 'integer', $scoped('units')],
            'pack_quantity' => ['required', ...$qty],
            'purchase_price' => ['nullable', ...$money],
            'list_price' => ['required', ...$money],
            'dealer_price' => ['nullable', ...$money],
            'tax_rate_id' => ['required', 'integer', Rule::exists('tax_rates', 'id')->where('business_id', $businessId)->where('is_active', true)],
            'price_includes_tax' => ['boolean'],
            'min_order_qty' => ['required', ...$qty],
            'order_step' => ['required', ...$qty],
            'critical_stock_level' => ['required', 'decimal:0,3', 'min:0', 'max:99999999999'],
            'variant_group_id' => ['nullable', 'integer', $scoped('variant_groups')],
            'variant_values' => ['nullable', 'array', 'max:3'],
            'variant_values.*' => ['nullable', 'string', 'max:60'],
            'is_active' => ['boolean'],
            'visible_to_dealers' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sku' => 'ürün kodu (SKU)', 'barcode' => 'barkod', 'name' => 'ürün adı', 'category_id' => 'kategori',
            'brand_id' => 'marka', 'unit_id' => 'birim', 'pack_quantity' => 'koli/paket içi miktar',
            'purchase_price' => 'alış fiyatı', 'list_price' => 'liste fiyatı', 'dealer_price' => 'bayi fiyatı',
            'tax_rate_id' => 'KDV oranı', 'min_order_qty' => 'minimum sipariş miktarı', 'order_step' => 'sipariş artış adımı',
            'critical_stock_level' => 'kritik stok seviyesi', 'variant_group_id' => 'varyant grubu',
        ];
    }

    /**
     * Girdiyi doğrular ve iş kurallarını uygular.
     *
     * @throws ValidationException
     */
    public function validate(array $input, ?Product $product = null): array
    {
        // Dizi gelirse olduğu gibi bırakılır; doğrulama "metin olmalı" hatası verir.
        foreach (['barcode', 'sku'] as $field) {
            if (isset($input[$field]) && is_scalar($input[$field])) {
                $input[$field] = trim((string) $input[$field]);
            }
        }
        if (($input['barcode'] ?? '') === '') {
            $input['barcode'] = null;
        }
        $input['sku'] ??= null;
        $input += ['pack_quantity' => 1, 'min_order_qty' => 1, 'order_step' => 1, 'critical_stock_level' => 0];

        $validator = Validator::make($input, $this->rules($product), [], $this->attributes());

        $validator->after(function ($v) use ($input) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $unit = Unit::find($input['unit_id']);
            $step = (string) $input['order_step'];
            $min = (string) $input['min_order_qty'];

            if ($unit && ! $unit->allows_fraction) {
                foreach (['min_order_qty' => $min, 'order_step' => $step, 'pack_quantity' => (string) $input['pack_quantity']] as $field => $value) {
                    if (bccomp($value, bcadd($value, '0', 0), 3) !== 0) {
                        $v->errors()->add($field, "\"{$unit->name}\" biriminde küsuratlı miktar kullanılamaz.");
                    }
                }
            }

            // Minimum miktar, artış adımının katı olmalı (ör. adım 12 ise minimum 12, 24…).
            if (bccomp(bcmod($min, $step, 3), '0', 3) !== 0) {
                $v->errors()->add('min_order_qty', 'Minimum sipariş miktarı, sipariş artış adımının katı olmalıdır.');
            }
        });

        $data = $validator->validate();
        // Varyant değerleri grubun özellik adlarıyla anahtarlanır: {"Renk": "Mavi"}.
        $data['variant_values'] = null;
        if (! empty($data['variant_group_id'])) {
            $attributes = VariantGroup::find($data['variant_group_id'])->attribute_names;
            $data['variant_values'] = array_filter(
                array_intersect_key($input['variant_values'] ?? [], array_flip($attributes)),
                fn ($v) => filled($v),
            ) ?: null;
        }

        return $data;
    }

    public function create(array $data): Product
    {
        $limit = Business::with('licenses')->find($this->tenant->businessId())?->currentLicense()?->max_products;
        if ($limit !== null && Product::notArchived()->count() >= $limit) {
            throw new DomainException("Lisansınızdaki ürün sınırına ({$limit}) ulaşıldı. Kullanılmayan ürünleri arşivleyin veya platform yöneticinizle iletişime geçin.");
        }

        return DB::transaction(function () use ($data) {
            $product = Product::create($data);
            $this->audit->log('product.created', $product, [], $product->only(self::TRACKED));

            return $product;
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->fill($data);
            $changed = array_intersect(array_keys($product->getDirty()), self::TRACKED);

            if ($changed !== []) {
                $old = array_intersect_key($product->getOriginal(), array_flip($changed));
                $product->save();
                $this->audit->log('product.updated', $product, $old, $product->only($changed));
            } else {
                $product->save();
            }

            return $product;
        });
    }

    /** Ürünü kopyalar: yeni SKU zorunlu, barkod ve görseller kopyalanmaz, stok sıfırdan başlar. */
    public function duplicate(Product $source, string $newSku, ?string $newName = null): Product
    {
        $data = $source->only(Product::EDITABLE);
        $data['sku'] = $newSku;
        $data['barcode'] = null;
        $data['name'] = $newName ?: $source->name.' (kopya)';
        $data['is_active'] = false;

        $data = $this->validate($data);
        $product = $this->create($data);
        $this->audit->log('product.duplicated', $product, [], ['source_product_id' => $source->id]);

        return $product;
    }

    public function archive(Product $product): void
    {
        $product->forceFill(['archived_at' => now(), 'is_active' => false])->save();
        $this->audit->log('product.archived', $product);
    }

    public function restore(Product $product): void
    {
        $product->forceFill(['archived_at' => null])->save();
        $this->audit->log('product.restored', $product);
    }

    /** @param  int[]  $ids */
    public function bulkSetActive(array $ids, bool $active): int
    {
        $count = Product::whereIn('id', $ids)->notArchived()->update(['is_active' => $active, 'updated_at' => now()]);
        $this->audit->log($active ? 'product.bulk_activated' : 'product.bulk_deactivated', null, [], ['ids' => array_values($ids)]);

        return $count;
    }

    /**
     * Toplu fiyat güncelleme. Yüzde veya sabit tutar; sonuç 0'ın altına inemez.
     * Her ürünün eski/yeni fiyatı ürün geçmişine yazılır.
     *
     * @param  'list_price'|'dealer_price'|'purchase_price'  $field
     * @param  'percent'|'amount'  $mode
     */
    public function bulkPriceUpdate(Builder $query, string $field, string $mode, string $value): int
    {
        if (! in_array($field, ['list_price', 'dealer_price', 'purchase_price'], true) || ! in_array($mode, ['percent', 'amount'], true)) {
            throw new DomainException('Geçersiz fiyat güncelleme seçimi.');
        }

        $updated = 0;
        DB::transaction(function () use ($query, $field, $mode, $value, &$updated) {
            $query->whereNotNull($field)->orderBy('id')->lockForUpdate()->chunkById(500, function ($products) use ($field, $mode, $value, &$updated) {
                foreach ($products as $product) {
                    $old = (string) $product->{$field};
                    $new = $mode === 'percent'
                        ? bcmul($old, bcadd('1', bcdiv($value, '100', 6), 6), 4)
                        : bcadd($old, $value, 4);
                    $new = bccomp($new, '0', 4) < 0 ? '0.0000' : $new;

                    if (bccomp($old, $new, 4) !== 0) {
                        $product->forceFill([$field => $new])->save();
                        $this->audit->log('product.updated', $product, [$field => $old], [$field => $new]);
                        $updated++;
                    }
                }
            });
        });

        return $updated;
    }

    public function addImage(Product $product, UploadedFile $file): ProductImage
    {
        if ($product->images()->count() >= self::MAX_IMAGES) {
            throw new DomainException('Bir ürüne en fazla '.self::MAX_IMAGES.' görsel eklenebilir.');
        }

        // Beyaz kenarlar kırpılır (her ürün kartı eşit doldurur); detay sayfasında doku net kalsın diye
        // daha büyük ve daha yüksek kalitede saklanır.
        $path = $this->images->store($file, 'businesses/'.$this->tenant->businessId().'/products/'.$product->id,
            maxEdge: 2000, quality: 90, trimWhitespace: true, sharpen: true);

        return ProductImage::create([
            'product_id' => $product->id,
            'path' => $path,
            'sort' => (int) $product->images()->max('sort') + 1,
            'is_primary' => ! $product->images()->exists(),
        ]);
    }

    public function removeImage(ProductImage $image): void
    {
        DB::transaction(function () use ($image) {
            $wasPrimary = $image->is_primary;
            $productId = $image->product_id;
            $image->delete();

            if ($wasPrimary) {
                ProductImage::where('product_id', $productId)->orderBy('sort')->first()?->update(['is_primary' => true]);
            }
        });

        $this->images->delete($image->path);
    }

    public function makePrimary(ProductImage $image): void
    {
        DB::transaction(function () use ($image) {
            ProductImage::where('product_id', $image->product_id)->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        });
    }

    /** Yeni ürün için önerilen benzersiz SKU (kopyalama ekranında). */
    public function suggestSku(string $base): string
    {
        $base = Str::limit($base, 56, '');
        for ($i = 2; $i < 1000; $i++) {
            $candidate = "{$base}-{$i}";
            if (! Product::where('sku', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $base.'-'.Str::upper(Str::random(4));
    }
}
