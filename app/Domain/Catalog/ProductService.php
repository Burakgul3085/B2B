<?php

namespace App\Domain\Catalog;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\ImageUploader;
use App\Domain\Files\ImageVariants;
use App\Domain\Tenancy\TenantContext;
use App\Jobs\GenerateImageVariants;
use App\Models\Business;
use App\Models\CartItem;
use App\Models\DealerProductPrice;
use App\Models\DiscountRule;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductRequest;
use App\Models\Promotion;
use App\Models\StockCountLine;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\VariantGroup;
use App\Models\WarehouseStock;
use App\Rules\NotBefore;
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
        'sku', 'barcode', 'name', 'category_id', 'brand_id', 'unit_id', 'sale_mode', 'pack_quantity', 'purchase_price', 'list_price',
        'dealer_price', 'dealer_sale_price', 'dealer_sale_starts_on', 'dealer_sale_ends_on', 'tax_rate_id',
        'price_includes_tax', 'min_order_qty', 'order_step', 'critical_stock_level', 'option_name', 'option_value', 'is_active', 'visible_to_dealers',
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
            'sale_mode' => ['required', Rule::in(array_keys(Product::SALE_MODES))],
            'min_cases' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'pack_quantity' => ['required', ...$qty],
            'purchase_price' => ['nullable', ...$money],
            'list_price' => ['required', ...$money],
            'dealer_sale_price' => ['nullable', 'decimal:0,4', 'gt:0', 'max:99999999999'],
            'dealer_sale_starts_on' => ['nullable', 'date'],
            'dealer_sale_ends_on' => ['nullable', 'date', new NotBefore('dealer_sale_starts_on')],
            'tax_rate_id' => ['required', 'integer', Rule::exists('tax_rates', 'id')->where('business_id', $businessId)->where('is_active', true)],
            'price_includes_tax' => ['boolean'],
            'min_order_qty' => ['required', ...$qty],
            'order_step' => ['required', ...$qty],
            'critical_stock_level' => ['required', 'decimal:0,3', 'min:0', 'max:99999999999'],
            'option_name' => ['nullable', 'string', 'max:40'],
            // Seçenekli üründe (aynı ürünün başka seçenekleri varsa) değer zorunludur.
            'option_value' => [$product?->variant_group_id ? 'required' : 'nullable', 'required_with:option_name', 'string', 'max:60'],
            'is_active' => ['boolean'],
            'visible_to_dealers' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sku' => 'ürün kodu (SKU)', 'barcode' => 'barkod', 'name' => 'ürün adı', 'category_id' => 'kategori',
            'brand_id' => 'marka', 'unit_id' => 'birim', 'sale_mode' => 'satış şekli', 'min_cases' => 'en az koli',
            'pack_quantity' => 'koli içi adet',
            'purchase_price' => 'alış fiyatı', 'list_price' => 'bayi fiyatı',
            'dealer_sale_price' => 'indirimli bayi fiyatı', 'dealer_sale_starts_on' => 'indirim başlangıcı', 'dealer_sale_ends_on' => 'indirim bitişi',
            'tax_rate_id' => 'KDV oranı', 'min_order_qty' => 'minimum sipariş miktarı', 'order_step' => 'sipariş artış adımı',
            'critical_stock_level' => 'kritik stok seviyesi', 'option_name' => 'seçenek adı', 'option_value' => 'seçenek değeri',
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
        // Birim seçilmez: yeni ürün adettir; mevcut ürün birimini korur (stoğun ve fiyatın anlamı değişmez).
        $input['unit_id'] = $product?->unit_id ?? $this->defaultUnitId();
        $input = $this->applySaleMode($input);

        $validator = Validator::make($input, $this->rules($product), [], $this->attributes());

        $validator->after(function ($v) use ($input) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            if ($input['sale_mode'] === 'case' && bccomp((string) $input['pack_quantity'], '2', 3) < 0) {
                $v->errors()->add('pack_quantity', 'Koli içi en az 2 adet olmalıdır; tek tek satılan ürün için "Tek tek" seçin.');
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

            // İndirimli bayi fiyatı, indirimsiz bayi fiyatından (yoksa liste fiyatından) düşük olmalı.
            if (filled($input['dealer_sale_price'] ?? null)) {
                $regular = (string) $input['list_price'];
                if (bccomp((string) $input['dealer_sale_price'], $regular, 4) >= 0) {
                    $v->errors()->add('dealer_sale_price', 'İndirimli bayi fiyatı, bayi fiyatından düşük olmalıdır.');
                }
            }
        });

        $data = $validator->validate();
        unset($data['min_cases']); // yalnızca min_order_qty hesabı için
        // Tek satış fiyatı: bayi fiyatı list_price'tadır; eski ayrı bayi fiyatı kaydedilince boşalır.
        $data['dealer_price'] = null;
        // İndirimli fiyat yoksa tarihleri de boşalır (eski tarih yeni indirime taşınmasın).
        if (array_key_exists('dealer_sale_price', $data) && blank($data['dealer_sale_price'])) {
            $data['dealer_sale_starts_on'] = $data['dealer_sale_ends_on'] = null;
        }
        foreach (['option_name', 'option_value'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field]) ? trim((string) $data[$field]) : null;
            }
        }
        // Aynı ürünün iki seçeneği aynı değeri taşımaz (ör. iki "Kırmızı").
        if ($product?->variant_group_id && filled($data['option_value'] ?? null)) {
            $this->assertOptionValueFree($product->variant_group_id, $data['option_value'], $product->id);
        }

        return $data;
    }

    /** İşletmenin "Adet" birimi (yoksa oluşturulur). */
    private function defaultUnitId(): int
    {
        return Unit::where('code', 'ADET')->value('id')
            ?? Unit::create(['code' => 'ADET', 'name' => 'Adet', 'allows_fraction' => false])->id;
    }

    /**
     * Satış şeklini sipariş kurallarına çevirir (docs/03-is-kurallari.md §2.1):
     *  - single: tek tek; isteğe bağlı en az miktar, 1'er artar.
     *  - case:   koli halinde; artış = koli içi, en az = koli içi × en az koli.
     *  - custom: en az X, Y'er artar (koli içi 1).
     * Şekil gönderilmediyse (içe aktarma, eski kayıt) kurallardan çıkarılır.
     */
    private function applySaleMode(array $input): array
    {
        $num = fn ($v) => is_scalar($v) && is_numeric((string) $v) ? (string) $v : null;
        $mode = $input['sale_mode'] ?? null;

        if (! is_string($mode) || ! isset(Product::SALE_MODES[$mode])) {
            if ($mode !== null) {
                return $input; // geçersiz değer: doğrulama hata verir
            }
            $pack = $num($input['pack_quantity']);
            $step = $num($input['order_step']);
            $input['sale_mode'] = match (true) {
                $pack !== null && $step !== null && bccomp($pack, '1', 3) > 0 && bccomp($pack, $step, 3) === 0 => 'case',
                $step !== null && bccomp($step, '1', 3) > 0 => 'custom',
                default => 'single',
            };

            return $input;
        }

        if ($mode === 'single') {
            $input['pack_quantity'] = 1;
            $input['order_step'] = 1;
        } elseif ($mode === 'custom') {
            $input['pack_quantity'] = 1;
        } else {
            $input['order_step'] = $input['pack_quantity'];
            $cases = $input['min_cases'] ?? 1;
            $pack = $num($input['pack_quantity']);
            $input['min_order_qty'] = $pack !== null && is_scalar($cases) && ctype_digit((string) $cases) && (int) $cases >= 1
                ? bcmul($pack, (string) (int) $cases, 3)
                : $input['pack_quantity']; // en az koli hatalıysa doğrulama min_cases için hata verir
        }

        return $input;
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
            // Seçenek adı (ör. Renk) aynı ürünün tüm seçeneklerinde ortaktır.
            if ($product->variant_group_id && $product->isDirty('option_name')) {
                Product::where('variant_group_id', $product->variant_group_id)->whereKeyNot($product->id)
                    ->update(['option_name' => $product->option_name]);
            }
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

    /**
     * Ürüne yeni seçenek ekler (ör. aynı defterin kırmızısı). Yeni seçenek kendi kodu, stoğu ve fiyatı olan
     * ayrı üründür; kaynakla arka planda variant_group_id ile bağlanır (grup kaydı otomatik açılır, toptancı görmez).
     * Kaynağın henüz değeri yoksa $sourceValue ile verilir (ör. mevcut ürün "Mavi").
     *
     * @throws ValidationException
     */
    public function createOption(Product $source, array $data, ?string $sourceValue = null): Product
    {
        $name = trim((string) ($data['option_name'] ?? '')) ?: null;
        $value = trim((string) ($data['option_value'] ?? ''));
        $sourceValue = trim((string) $sourceValue);
        $errors = [];
        if ($name === null) {
            $errors['option_name'] = 'Seçenek adını yazın (ör. Renk).';
        }
        if ($value === '') {
            $errors['option_value'] = 'Yeni seçeneğin değerini yazın (ör. Kırmızı).';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($source, $data, $name, $value, $sourceValue) {
            $source = Product::lockForUpdate()->findOrFail($source->id);
            abort_if($source->isArchived(), 409, 'Arşivlenmiş ürüne seçenek eklenemez.');

            if (blank($source->option_value)) {
                if ($sourceValue === '' || mb_strlen($sourceValue) > 60) {
                    throw ValidationException::withMessages(['source_option_value' => "“{$source->name}” ürününün kendi değerini yazın (ör. Mavi; en fazla 60 karakter)."]);
                }
                $source->option_value = $sourceValue;
            }
            if (! $source->variant_group_id) {
                $source->variant_group_id = VariantGroup::create(['name' => mb_substr($source->name, 0, 160), 'attribute_names' => [$name]])->id;
            }
            $source->option_name = $name;
            $source->save();

            $this->assertOptionValueFree($source->variant_group_id, $value, null);
            Product::where('variant_group_id', $source->variant_group_id)->update(['option_name' => $name]);

            $product = $this->create(['option_name' => $name, 'option_value' => $value] + $data);
            $product->forceFill(['variant_group_id' => $source->variant_group_id])->save();
            $this->audit->log('product.option_added', $product, [], ['source_product_id' => $source->id, 'option_value' => $value]);

            return $product;
        });
    }

    /** Ürünü seçeneklerinden ayırır; tek kalan seçenek de bağımsız olur. */
    public function detachOption(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $groupId = $product->variant_group_id;
            if (! $groupId) {
                return;
            }
            $product->forceFill(['variant_group_id' => null])->save();
            if (Product::where('variant_group_id', $groupId)->count() === 1) {
                Product::where('variant_group_id', $groupId)->update(['variant_group_id' => null]);
            }
            $this->audit->log('product.option_detached', $product, ['variant_group_id' => $groupId], []);
        });
    }

    private function assertOptionValueFree(int $groupId, string $value, ?int $exceptId): void
    {
        $taken = Product::where('variant_group_id', $groupId)->where('option_value', $value)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['option_value' => "“{$value}” bu ürünün başka bir seçeneğinde zaten var."]);
        }
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

    /**
     * Ürün kalıcı olarak silinir: stok hareketleri ve depo stoğuyla birlikte (mağazadaki gibi). Stok hareketleri normalde
     * yalnızca eklenir; ürün tümüyle silindiğinde hareket geçmişi de onunla kalkar, silinen stok ve hareket sayısı işlem
     * geçmişine yazılır. Siparişler etkilenmez: kalem ürünün adını, kodunu, birimini, fiyatını ve KDV'sini kendi
     * üzerinde saklar, yalnızca ürün bağlantısı boşalır. Açık sipariş veya açık iade talebindeki ürün silinmez
     * (onay, sevk ve iadede ürün gerekir). Ürün satırı kilitlenir; aynı anda gönderilen sipariş ile çakışmaz.
     *
     * @throws DomainException
     */
    public function delete(Product $product): void
    {
        $paths = $product->images()->pluck('path');

        DB::transaction(function () use ($product) {
            // Kilit sırası sipariş gönderimiyle aynı (önce sepet satırları, sonra ürün): aynı anda gönderilen sepet ya
            // önce tamamlanır ve açık sipariş olarak görülür ya da ürün sepetten kalkmış olarak gönderilir.
            CartItem::where('product_id', $product->id)->lockForUpdate()->get();
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            // Kilitli okuma: işlemin başındaki anlık görüntü değil, son gönderilen siparişler sayılır.
            $open = Order::whereIn('status', [...Order::PENDING, ...Order::ACTIVE_FULFILMENT])
                ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
                ->lockForUpdate()->orderBy('id')->pluck('order_no');
            if ($open->isNotEmpty()) {
                throw new DomainException('Bu ürün '.$open->count().' açık siparişte ('.$open->take(5)->implode(', ').($open->count() > 5 ? '…' : '')
                    .'). Siparişler teslim edilince, reddedilince veya iptal edilince silebilirsiniz; şimdilik arşivleyebilirsiniz.');
            }
            $openReturns = OrderReturnRequest::whereIn('status', OrderReturnRequest::OPEN)
                ->whereHas('items.orderItem', fn ($q) => $q->where('product_id', $product->id))->lockForUpdate()->count();
            if ($openReturns > 0) {
                throw new DomainException('Bu ürün açık bir iade talebinde. Talep sonuçlanınca silebilirsiniz.');
            }

            $stock = (string) WarehouseStock::where('product_id', $product->id)->sum('quantity');
            // Toplu silme: StockMovement modelindeki "silinemez" koruması tek kayıt içindir; burada ürünle birlikte kalkar.
            $movements = StockMovement::where('product_id', $product->id)->delete();
            WarehouseStock::where('product_id', $product->id)->delete();
            StockCountLine::where('product_id', $product->id)->delete();
            CartItem::where('product_id', $product->id)->delete();
            ProductRequest::where('product_id', $product->id)->delete();
            DealerProductPrice::where('product_id', $product->id)->delete();
            PriceListItem::where('product_id', $product->id)->delete();
            DiscountRule::where('scope', 'product')->where('product_id', $product->id)->delete();
            $orderLines = OrderItem::where('product_id', $product->id)->update(['product_id' => null]);
            Promotion::whereJsonContains('product_ids', $product->id)->get()->each(function (Promotion $promotion) use ($product) {
                $ids = array_values(array_diff(array_map('intval', $promotion->product_ids ?? []), [$product->id]));
                $promotion->update(['product_ids' => $ids ?: null]);
            });

            $product->delete(); // görseller ve vitrin kayıtları veritabanında birlikte silinir
            $this->audit->log('product.deleted', $product, [
                'sku' => $product->sku, 'name' => $product->name, 'stock' => $stock, 'stock_movements' => $movements, 'order_lines' => $orderLines,
            ]);
        });

        foreach ($paths as $path) {
            $this->images->delete($path);
            app(ImageVariants::class)->forget($path);
        }
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

        $image = ProductImage::create([
            'product_id' => $product->id,
            'path' => $path,
            'sort' => (int) $product->images()->max('sort') + 1,
            'is_primary' => ! $product->images()->exists(),
        ]);
        GenerateImageVariants::dispatch($path)->afterCommit();

        return $image;
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
        app(ImageVariants::class)->forget($image->path);
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
