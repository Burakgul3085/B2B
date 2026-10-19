<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Ledger\AccountAnalysis;
use App\Domain\Reporting\DashboardMetrics;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\Concerns\UsesDealerContext;
use App\Models\Announcement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductRequest;
use Illuminate\View\View;

/** Bayinin hesap özeti: yalnızca seçili toptancıyla olan ilişkinin gerçek verileri. */
class DashboardController extends Controller
{
    use UsesDealerContext;

    /** Alım sayılan durumlar (onaylanmış ve iptal edilmemiş). */
    private const PURCHASED = ['approved', 'preparing', 'completed', 'cancel_requested'];

    public function __invoke(DashboardMetrics $metrics, AccountAnalysis $analysis): View
    {
        $relationship = $this->relationship()->load(['currentAccount', 'group']);
        $orders = Order::where('dealer_relationship_id', $relationship->id);
        $purchased = (clone $orders)->whereIn('status', self::PURCHASED);
        $approvedSince = fn ($from, $to = null) => (string) (clone $purchased)->where('approved_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('approved_at', '<', $to))->sum('grand_total');

        $year = (clone $purchased)->where('approved_at', '>=', now()->startOfYear())
            ->selectRaw('COUNT(*) c, COALESCE(SUM(grand_total), 0) t')->toBase()->first();

        $kpis = [
            'open_orders' => (clone $orders)->whereIn('status', [...Order::PENDING, ...Order::ACTIVE_FULFILMENT])->count(),
            'pending_orders' => (clone $orders)->whereIn('status', Order::PENDING)->count(),
            'month_amount' => $approvedSince(now()->startOfMonth()),
            'last_month_amount' => $approvedSince(now()->subMonthNoOverflow()->startOfMonth(), now()->startOfMonth()),
            'balance' => (string) ($relationship->currentAccount?->balance ?? '0'),
            'overdue' => $relationship->currentAccount ? $analysis->aging($relationship->currentAccount)['overdue'] : '0.00',
            'open_requests' => ProductRequest::where('dealer_relationship_id', $relationship->id)->whereIn('status', ProductRequest::OPEN)->count(),
            'year_count' => (int) $year->c,
            'year_amount' => (string) $year->t,
            'year_average' => (int) $year->c > 0 ? bcdiv((string) $year->t, (string) $year->c, 2) : '0',
        ];

        $topProducts = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('dealer_relationship_id', $relationship->id)->whereIn('status', ['approved', 'preparing', 'completed']))
            ->selectRaw('product_id, MAX(name) as name, SUM(quantity_approved) as qty, MAX(unit_name) as unit_name, COUNT(*) as times')
            ->groupBy('product_id')->orderByDesc('qty')->limit(5)->get();
        $images = Product::whereIn('id', $topProducts->pluck('product_id'))->with('primaryImage')->get()->keyBy('id');

        return view('dealer.dashboard', [
            'relationship' => $relationship,
            'kpis' => $kpis,
            'recentOrders' => (clone $orders)->withCount('items')->latest('created_at')->latest('id')->limit(5)->get(),
            'topProducts' => $topProducts,
            'images' => $images,
            'monthly' => $metrics->dealerMonthly($relationship->id),
            // Son 30 günün duyuruları: herkese veya bayinin grubuna.
            'announcements' => Announcement::whereNotNull('published_at')->where('published_at', '>=', now()->subDays(30))
                ->where(fn ($q) => $q->whereNull('dealer_group_id')->orWhere('dealer_group_id', $relationship->dealer_group_id))
                ->with('attachments')->latest('published_at')->limit(3)->get(),
            'arrived' => ProductRequest::where('dealer_relationship_id', $relationship->id)->where('status', 'stock_updated')
                ->with('product:id,name')->latest('notified_at')->limit(5)->get(),
        ]);
    }
}
