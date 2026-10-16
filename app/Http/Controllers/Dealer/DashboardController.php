<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\Concerns\UsesDealerContext;
use App\Models\Announcement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductRequest;
use Illuminate\View\View;

/** Bayi ana sayfası: yalnızca seçili toptancıyla olan ilişkinin gerçek verileri. */
class DashboardController extends Controller
{
    use UsesDealerContext;

    public function __invoke(): View
    {
        $relationship = $this->relationship()->load('currentAccount');
        $orders = Order::where('dealer_relationship_id', $relationship->id);

        $kpis = [
            'open_orders' => (clone $orders)->whereIn('status', [...Order::PENDING, ...Order::ACTIVE_FULFILMENT])->count(),
            'total_orders' => (clone $orders)->count(),
            'month_amount' => (string) (clone $orders)->whereIn('status', ['approved', 'preparing', 'completed', 'cancel_requested'])
                ->where('approved_at', '>=', now()->startOfMonth())->sum('grand_total'),
            'balance' => (string) ($relationship->currentAccount?->balance ?? '0'),
            'open_requests' => ProductRequest::where('dealer_relationship_id', $relationship->id)->whereIn('status', ProductRequest::OPEN)->count(),
        ];

        $topProducts = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('dealer_relationship_id', $relationship->id)->whereIn('status', ['approved', 'preparing', 'completed']))
            ->selectRaw('product_id, MAX(name) as name, SUM(quantity_approved) as qty, MAX(unit_name) as unit_name')
            ->groupBy('product_id')->orderByDesc('qty')->limit(5)->get();

        return view('dealer.dashboard', [
            'kpis' => $kpis,
            'recentOrders' => (clone $orders)->latest('id')->limit(5)->get(),
            'topProducts' => $topProducts,
            // Son 30 günün duyuruları: herkese veya bayinin grubuna.
            'announcements' => Announcement::whereNotNull('published_at')->where('published_at', '>=', now()->subDays(30))
                ->where(fn ($q) => $q->whereNull('dealer_group_id')->orWhere('dealer_group_id', $relationship->dealer_group_id))
                ->latest('published_at')->limit(3)->get(),
            'arrived' => ProductRequest::where('dealer_relationship_id', $relationship->id)->where('status', 'stock_updated')
                ->with('product:id,name')->latest('notified_at')->limit(5)->get(),
        ]);
    }
}
