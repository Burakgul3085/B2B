<?php

namespace App\Domain\Pricing;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\ImageUploader;
use App\Domain\Files\ImageVariants;
use App\Domain\Support\Decimal;
use App\Domain\Tenancy\TenantContext;
use App\Jobs\GenerateImageVariants;
use App\Models\Cart;
use App\Models\Category;
use App\Models\DealerGroup;
use App\Models\DealerRelationship;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionImage;
use App\Models\PromotionRedemption;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Toptancı panelinde kupon / kampanya kaydı (kurallar: PromotionService). Seçilen bayi, grup, kategori ve ürünler
 * işletme kapsamındaki kayıtlarla süzülür; başka işletmenin kimliği yazılamaz.
 */
class PromotionAdmin
{
    /** Kampanya başına en fazla detay fotoğrafı (kapak hariç). */
    public const MAX_IMAGES = 8;

    public function __construct(private AuditLogger $audit, private ImageUploader $uploader, private TenantContext $tenant) {}

    /** @throws ValidationException|DomainException */
    public function save(array $input, ?Promotion $promotion = null): Promotion
    {
        $isCoupon = ($input['kind'] ?? 'coupon') === 'coupon';
        $input['code'] = $isCoupon ? PromotionService::normalizeCode($input['code'] ?? null) : null;

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'in:coupon,campaign'],
            'code' => [$isCoupon ? 'required' : 'nullable', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/'],
            'type' => ['required', Rule::in(array_keys(Promotion::TYPES))],
            'value' => ['required', 'string', 'max:20'],
            'max_discount' => ['nullable', 'string', 'max:20'],
            'min_subtotal' => ['nullable', 'string', 'max:20'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_dealer_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'target' => ['required', Rule::in(array_keys(Promotion::TARGETS))],
            'dealer_group_id' => ['nullable', 'integer'],
            'dealer_ids' => ['nullable', 'array', 'max:1000'],
            'dealer_ids.*' => ['integer'],
            'category_ids' => ['nullable', 'array', 'max:500'],
            'category_ids.*' => ['integer'],
            'product_skus' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover' => ['nullable', 'file', 'max:5120'],
            'remove_cover' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'images.*' => ['file', 'max:5120'],
            'remove_images' => ['nullable', 'array', 'max:100'],
            'remove_images.*' => ['integer'],
            'image_sort' => ['nullable', 'array', 'max:100'],
            'image_sort.*' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ], ['code.regex' => 'Kupon kodu yalnızca harf, rakam, - ve _ içerebilir (Türkçe karakter olmadan).'], [
            'name' => 'ad', 'code' => 'kupon kodu', 'type' => 'indirim türü', 'value' => 'indirim', 'max_discount' => 'en fazla indirim',
            'min_subtotal' => 'en düşük sipariş tutarı', 'starts_at' => 'başlangıç', 'ends_at' => 'bitiş', 'usage_limit' => 'toplam kullanım',
            'per_dealer_limit' => 'bayi başına kullanım', 'target' => 'kime', 'dealer_group_id' => 'bayi grubu', 'dealer_ids' => 'bayiler',
            'category_ids' => 'kategoriler', 'product_skus' => 'ürün kodları', 'description' => 'açıklama',
            'cover' => 'kapak fotoğrafı', 'images' => 'detay fotoğrafları', 'images.*' => 'detay fotoğrafı',
        ])->validate();

        $errors = [];
        $money = fn (string $key) => filled($data[$key] ?? null) ? Decimal::parse($data[$key], 2) : null;
        $value = $money('value');
        if ($value === null || bccomp($value, '0', 2) <= 0) {
            $errors['value'] = 'Geçerli bir indirim değeri girin.';
        } elseif ($data['type'] === 'percent' && bccomp($value, '100', 2) >= 0) {
            $errors['value'] = 'Yüzde indirim 100\'den küçük olmalıdır.';
        }
        foreach (['min_subtotal', 'max_discount'] as $key) {
            if (filled($data[$key] ?? null) && ($money($key) === null || bccomp($money($key), '0', 2) <= 0)) {
                $errors[$key] = 'Geçerli bir tutar girin (ör. 250,00).';
            }
        }
        // Tutar indirimi en düşük sipariş tutarı olmadan küçük siparişi neredeyse bedava yapar.
        if ($data['type'] === 'fixed' && $value !== null && ! isset($errors['value'])) {
            $min = $money('min_subtotal');
            if ($min === null || bccomp($min, $value, 2) <= 0) {
                $errors['min_subtotal'] = 'Tutar indiriminde en düşük sipariş tutarı zorunludur ve indirimden büyük olmalıdır (ör. ₺100 indirim için ₺2.000).';
            }
        }
        if ($data['code'] !== null && Promotion::where('code', $data['code'])->when($promotion, fn ($q) => $q->whereKeyNot($promotion->id))->exists()) {
            $errors['code'] = 'Bu kupon kodu zaten kullanılıyor.';
        }

        // Hedef: yalnızca bu işletmenin grubu / bayileri (global işletme kapsamı süzer).
        $groupId = null;
        $dealerIds = [];
        if ($data['target'] === 'group') {
            $groupId = DealerGroup::whereKey((int) ($data['dealer_group_id'] ?? 0))->value('id');
            if (! $groupId) {
                $errors['dealer_group_id'] = 'Bayi grubu seçin.';
            }
        } elseif ($data['target'] === 'dealers') {
            $dealerIds = DealerRelationship::whereIn('id', array_map('intval', $data['dealer_ids'] ?? []))->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            if ($dealerIds === []) {
                $errors['dealer_ids'] = 'En az bir bayi seçin.';
            }
        }

        // Kapsam: kategoriler (alt kategorileriyle) ve stok koduyla ürünler.
        $categoryIds = Category::whereIn('id', array_map('intval', $data['category_ids'] ?? []))->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $skus = array_values(array_unique(array_filter(array_map(fn ($v) => mb_strtoupper(trim($v), 'UTF-8'), preg_split('/[\s,;]+/', (string) ($data['product_skus'] ?? ''))))));
        $found = $skus ? Product::whereIn('sku', $skus)->pluck('id', 'sku') : collect();
        $unknown = array_diff($skus, $found->keys()->map(fn ($v) => mb_strtoupper($v, 'UTF-8'))->all());
        if ($unknown) {
            $errors['product_skus'] = 'Bulunamayan stok kodu: '.implode(', ', array_slice($unknown, 0, 10)).'.';
        }

        // Görseller: yalnızca bu kampanyanın kayıtları (başka kampanyanın görsel numarası yok sayılır).
        $existing = $promotion ? $promotion->images()->get() : collect();
        $removeIds = array_map('intval', $data['remove_images'] ?? []);
        $newFiles = array_values(array_filter($data['images'] ?? [], fn ($f) => $f instanceof UploadedFile));
        $details = $existing->where('is_cover', false)->reject(fn ($i) => in_array($i->id, $removeIds, true));
        if ($details->count() + count($newFiles) > self::MAX_IMAGES) {
            $errors['images'] = 'En fazla '.self::MAX_IMAGES.' detay fotoğrafı eklenebilir; önce bazılarını kaldırın.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $used = $promotion && $promotion->redemptions()->exists();
        if ($used && ($promotion->code !== $data['code'] || $promotion->type !== $data['type'])) {
            throw new DomainException('Kullanılmış bir kupon / kampanyanın kodu ve türü değiştirilemez. Yeni bir tane oluşturun.');
        }

        $values = [
            'name' => trim($data['name']),
            'code' => $data['code'],
            'type' => $data['type'],
            'value' => $value,
            'max_discount' => $data['type'] === 'percent' ? $money('max_discount') : null,
            'min_subtotal' => $money('min_subtotal'),
            'starts_at' => filled($data['starts_at'] ?? null) ? Carbon::parse($data['starts_at'], 'Europe/Istanbul')->utc() : null,
            'ends_at' => filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at'], 'Europe/Istanbul')->utc() : null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'per_dealer_limit' => $data['per_dealer_limit'] ?? null,
            'target' => $data['target'],
            'dealer_group_id' => $groupId,
            'dealer_ids' => $dealerIds ?: null,
            'category_ids' => $categoryIds ?: null,
            'product_ids' => $found->values()->map(fn ($id) => (int) $id)->all() ?: null,
            'description' => filled($data['description'] ?? null) ? trim(str_replace("\r\n", "\n", $data['description'])) : null,
            'is_active' => filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOL),
            // Otomatik kampanya hedef bayilerin Kampanyalar sayfasında hep görünür; kupon kodu (kişiye özel verilebilir)
            // yalnızca toptancı seçerse görünür.
            'show_to_dealers' => ! $isCoupon || filter_var($input['show_to_dealers'] ?? false, FILTER_VALIDATE_BOOL),
        ];

        // Dosyalar önce diske yazılır (içerik denetimi burada); kayıt başarısız olursa geri silinir.
        $stored = [];
        try {
            $cover = ($data['cover'] ?? null) instanceof UploadedFile ? $stored[] = $this->store($data['cover'], 'cover') : null;
            $added = [];
            foreach ($newFiles as $file) {
                $added[] = $stored[] = $this->store($file, 'images');
            }
        } catch (ValidationException $e) {
            array_walk($stored, fn ($path) => $this->uploader->delete($path));
            throw $e;
        }

        $dropped = $existing->filter(fn (PromotionImage $i) => $i->is_cover
            ? $cover !== null || filter_var($data['remove_cover'] ?? false, FILTER_VALIDATE_BOOL)
            : in_array($i->id, $removeIds, true));

        try {
            $promotion = DB::transaction(function () use ($promotion, $values, $cover, $added, $dropped, $details, $data) {
                if ($promotion) {
                    $old = $promotion->only(['name', 'code', 'type', 'value', 'target', 'is_active']);
                    $promotion->update($values);
                    $this->audit->log('pricing.promotion_updated', $promotion, $old, $promotion->only(array_keys($old)));
                } else {
                    $promotion = Promotion::create($values);
                    $this->audit->log('pricing.promotion_created', $promotion, [], ['name' => $promotion->name, 'code' => $promotion->code]);
                }

                PromotionImage::whereIn('id', $dropped->pluck('id')->all())->delete();
                foreach ($details as $image) {
                    $sort = $data['image_sort'][$image->id] ?? null;
                    if ($sort !== null && (int) $sort !== $image->sort) {
                        $image->update(['sort' => (int) $sort]);
                    }
                }
                if ($cover) {
                    $promotion->images()->create(['path' => $cover, 'is_cover' => true]);
                }
                $next = (int) $details->max('sort');
                foreach ($added as $path) {
                    $promotion->images()->create(['path' => $path, 'sort' => ++$next]);
                }

                return $promotion;
            });
        } catch (\Throwable $e) {
            array_walk($stored, fn ($path) => $this->uploader->delete($path));
            throw $e;
        }

        $this->forget($dropped->pluck('path')->all());
        foreach ($stored as $path) {
            GenerateImageVariants::dispatch($path);
        }

        return $promotion;
    }

    public function toggle(Promotion $promotion): void
    {
        $promotion->update(['is_active' => ! $promotion->is_active]);
        $this->audit->log('pricing.promotion_toggled', $promotion, [], ['is_active' => $promotion->is_active]);
    }

    /** Kullanılmış kupon silinmez (siparişlerde kaydı var); pasife alınır. */
    /**
     * Her zaman silinebilir (kullanılmış olsa da). Sipariş, uygulanan indirimin anlık kopyasını kendi satırında taşır
     * (orders.promotion_*); bekleyen siparişin onayı da bu kopyadan hesaplanır. Kullanım kayıtları yalnızca kullanım
     * sınırı içindir, kampanyayla birlikte silinir. Sipariş gönderimi kampanya satırını kilitlediği için önce o kilitlenir.
     */
    public function delete(Promotion $promotion): void
    {
        $paths = $promotion->images()->pluck('path')->all();

        DB::transaction(function () use ($promotion) {
            $promotion = Promotion::whereKey($promotion->id)->lockForUpdate()->firstOrFail();
            $used = PromotionRedemption::where('promotion_id', $promotion->id)->whereNull('released_at')->count();
            PromotionRedemption::where('promotion_id', $promotion->id)->delete();
            if ($promotion->code !== null) {
                Cart::where('promotion_code', $promotion->code)->update(['promotion_code' => null]);
            }
            $promotion->delete(); // görsel kayıtları veritabanında birlikte silinir
            $this->audit->log('pricing.promotion_deleted', $promotion, ['name' => $promotion->name, 'code' => $promotion->code, 'used' => $used]);
        });

        $this->forget($paths);
    }

    /** Yüklenen görseli denetleyip private diske yazar; geçersiz dosya alanında hata olarak döner. */
    private function store(UploadedFile $file, string $field): string
    {
        try {
            return $this->uploader->store($file, 'businesses/'.$this->tenant->businessId().'/promotions', maxEdge: 1800, quality: 85);
        } catch (DomainException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }

    private function forget(array $paths): void
    {
        foreach ($paths as $path) {
            $this->uploader->delete($path);
            app(ImageVariants::class)->forget($path);
        }
    }
}
