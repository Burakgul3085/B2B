<?php

namespace App\Domain\Support;

use Illuminate\Http\UploadedFile;

/**
 * Hazır marka kütüphanesi: kırtasiye, ofis, parti ve oyuncak markaları ve logoları (resources/brand-library).
 * Kodla birlikte gelen sabit dosyalardır; veritabanında tutulmaz. Mağaza ve B2B marka ekranları buradan öneri gösterir;
 * seçilen marka her tarafın kendi marka tablosuna, logosu her tarafın kendi görsel kuralıyla (yeniden kodlanarak)
 * kopyalanır. Böylece iki taraf veri paylaşmaz. Logolar markaların tescilli işaretleridir; yalnızca satılan ürünün
 * markasını göstermek için kullanılır.
 */
class BrandLibrary
{
    public const CATEGORIES = ['kirtasiye' => 'Kırtasiye', 'ofis' => 'Ofis', 'parti' => 'Parti', 'oyuncak' => 'Oyuncak'];

    /** @var array<string, array{name: string, slug: string, categories: list<string>, logo: ?string}>|null */
    private ?array $brands = null;

    public function __construct(private ?string $directory = null) {}

    public function directory(): string
    {
        return $this->directory ?? resource_path('brand-library');
    }

    /** @return array<string, array{name: string, slug: string, categories: list<string>, logo: ?string}> slug => marka */
    public function all(): array
    {
        if ($this->brands === null) {
            $file = $this->directory().'/brands.json';
            $list = is_file($file) ? json_decode((string) file_get_contents($file), true, 8, JSON_THROW_ON_ERROR) : [];
            $this->brands = [];
            foreach ($list as $brand) {
                $this->brands[$brand['slug']] = $brand;
            }
        }

        return $this->brands;
    }

    public function find(?string $slug): ?array
    {
        return is_string($slug) && preg_match('/^[a-z0-9-]{1,80}$/', $slug) ? ($this->all()[$slug] ?? null) : null;
    }

    /** Logo dosyasının tam yolu (yalnızca kütüphanedeki, var olan dosya). */
    public function logoPath(?string $slug): ?string
    {
        $brand = $this->find($slug);
        if (! $brand || ! $brand['logo'] || ! preg_match('/^[a-z0-9-]+\.(webp|png)$/', $brand['logo'])) {
            return null;
        }
        $path = $this->directory().'/logos/'.$brand['logo'];

        return is_file($path) ? $path : null;
    }

    /** Logo, görsel yükleyicisine verilecek dosya olarak (yükleyici yeniden kodlar ve doğrular). */
    public function logoUpload(?string $slug): ?UploadedFile
    {
        $path = $this->logoPath($slug);

        return $path ? new UploadedFile($path, basename($path), mime_content_type($path) ?: null, null, true) : null;
    }

    /**
     * Ekranda öneri listesi için hafif liste.
     *
     * @param  callable(string): string  $logoUrl  slug => önizleme adresi
     * @return list<array{n: string, s: string, c: list<string>, l: ?string}>
     */
    public function suggestions(callable $logoUrl): array
    {
        return array_values(array_map(fn (array $b) => [
            'n' => $b['name'], 's' => $b['slug'], 'c' => $b['categories'], 'l' => $b['logo'] ? $logoUrl($b['slug']) : null,
        ], $this->all()));
    }
}
