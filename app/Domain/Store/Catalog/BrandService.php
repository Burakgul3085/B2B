<?php

namespace App\Domain\Store\Catalog;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\ImageUploader;
use App\Models\Store\StoreBrand;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/** Mağaza markaları ve logoları. Ürünü olan marka silinemez (pasif yapılabilir). */
class BrandService
{
    public function __construct(private AuditLogger $audit, private ImageUploader $images) {}

    public function create(array $input): StoreBrand
    {
        $data = $this->validate($input);
        $brand = StoreBrand::create($data + ['slug' => Slugs::unique(StoreBrand::class, $data['name'])]);
        $this->audit->log('store.brand_created', $brand, [], ['name' => $brand->name]);

        return $brand;
    }

    public function update(StoreBrand $brand, array $input): void
    {
        $data = $this->validate($input, $brand);
        $old = $brand->only(array_keys($data));
        $brand->update($data);
        $this->audit->log('store.brand_updated', $brand, $old, $brand->only(array_keys($data)));
    }

    /** @throws DomainException */
    public function delete(StoreBrand $brand): void
    {
        if ($brand->products()->exists()) {
            throw new DomainException('Bu markaya ait ürün var. Markayı pasif yapabilirsiniz.');
        }

        $this->images->delete($brand->logo_path);
        $brand->delete();
        $this->audit->log('store.brand_deleted', $brand, ['name' => $brand->name]);
    }

    /** @throws DomainException */
    public function setLogo(StoreBrand $brand, UploadedFile $file): void
    {
        $path = $this->images->store($file, 'store/brands', 480, 2 * 1024 * 1024, 90, trimWhitespace: true);
        $this->images->delete($brand->logo_path);
        $brand->forceFill(['logo_path' => $path])->save();
        $this->audit->log('store.brand_logo_updated', $brand);
    }

    public function removeLogo(StoreBrand $brand): void
    {
        $this->images->delete($brand->logo_path);
        $brand->forceFill(['logo_path' => null])->save();
        $this->audit->log('store.brand_logo_removed', $brand);
    }

    private function validate(array $input, ?StoreBrand $brand = null): array
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120', 'unique:store_brands,name'.($brand ? ','.$brand->id : '')],
        ], [], ['name' => 'marka adı'])->validate();

        return ['name' => trim($data['name']), 'is_active' => (bool) ($input['is_active'] ?? true)];
    }
}
