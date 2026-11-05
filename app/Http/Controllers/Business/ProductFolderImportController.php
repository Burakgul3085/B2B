<?php

namespace App\Http\Controllers\Business;

use App\Domain\Catalog\FolderImport;
use App\Domain\Catalog\ProductService;
use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Klasörden toplu ürün ekleme. Klasör tarayıcıda okunur ve ürün ürün gönderilir; sunucu her ürünü tek ürün
 * ekleme ekranıyla aynı kurallarla doğrular. Tarayıcıdan gelen kategori, marka ve KDV yalnızca işletmenin
 * kendi kayıtlarıyla eşleşirse kabul edilir.
 */
class ProductFolderImportController extends Controller
{
    public function __construct(private FolderImport $import) {}

    public function index(): View
    {
        return view('business.catalog.folder-import', [
            'taxRates' => TaxRate::where('is_active', true)->orderBy('rate')->get(['id', 'name', 'rate']),
            'maxImages' => ProductService::MAX_IMAGES,
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
            'categories.*' => ['array', 'min:1', 'max:'.FolderImport::MAX_DEPTH],
            'categories.*.*' => ['string', 'min:1', 'max:120'],
            'brands' => ['present', 'array', 'max:'.FolderImport::MAX_LOOKUP],
            'brands.*' => ['string', 'min:1', 'max:120'],
            'create' => ['boolean'],
        ], ['categories.*.max' => 'Kategori en fazla '.FolderImport::MAX_DEPTH.' seviye olabilir.']);

        $trim = fn (array $names) => array_values(array_map(fn ($n) => trim($n), $names));

        return response()->json($this->import->prepare(
            array_map($trim, $data['categories']),
            $trim($data['brands']),
            $request->boolean('create'),
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'images' => ['nullable', 'array', 'max:'.ProductService::MAX_IMAGES],
            'images.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'parent_sku' => ['nullable', 'string', 'max:64'],
        ], [], ['images' => 'görseller', 'images.*' => 'görsel', 'stock' => 'stok']);

        $input = $request->only(['sku', 'barcode', 'name', 'description', 'category_id', 'brand_id', 'list_price', 'tax_rate_id', 'option_name', 'option_value']);
        $input['sale_mode'] = 'single';
        $input['price_includes_tax'] = $request->boolean('price_includes_tax');
        $input['is_active'] = $request->boolean('is_active');
        $input['visible_to_dealers'] = true;

        try {
            $result = $this->import->product(
                $input,
                $request->file('images', []),
                $request->filled('stock') ? (string) (int) $request->input('stock') : null,
                $request->filled('parent_sku') ? (string) $request->input('parent_sku') : null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result, $result['status'] === 'created' ? 201 : 200);
    }
}
