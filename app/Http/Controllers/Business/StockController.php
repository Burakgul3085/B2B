<?php

namespace App\Http\Controllers\Business;

use App\Domain\Inventory\InsufficientStock;
use App\Domain\Inventory\Quantity;
use App\Domain\Inventory\StockService;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Stok durumu, elle stok hareketi ve depolar arası transfer. */
class StockController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request): View
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $defaultId = $request->user()->business->settings->default_warehouse_id;

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'warehouse' => ['nullable', 'integer', Rule::in($warehouses->pluck('id'))],
            'category' => ['nullable', 'integer'],
            'level' => ['nullable', Rule::in(['critical', 'out', 'in'])],
        ]);
        $warehouseId = (int) ($filters['warehouse'] ?? $defaultId);

        $qtyExpr = '(SELECT COALESCE(ws.quantity, 0) FROM warehouse_stock ws WHERE ws.product_id = products.id AND ws.warehouse_id = '.$warehouseId.')';

        $products = Product::notArchived()
            ->with(['unit', 'brand'])
            ->select('products.*')
            ->selectRaw("COALESCE({$qtyExpr}, 0) as warehouse_qty")
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = addcslashes($term, '%_\\');
                $q->where(fn ($q) => $q->where('name', 'like', "%{$like}%")->orWhere('sku', 'like', "{$like}%")->orWhere('barcode', $term));
            })
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where(fn ($q) => $q->where('category_id', $c)->orWhereIn('category_id', Category::where('parent_id', $c)->select('id'))))
            ->when(($filters['level'] ?? null) === 'out', fn ($q) => $q->whereRaw("COALESCE({$qtyExpr}, 0) <= 0"))
            ->when(($filters['level'] ?? null) === 'critical', fn ($q) => $q->whereRaw("COALESCE({$qtyExpr}, 0) > 0")->whereRaw("COALESCE({$qtyExpr}, 0) <= products.critical_stock_level"))
            ->when(($filters['level'] ?? null) === 'in', fn ($q) => $q->whereRaw("COALESCE({$qtyExpr}, 0) > 0"))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('business.inventory.stock', [
            'products' => $products,
            'warehouses' => $warehouses,
            'warehouseId' => $warehouseId,
            'filters' => $filters,
            'categories' => Category::with('parent')->get()->sortBy(fn ($c) => $c->fullName())->mapWithKeys(fn ($c) => [$c->id => $c->fullName()])->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $product = $request->integer('product') ? Product::notArchived()->with('unit')->find($request->integer('product')) : null;

        return view('business.inventory.adjust', [
            'product' => $product,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'defaultId' => $request->user()->business->settings->default_warehouse_id,
            'types' => array_intersect_key(StockMovement::TYPES, StockService::MANUAL_TYPES),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(array_keys(StockService::MANUAL_TYPES))],
            'quantity' => ['required', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['product_id' => 'ürün', 'warehouse_id' => 'depo', 'type' => 'hareket türü', 'quantity' => 'miktar']);

        $product = Product::notArchived()->with('unit')->findOrFail($data['product_id']);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $qty = Quantity::parse($data['quantity']);

        if ($qty === null) {
            return back()->withErrors(['quantity' => 'Geçerli bir miktar girin (en fazla 3 ondalık).'])->withInput();
        }

        try {
            $this->stock->adjust($product, $warehouse, $data['type'], $qty, $data['note'] ?? null);
        } catch (InsufficientStock|DomainException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()])->withInput();
        }

        return redirect()->route('business.products.show', $product)->with('status', 'Stok hareketi kaydedildi.');
    }

    public function transferForm(Request $request): View
    {
        return view('business.inventory.transfer', [
            'product' => $request->integer('product') ? Product::notArchived()->with('unit')->find($request->integer('product')) : null,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function transfer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'from_warehouse_id' => ['required', 'integer'],
            'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id'],
            'quantity' => ['required', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['to_warehouse_id.different' => 'Hedef depo, kaynak depodan farklı olmalıdır.'], ['quantity' => 'miktar']);

        $product = Product::notArchived()->with('unit')->findOrFail($data['product_id']);
        $from = Warehouse::findOrFail($data['from_warehouse_id']);
        $to = Warehouse::findOrFail($data['to_warehouse_id']);
        $qty = Quantity::parse($data['quantity']);

        if ($qty === null) {
            return back()->withErrors(['quantity' => 'Geçerli bir miktar girin.'])->withInput();
        }

        try {
            $this->stock->transfer($product, $from, $to, $qty, $data['note'] ?? null);
        } catch (InsufficientStock|DomainException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()])->withInput();
        }

        return redirect()->route('business.products.show', $product)->with('status', "Transfer tamamlandı: {$from->name} → {$to->name}.");
    }

    /** Ürün seçici için arama (ad, SKU, barkod). Yalnızca işletmenin kendi ürünleri. */
    public function lookup(Request $request)
    {
        $term = trim($request->scalar('q'));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }
        $like = addcslashes($term, '%_\\');

        return Product::notArchived()->with('unit')
            ->where(fn ($q) => $q->where('name', 'like', "%{$like}%")->orWhere('sku', 'like', "{$like}%")->orWhere('barcode', $term))
            ->orderBy('name')->limit(15)->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'unit' => $p->unit->name, 'fraction' => $p->unit->allows_fraction]);
    }
}
