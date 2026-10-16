<?php

namespace App\Http\Controllers\Business;

use App\Domain\Audit\AuditLogger;
use App\Domain\Inventory\Quantity;
use App\Domain\Inventory\StockService;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Sayım: taslakta sayılan miktarlar girilir; uygulandığında fark kadar hareket oluşur. */
class StockCountController extends Controller
{
    public function __construct(private StockService $stock, private AuditLogger $audit) {}

    public function index(): View
    {
        return view('business.inventory.counts', [
            'counts' => StockCount::with('warehouse')->withCount('lines')->latest('id')->paginate(20),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['warehouse_id' => 'depo']);
        $warehouse = Warehouse::where('is_active', true)->findOrFail($data['warehouse_id']);

        $count = StockCount::create(['warehouse_id' => $warehouse->id, 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id]);

        return redirect()->route('business.stock-counts.show', $count);
    }

    public function show(StockCount $count): View
    {
        $count->load(['warehouse', 'lines' => fn ($q) => $q->with('product.unit')->orderBy('id')]);

        $current = $count->status === 'draft'
            ? WarehouseStock::where('warehouse_id', $count->warehouse_id)->whereIn('product_id', $count->lines->pluck('product_id'))->pluck('quantity', 'product_id')
            : collect();

        return view('business.inventory.count-show', compact('count', 'current'));
    }

    public function addLine(Request $request, StockCount $count): RedirectResponse
    {
        abort_unless($count->status === 'draft', 409);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'counted_qty' => ['required', 'string', 'max:20'],
        ], [], ['code' => 'ürün kodu / barkod', 'counted_qty' => 'sayılan miktar']);

        $code = trim($data['code']);
        $product = Product::notArchived()->with('unit')->where(fn ($q) => $q->where('sku', $code)->orWhere('barcode', $code))->first();
        if (! $product) {
            return back()->withErrors(['code' => "\"{$code}\" kodlu veya barkodlu ürün bulunamadı."])->withInput();
        }

        $qty = Quantity::parse($data['counted_qty']);
        if ($qty === null || bccomp($qty, '0', 3) < 0 || (! $product->unit->allows_fraction && ! Quantity::isWhole($qty))) {
            return back()->withErrors(['counted_qty' => 'Sayılan miktar sıfır veya pozitif olmalı; birim küsurata izin vermiyorsa tam sayı girin.'])->withInput();
        }

        StockCountLine::updateOrCreate(
            ['stock_count_id' => $count->id, 'product_id' => $product->id],
            ['counted_qty' => $qty],
        );

        return back()->with('status', "{$product->name}: ".Quantity::format($qty).' '.$product->unit->name.' kaydedildi.');
    }

    public function removeLine(StockCount $count, StockCountLine $line): RedirectResponse
    {
        abort_unless($line->stock_count_id === $count->id, 404);
        abort_unless($count->status === 'draft', 409);
        $line->delete();

        return back();
    }

    public function apply(StockCount $count): RedirectResponse
    {
        try {
            $this->stock->applyCount($count);
        } catch (DomainException $e) {
            return back()->withErrors(['count' => $e->getMessage()]);
        }
        $this->audit->log('inventory.count_applied', $count);

        return back()->with('status', 'Sayım uygulandı. Farklar stok hareketi olarak kaydedildi.');
    }

    public function cancel(StockCount $count): RedirectResponse
    {
        abort_unless($count->status === 'draft', 409);
        $count->update(['status' => 'cancelled']);

        return redirect()->route('business.stock-counts.index')->with('status', 'Sayım iptal edildi; stok değişmedi.');
    }
}
