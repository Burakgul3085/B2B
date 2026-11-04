<?php

namespace App\Http\Controllers\Business;

use App\Domain\Catalog\CreateProduct;
use App\Domain\Catalog\ProductService;
use App\Domain\Inventory\Quantity;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Showcase;
use App\Models\ShowcaseProduct;
use App\Models\TaxRate;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    private const SORTS = ['name' => 'name', 'sku' => 'sku', 'list_price' => 'list_price', 'stock' => 'total_stock', 'updated' => 'updated_at'];

    public function __construct(private ProductService $products) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $products = $this->filteredQuery($filters)
            ->with(['brand', 'category.parent', 'unit', 'primaryImage'])
            ->withSum('stocks as total_stock', 'quantity')
            ->orderBy(self::SORTS[$filters['sort'] ?? 'name'] ?? 'name', ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('business.products.index', [
            'products' => $products,
            'filters' => $filters,
            'categories' => $this->categoryOptions(),
            'brands' => Brand::orderBy('name')->pluck('name', 'id'),
            'hasAnyProduct' => Product::exists(),
        ]);
    }

    public function create(Request $request): View
    {
        // "Yeni seçenek ekle": form kaynak ürünün bilgileriyle dolu gelir; kod, barkod ve değer boş kalır.
        if ($request->filled('secenek')) {
            $source = Product::notArchived()->findOrFail((int) $request->query('secenek'));
            $draft = new Product(['is_active' => true] + array_diff_key($source->only(Product::EDITABLE), array_flip(['sku', 'barcode', 'option_value', 'is_active'])));

            return view('business.products.form', ['optionOf' => $source] + $this->formData($draft));
        }

        return view('business.products.form', $this->formData(new Product([
            'is_active' => true, 'visible_to_dealers' => true, 'pack_quantity' => 1, 'min_order_qty' => 1,
            'order_step' => 1, 'critical_stock_level' => 0, 'sale_mode' => 'single',
            'tax_rate_id' => TaxRate::where('rate', '20.00')->value('id'),
        ])));
    }

    /** Ürün tek ekranda oluşturulur: bilgiler, görseller, başlangıç stoğu, vitrinler ve diğer seçenekler (docs/03 §2.3). */
    public function store(Request $request, CreateProduct $create): RedirectResponse
    {
        $source = $request->filled('option_of') ? Product::notArchived()->findOrFail((int) $request->input('option_of')) : null;
        $image = ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp'];
        $extras = $request->validate([
            'images' => ['nullable', 'array', 'max:'.ProductService::MAX_IMAGES],
            'images.*' => $image,
            'opening_stock' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'warehouse_id' => ['nullable', 'integer'],
            'options' => ['nullable', 'array', 'max:'.CreateProduct::MAX_OPTIONS],
            'options.*' => ['array'],
            'options.*.stock' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'options.*.image' => ['nullable', ...$image],
            'showcases' => ['nullable', 'array'],
            'showcases.*' => ['integer'],
        ], [], ['images' => 'görseller', 'images.*' => 'görsel', 'opening_stock' => 'başlangıç stoğu', 'options.*.stock' => 'stok', 'options.*.image' => 'görsel']);

        try {
            $product = $create->handle(
                $this->input($request),
                $request->file('images', []),
                isset($extras['opening_stock']) ? (string) $extras['opening_stock'] : null,
                isset($extras['warehouse_id']) ? (int) $extras['warehouse_id'] : null,
                $request->file('options', []) ? array_replace_recursive((array) $request->input('options', []), $request->file('options', [])) : (array) $request->input('options', []),
                $extras['showcases'] ?? [],
                $source,
                $request->string('source_option_value')->toString(),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['name' => $e->getMessage()])->withInput();
        }

        $count = $product->variant_group_id ? Product::optionsOf($product)->count() : 1;

        return redirect()->route('business.products.show', $product)
            ->with('status', $count > 1 && ! $source ? "Ürün {$count} seçeneğiyle oluşturuldu." : 'Ürün oluşturuldu.');
    }

    public function show(Product $product): View
    {
        $product->load(['brand', 'category.parent', 'unit', 'taxRate', 'images', 'variantGroup']);

        $stocks = Warehouse::orderBy('name')->get()->map(fn ($w) => [
            'warehouse' => $w,
            'quantity' => $product->stocks->firstWhere('warehouse_id', $w->id)?->quantity ?? '0',
        ]);

        $history = AuditLog::where('entity_type', 'Product')->where('entity_id', $product->id)
            ->where('business_id', $product->business_id)->latest('id')->limit(20)->get();

        return view('business.products.show', [
            'product' => $product,
            'stocks' => $stocks,
            'movements' => $product->movements()->with('warehouse')->limit(10)->get(),
            'history' => $history,
            'manualShowcases' => Showcase::where('type', 'manual')->orderBy('sort')->get(['id', 'name', 'is_active']),
            'productShowcaseIds' => ShowcaseProduct::where('product_id', $product->id)->pluck('showcase_id')->map(fn ($id) => (int) $id)->all(),
            'options' => $product->variant_group_id
                ? Product::optionsOf($product)->notArchived()->withSum('stocks', 'quantity')->orderBy('option_value')->get(['id', 'name', 'sku', 'option_value', 'is_active', 'dealer_price', 'list_price'])
                : collect(),
        ]);
    }

    public function edit(Product $product): View
    {
        return view('business.products.form', $this->formData($product));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        abort_if($product->isArchived(), 409, 'Arşivlenmiş ürün düzenlenemez; önce arşivden çıkarın.');

        $this->products->update($product, $this->products->validate($this->input($request), $product));

        return redirect()->route('business.products.show', $product)->with('status', 'Ürün güncellendi.');
    }

    /** Ürünü seçeneklerinden ayırır (bağımsız ürün olur; kodu, stoğu ve geçmişi değişmez). */
    public function detachOption(Product $product): RedirectResponse
    {
        $this->products->detachOption($product);

        return redirect()->route('business.products.show', $product)->with('status', 'Ürün seçeneklerden ayrıldı; artık bağımsız bir ürün.');
    }

    public function duplicate(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['sku' => ['required', 'string', 'max:64'], 'name' => ['nullable', 'string', 'max:255']], [], ['sku' => 'yeni ürün kodu']);
        $copy = $this->products->duplicate($product, $data['sku'], $data['name'] ?? null);

        return redirect()->route('business.products.edit', $copy)->with('status', 'Ürün kopyalandı. Kopya pasif olarak oluşturuldu; bilgileri kontrol edip etkinleştirin.');
    }

    public function archive(Product $product): RedirectResponse
    {
        $this->products->archive($product);

        return redirect()->route('business.products.show', $product)->with('status', 'Ürün arşivlendi. Bayiler artık bu ürünü görmez; geçmiş kayıtlar korunur.');
    }

    /** Kalıcı silme (stok hareketleriyle birlikte; açık siparişteki ürün silinmez). */
    public function destroy(Product $product): RedirectResponse
    {
        try {
            $this->products->delete($product);
        } catch (DomainException $e) {
            return back()->withErrors(['product' => $e->getMessage()]);
        }

        return redirect()->route('business.products.index')->with('status', "“{$product->name}” kalıcı olarak silindi.");
    }

    public function restore(Product $product): RedirectResponse
    {
        $this->products->restore($product);

        return back()->with('status', 'Ürün arşivden çıkarıldı. Satışa açmak için etkinleştirin.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate'])],
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ], ['ids.required' => 'En az bir ürün seçin.']);

        $count = $this->products->bulkSetActive($data['ids'], $data['action'] === 'activate');

        return back()->with('status', "{$count} ürün güncellendi.");
    }

    public function priceForm(Request $request): View
    {
        $filters = $this->filters($request);

        return view('business.products.bulk-price', [
            'filters' => $filters,
            'matching' => $this->filteredQuery($filters)->count(),
            'categories' => $this->categoryOptions(),
            'brands' => Brand::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function priceUpdate(Request $request): RedirectResponse
    {
        $filters = $this->filters($request);
        $data = $request->validate([
            'field' => ['required', Rule::in(['list_price', 'dealer_price', 'purchase_price'])],
            'mode' => ['required', Rule::in(['percent', 'amount'])],
            'value' => ['required', 'decimal:0,4', 'not_in:0', 'min:-100000', 'max:100000'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Değişikliği onaylamak için kutuyu işaretleyin.'], ['value' => 'değişim değeri']);

        if ($data['mode'] === 'percent' && $data['value'] <= -100) {
            return back()->withErrors(['value' => 'Yüzde indirim %100 veya daha fazla olamaz.'])->withInput();
        }

        try {
            $count = $this->products->bulkPriceUpdate($this->filteredQuery($filters), $data['field'], $data['mode'], (string) $data['value']);
        } catch (DomainException $e) {
            return back()->withErrors(['value' => $e->getMessage()])->withInput();
        }

        return redirect()->route('business.products.index', $filters)->with('status', "{$count} ürünün fiyatı güncellendi. Geçmiş siparişler etkilenmez.");
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'brand' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'passive', 'archived'])],
            'stock' => ['nullable', Rule::in(['critical', 'out'])],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = Product::query();

        if (($filters['status'] ?? null) === 'archived') {
            $query->whereNotNull('archived_at');
        } else {
            $query->notArchived();
            match ($filters['status'] ?? null) {
                'active' => $query->where('is_active', true),
                'passive' => $query->where('is_active', false),
                default => null,
            };
        }

        if ($term = trim($filters['q'] ?? '')) {
            $like = addcslashes($term, '%_\\');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$like}%")->orWhere('sku', 'like', "{$like}%")->orWhere('barcode', $term));
        }

        if ($category = $filters['category'] ?? null) {
            $query->where(fn ($q) => $q->where('category_id', $category)
                ->orWhereIn('category_id', Category::where('parent_id', $category)->select('id')));
        }

        if ($brand = $filters['brand'] ?? null) {
            $query->where('brand_id', $brand);
        }

        $stockSum = '(SELECT COALESCE(SUM(ws.quantity), 0) FROM warehouse_stock ws WHERE ws.business_id = products.business_id AND ws.product_id = products.id)';
        match ($filters['stock'] ?? null) {
            'out' => $query->whereRaw("{$stockSum} <= 0"),
            'critical' => $query->whereRaw("{$stockSum} <= products.critical_stock_level")->whereRaw("{$stockSum} > 0"),
            default => null,
        };

        return $query;
    }

    private function input(Request $request): array
    {
        $input = $request->only([...Product::EDITABLE, 'min_cases']);
        foreach (['price_includes_tax', 'is_active', 'visible_to_dealers'] as $flag) {
            $input[$flag] = $request->boolean($flag);
        }
        foreach (['pack_quantity', 'min_order_qty', 'order_step', 'critical_stock_level'] as $field) {
            if (isset($input[$field]) && is_scalar($input[$field]) && ($parsed = Quantity::parse($input[$field])) !== null) {
                $input[$field] = $parsed;
            }
        }

        return $input;
    }

    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'categories' => $this->categoryOptions(),
            'brands' => Brand::where('is_active', true)->orWhere('id', $product->brand_id)->orderBy('name')->pluck('name', 'id'),
            'taxRates' => TaxRate::where('is_active', true)->orWhere('id', $product->tax_rate_id)->orderBy('rate')->pluck('name', 'id'),
            'optionOf' => null,
            'warehouses' => $product->exists ? collect() : Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'defaultWarehouseId' => request()->user()?->business?->settings?->default_warehouse_id,
            'showcaseOptions' => $product->exists ? collect() : Showcase::where('type', 'manual')->orderBy('sort')->get(['id', 'name', 'is_active']),
        ];
    }

    /** @return array<int, string> "Ana › Alt" biçiminde kategori listesi */
    private function categoryOptions(): array
    {
        return Category::with('parent')->get()
            ->sortBy(fn ($c) => mb_strtolower($c->fullName()))
            ->mapWithKeys(fn ($c) => [$c->id => $c->fullName()])
            ->all();
    }
}
