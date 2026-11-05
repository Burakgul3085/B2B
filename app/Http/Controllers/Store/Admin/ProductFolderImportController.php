<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Catalog\CreateStoreProduct;
use App\Domain\Store\Catalog\FolderImport;
use App\Domain\Store\Catalog\ProductService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProductVariant;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mağazada klasörden toplu ürün ekleme. Klasör tarayıcıda okunur ve ürün ürün gönderilir; sunucu her ürünü
 * tek ürün ekleme ekranıyla aynı kurallarla doğrular (fiyat, KDV, kategori ve marka sunucuda denetlenir).
 */
class ProductFolderImportController extends Controller
{
    public function __construct(private FolderImport $import) {}

    public function index(): View
    {
        return view('store.admin.catalog.folder-import', [
            'vatRates' => StoreProductVariant::VAT_RATES,
            'maxImages' => ProductService::MAX_IMAGES,
            'maxDepth' => StoreCategory::MAX_DEPTH,
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'skus' => ['present', 'array', 'max:'.FolderImport::MAX_LOOKUP],
            'skus.*' => ['string', 'max:64'],
            'barcodes' => ['present', 'array', 'max:'.FolderImport::MAX_LOOKUP],
            'barcodes.*' => ['string', 'max:64'],
        ]);

        return response()->json($this->import->lookup($data['skus'], $data['barcodes']));
    }

    public function prepare(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categories' => ['present', 'array', 'max:'.FolderImport::MAX_LOOKUP],
            'categories.*' => ['array', 'min:1', 'max:'.StoreCategory::MAX_DEPTH],
            'categories.*.*' => ['string', 'min:1', 'max:120'],
            'brands' => ['present', 'array', 'max:'.FolderImport::MAX_LOOKUP],
            'brands.*' => ['string', 'min:1', 'max:120'],
            'create' => ['boolean'],
        ], ['categories.*.max' => 'Kategori en fazla '.StoreCategory::MAX_DEPTH.' seviye olabilir.']);

        $trim = fn (array $names) => array_values(array_map(fn ($n) => trim($n), $names));

        try {
            return response()->json($this->import->prepare(
                array_map($trim, $data['categories']),
                $trim($data['brands']),
                $request->boolean('create'),
            ));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'images' => ['nullable', 'array', 'max:'.ProductService::MAX_IMAGES],
            'images.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:'.StoreStockService::MAX_QUANTITY],
            'options' => ['nullable', 'array', 'max:'.CreateStoreProduct::MAX_OPTIONS],
            'options.*' => ['array'],
            'options.*.stock' => ['nullable', 'integer', 'min:0', 'max:'.StoreStockService::MAX_QUANTITY],
        ], [], ['images' => 'görseller', 'images.*' => 'görsel', 'stock' => 'stok', 'options.*.stock' => 'stok']);

        $product = $request->only(['name', 'category_id', 'brand_id', 'variant_name', 'description']);
        $product['is_published'] = $request->boolean('is_published');
        $variant = $request->only(['sku', 'barcode', 'option_value', 'price', 'vat_rate']);
        $options = array_map(
            // Gönderilmeyen alan boştur; ilk seçeneğin barkodu veya kodu başka seçeneğe geçmez.
            fn ($row) => array_intersect_key((array) $row, array_flip(['option_value', 'sku', 'barcode', 'price', 'stock']))
                + ['option_value' => null, 'sku' => null, 'barcode' => null, 'price' => null, 'stock' => null],
            (array) $request->input('options', []),
        );

        try {
            $result = $this->import->product(
                $product,
                $variant,
                $request->file('images', []),
                $request->filled('stock') ? (int) $request->input('stock') : null,
                $options,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result, $result['status'] === 'created' ? 201 : 200);
    }
}
