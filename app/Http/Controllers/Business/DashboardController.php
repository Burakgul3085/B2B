<?php

namespace App\Http\Controllers\Business;

use App\Domain\Ledger\AccountAnalysis;
use App\Domain\Reporting\DashboardMetrics;
use App\Domain\Reporting\Reports\Options;
use App\Http\Controllers\Controller;
use App\Models\CollectionEntry;
use App\Models\CurrentAccount;
use App\Models\DealerApplication;
use App\Models\DealerRelationship;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Toptancı dashboard'u. Yalnızca kullanıma açılmış modüllerin gerçek verisi gösterilir;
 * sipariş ve cari göstergeleri ilgili modüllerle eklenecek (sahte rakam yok).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardMetrics $metrics): View
    {
        $business = $request->user()->business->load('settings');

        // Dönem ve filtreler: göstergelere ve grafiklere aynı şekilde uygulanır.
        $f = $request->validate([
            'period' => ['nullable', 'in:'.implode(',', array_keys(DashboardMetrics::PERIODS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'dealer' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
        ]);
        $f['period'] ??= 'month';
        $range = $metrics->range($f['period'], $f['from'] ?? null, $f['to'] ?? null);
        $analytics = [
            'filters' => $f,
            'range' => $range,
            'kpis' => $metrics->kpis($range, $f),
            'series' => $metrics->salesSeries($range, $f),
            'status' => $metrics->statusGroups($range, $f),
            'categories' => $metrics->categories($range, $f),
            'dealers' => $metrics->dealers($range, $f),
            'dealerOptions' => Options::dealers(),
            'categoryOptions' => Options::categories(),
        ];
        $warehouseId = $business->settings->default_warehouse_id;

        // Toplam stok ve stok değeri tüm depolardan; değer = alış fiyatı × miktar (alış fiyatı olmayan ürün hariç).
        $stockTotals = WarehouseStock::query()
            ->join('products', 'products.id', '=', 'warehouse_stock.product_id')
            ->whereNull('products.archived_at')
            ->selectRaw('COALESCE(SUM(warehouse_stock.quantity), 0) as qty')
            ->selectRaw('COALESCE(SUM(warehouse_stock.quantity * products.purchase_price), 0) as value')
            ->selectRaw('SUM(products.purchase_price IS NULL AND warehouse_stock.quantity > 0) as unpriced')
            ->first();

        $salesQty = '(SELECT COALESCE(ws.quantity, 0) FROM warehouse_stock ws WHERE ws.product_id = products.id AND ws.warehouse_id = '.(int) $warehouseId.')';
        $active = Product::notArchived()->where('is_active', true);

        $kpis = [
            'active_dealers' => DealerRelationship::where('status', 'active')->count(),
            'active_products' => (clone $active)->count(),
            'out_of_stock' => (clone $active)->whereRaw("COALESCE({$salesQty}, 0) <= 0")->count(),
            'stock_qty' => (string) $stockTotals->qty,
            'stock_value' => (string) $stockTotals->value,
            'unpriced' => (int) $stockTotals->unpriced,
            'pending_orders' => Order::whereIn('status', [...Order::PENDING, 'cancel_requested'])->count(),
            'today_orders' => Order::where('created_at', '>=', now()->startOfDay())->count(),
            // Satış = onaylanmış ve iptal edilmemiş siparişlerin tutarı (onay tarihine göre).
            'month_sales' => (string) Order::whereIn('status', ['approved', 'preparing', 'completed', 'cancel_requested'])
                ->where('approved_at', '>=', now()->startOfMonth())->sum('grand_total'),
            'open_requests' => ProductRequest::whereIn('status', ProductRequest::OPEN)->count(),
            'new_applications' => DealerApplication::whereIn('status', ['new', 'waiting_info'])->count(),
        ];

        $recentOrders = Order::with('relationship.dealer:id,legal_name')->latest('id')->limit(6)->get();

        $accounts = CurrentAccount::where('balance', '>', 0)->get();
        $aging = app(AccountAnalysis::class)->agingMany($accounts);
        $finance = [
            'receivable' => (string) $accounts->sum('balance'),
            'overdue' => collect($aging)->reduce(fn ($c, $a) => bcadd($c, $a['overdue'], 2), '0.00'),
            'month_collections' => (string) CollectionEntry::whereNull('cancelled_at')->where('collected_on', '>=', now()->startOfMonth()->toDateString())->sum('amount'),
        ];

        $critical = (clone $active)->with('unit')
            ->select('products.*')->selectRaw("COALESCE({$salesQty}, 0) as sales_qty")
            ->whereRaw("COALESCE({$salesQty}, 0) > 0")
            ->whereRaw("COALESCE({$salesQty}, 0) <= products.critical_stock_level")
            ->orderByRaw("COALESCE({$salesQty}, 0) / NULLIF(products.critical_stock_level, 0)")
            ->limit(8)->get();

        $setup = [
            ['label' => 'İşletme bilgilerini tamamlayın', 'done' => filled($business->phone) && filled($business->address), 'route' => 'business.settings.edit'],
            ['label' => 'İlk ürününüzü ekleyin', 'done' => $kpis['active_products'] > 0, 'route' => 'business.products.create'],
            ['label' => 'Stok girişi yapın', 'done' => bccomp($kpis['stock_qty'], '0', 3) > 0, 'route' => 'business.stock.create'],
        ];

        return view('business.dashboard', compact('kpis', 'setup', 'critical', 'recentOrders', 'finance', 'analytics'));
    }
}
