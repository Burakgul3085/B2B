<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Catalog\CategoryService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Support\LocalTime;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreStockMovement;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Mağaza stoğu: durum listesi, mal girişi, sayım, düzeltme, hareket geçmişi ve ters kayıt. */
class StockController extends Controller
{
    public function __construct(private StoreStockService $stock) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['critical', 'out', 'in'])],
        ]);

        $variants = StoreProductVariant::query()
            ->with(['product:id,name,variant_name,category_id'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('sku', 'like', "%{$term}%")->orWhere('barcode', $term)
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$term}%"))))
            ->when($filters['category'] ?? null, function ($q, $id) {
                $category = StoreCategory::find($id);
                $q->whereHas('product', fn ($p) => $p->whereIn('category_id', $category ? $category->descendantIds() : [0]));
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => match ($status) {
                'critical' => $q->whereNotNull('critical_stock')->whereColumn('stock_quantity', '<=', 'critical_stock'),
                'out' => $q->where('stock_quantity', 0),
                'in' => $q->where('stock_quantity', '>', 0),
            })
            ->orderBy(StoreProduct::select('name')->whereColumn('store_products.id', 'store_product_variants.product_id'))
            ->orderBy('sort')->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        return view('store.admin.stock.index', [
            'variants' => $variants,
            'held' => $this->stock->activeHeld($variants->pluck('id')->all()),
            'filters' => $filters,
            'categories' => CategoryService::options(),
            'summary' => [
                'units' => (int) StoreProductVariant::sum('stock_quantity'),
                'out' => StoreProductVariant::where('is_active', true)->where('stock_quantity', 0)->count(),
                'critical' => StoreProductVariant::whereNotNull('critical_stock')->whereColumn('stock_quantity', '<=', 'critical_stock')->count(),
            ],
        ]);
    }

    public function receive(Request $request, StoreProductVariant $storeVariant): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.StoreStockService::MAX_QUANTITY],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['quantity' => 'miktar', 'note' => 'açıklama']);

        return $this->attempt(fn () => $this->stock->receive($storeVariant, (int) $data['quantity'], $data['note'] ?? null),
            "{$storeVariant->sku}: {$data['quantity']} adet giriş yapıldı.");
    }

    public function count(Request $request, StoreProductVariant $storeVariant): RedirectResponse
    {
        $data = $request->validate([
            'actual' => ['required', 'integer', 'min:0', 'max:'.StoreStockService::MAX_QUANTITY],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['actual' => 'sayılan miktar', 'note' => 'açıklama']);

        return $this->attempt(function () use ($storeVariant, $data) {
            $movement = $this->stock->count($storeVariant, (int) $data['actual'], $data['note'] ?? null);

            return $movement ? null : 'Sayılan miktar mevcut stokla aynı; değişiklik yapılmadı.';
        }, "{$storeVariant->sku}: stok {$data['actual']} adet olarak güncellendi.");
    }

    public function adjust(Request $request, StoreProductVariant $storeVariant): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.StoreStockService::MAX_QUANTITY],
            'note' => ['required', 'string', 'max:500'],
        ], [], ['quantity' => 'miktar', 'note' => 'açıklama']);
        $signed = $data['direction'] === 'out' ? -(int) $data['quantity'] : (int) $data['quantity'];

        return $this->attempt(fn () => $this->stock->adjust($storeVariant, $signed, $data['note']), "{$storeVariant->sku}: düzeltme kaydedildi.");
    }

    public function movements(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(array_keys(StoreStockMovement::TYPES))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'variant' => ['nullable', 'integer'],
        ]);

        $movements = StoreStockMovement::query()
            ->with(['variant.product:id,name,variant_name', 'reversal:id,reverses_id'])
            ->when($filters['variant'] ?? null, fn ($q, $id) => $q->where('variant_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('variant', fn ($v) => $v
                ->where('sku', 'like', "%{$term}%")
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$term}%"))))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', LocalTime::dayRange($d, $d)[0]))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', LocalTime::dayRange($d, $d)[1]))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('store.admin.stock.movements', [
            'movements' => $movements,
            'filters' => $filters,
            'variant' => isset($filters['variant']) ? StoreProductVariant::with('product:id,name')->find($filters['variant']) : null,
            'actors' => ActivityController::adminNames(),
        ]);
    }

    public function reverse(Request $request, StoreStockMovement $storeMovement): RedirectResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;

        return $this->attempt(fn () => $this->stock->reverse($storeMovement, $note), "#{$storeMovement->id} numaralı hareket ters kayıtla geri alındı.");
    }

    /** İşlemi çalıştırır; işlem bir bilgi mesajı döndürürse başarı mesajı yerine onu gösterir. */
    private function attempt(callable $action, string $message): RedirectResponse
    {
        try {
            $info = $action();
        } catch (DomainException $e) {
            return back()->withErrors(['stock' => $e->getMessage()])->withInput();
        }

        return back()->with('status', is_string($info) ? $info : $message);
    }
}
