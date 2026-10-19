<?php

namespace App\Domain\Storefront;

use App\Domain\Audit\AuditLogger;
use App\Domain\Tenancy\TenantContext;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomeBanner;
use App\Models\Product;
use App\Models\Showcase;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Ana sayfa slaytları: oluşturma, düzenleme, sıralama, silme. Medya dosyaları kayıtla birlikte yönetilir;
 * bir dosya reddedilirse kayıt oluşmaz, yeni dosya kaydedilemezse eski dosya silinmez.
 */
class BannerService
{
    public const MAX_BANNERS = 12;

    public function __construct(private BannerMedia $media, private TenantContext $tenant, private AuditLogger $audit) {}

    /** @param  array{media?: UploadedFile, mobile_media?: UploadedFile, poster?: UploadedFile}  $files */
    public function create(array $data, array $files): HomeBanner
    {
        if (HomeBanner::count() >= self::MAX_BANNERS) {
            throw new DomainException('En fazla '.self::MAX_BANNERS.' slayt eklenebilir. Kullanmadığınız slaytları silin.');
        }
        if (empty($files['media'])) {
            throw new DomainException('Slayt için bir görsel veya video seçin.');
        }

        $this->assertLinkTarget($data['link_type'] ?? 'none', $data['link_id'] ?? null);
        $stored = [];

        try {
            $banner = DB::transaction(function () use ($data, $files, &$stored) {
                $main = $stored[] = $this->media->store($files['media'], $this->directory());

                return HomeBanner::create($this->attributes($data) + [
                    'media_type' => $main['type'], 'media_path' => $main['path'], 'media_mime' => $main['mime'], 'media_size' => $main['size'],
                    'mobile_media_path' => ! empty($files['mobile_media']) ? ($stored[] = ['path' => $this->media->storeImage($files['mobile_media'], $this->directory())])['path'] : null,
                    'poster_path' => ! empty($files['poster']) ? ($stored[] = ['path' => $this->media->storeImage($files['poster'], $this->directory())])['path'] : null,
                    'sort' => (int) HomeBanner::max('sort') + 1,
                ]);
            });
        } catch (\Throwable $e) {
            foreach ($stored as $file) {
                $this->media->delete($file['path']);
            }

            throw $e;
        }

        $this->audit->log('storefront.banner_created', $banner, [], ['media_type' => $banner->media_type]);

        return $banner;
    }

    /** @param  array{media?: UploadedFile, mobile_media?: UploadedFile, poster?: UploadedFile}  $files */
    public function update(HomeBanner $banner, array $data, array $files, bool $removeMobile = false, bool $removePoster = false): HomeBanner
    {
        $this->assertLinkTarget($data['link_type'] ?? 'none', $data['link_id'] ?? null);
        $old = [];
        $changes = $this->attributes($data);

        if (! empty($files['media'])) {
            $main = $this->media->store($files['media'], $this->directory());
            $old[] = $banner->media_path;
            $changes += ['media_type' => $main['type'], 'media_path' => $main['path'], 'media_mime' => $main['mime'], 'media_size' => $main['size']];
            if ($main['type'] === 'image' && $banner->poster_path) {
                $old[] = $banner->poster_path;
                $changes['poster_path'] = null;
            }
        }
        if (! empty($files['mobile_media'])) {
            $old[] = $banner->mobile_media_path;
            $changes['mobile_media_path'] = $this->media->storeImage($files['mobile_media'], $this->directory());
        } elseif ($removeMobile) {
            $old[] = $banner->mobile_media_path;
            $changes['mobile_media_path'] = null;
        }
        if (! empty($files['poster'])) {
            $old[] = $banner->poster_path;
            $changes['poster_path'] = $this->media->storeImage($files['poster'], $this->directory());
        } elseif ($removePoster) {
            $old[] = $banner->poster_path;
            $changes['poster_path'] = null;
        }

        $banner->update($changes);
        foreach (array_filter($old) as $path) {
            $this->media->delete($path);
        }
        $this->audit->log('storefront.banner_updated', $banner);

        return $banner;
    }

    public function delete(HomeBanner $banner): void
    {
        $paths = [$banner->media_path, $banner->mobile_media_path, $banner->poster_path];
        $banner->delete();
        foreach ($paths as $path) {
            $this->media->delete($path);
        }
        $this->audit->log('storefront.banner_deleted', null, ['id' => $banner->id, 'title' => $banner->title]);
    }

    /** @param  int[]  $ids  yeni sıra */
    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                HomeBanner::whereKey($id)->update(['sort' => $i + 1]);
            }
        });
    }

    private function attributes(array $data): array
    {
        $linkType = $data['link_type'] ?? 'none';

        return [
            'placement' => $data['placement'] ?? 'hero',
            'title' => $data['title'] ?? null,
            'subtitle' => $data['subtitle'] ?? null,
            'button_label' => $linkType === 'none' ? null : ($data['button_label'] ?? null),
            'link_type' => $linkType,
            'link_id' => in_array($linkType, ['none', 'catalog'], true) ? null : ($data['link_id'] ?? null),
            'text_position' => $data['text_position'] ?? 'left',
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'dealer_group_id' => $data['dealer_group_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /** Bağlantı hedefi bu işletmeye ait olmalı (işletme kapsamı sorguları zaten sınırlar). */
    private function assertLinkTarget(string $type, $id): void
    {
        $exists = match ($type) {
            'none', 'catalog' => true,
            'category' => Category::whereKey($id)->exists(),
            'brand' => Brand::whereKey($id)->exists(),
            'product' => Product::whereKey($id)->exists(),
            'showcase' => Showcase::whereKey($id)->exists(),
            default => false,
        };

        if (! $exists) {
            throw new DomainException('Bağlantı hedefi bulunamadı; listeden yeniden seçin.');
        }
    }

    private function directory(): string
    {
        return 'businesses/'.$this->tenant->businessId().'/storefront';
    }
}
