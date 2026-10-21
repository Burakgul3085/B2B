<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Reports\OrderExport;
use App\Domain\Store\Reports\StoreReports;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Mağaza raporları: dönem özeti, günlük satış, ödeme/teslimat, en çok satanlar, kampanyalar, iadeler; Excel sipariş listesi. */
class ReportController extends Controller
{
    public function __construct(private StoreReports $reports) {}

    public function index(Request $request): View
    {
        [$from, $to] = $this->period($request);

        return view('store.admin.reports.index', [
            'from' => $from, 'to' => $to,
            'summary' => $this->reports->summary($from, $to),
            'daily' => $this->reports->daily($from, $to),
            'methods' => $this->reports->byMethod($from, $to),
            'products' => $this->reports->topProducts($from, $to),
            'coupons' => $this->reports->coupons($from, $to),
            'reasons' => $this->reports->returnReasons($from, $to),
        ]);
    }

    public function export(Request $request, OrderExport $export): BinaryFileResponse
    {
        [$from, $to] = $this->period($request);
        $path = tempnam(sys_get_temp_dir(), 'magaza').'.xlsx';
        $export->write($path, $from, $to);

        return response()->download($path, "siparisler-{$from}-{$to}.xlsx")->deleteFileAfterSend();
    }

    /** @return array{0: string, 1: string} Y-m-d (Türkiye saati); varsayılan: bu ayın başından bugüne, en fazla 1 yıl */
    private function period(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']], [], ['from' => 'başlangıç', 'to' => 'bitiş']);
        $today = Carbon::now('Europe/Istanbul');
        $to = isset($data['to']) ? Carbon::parse($data['to']) : $today;
        $from = isset($data['from']) ? Carbon::parse($data['from']) : $today->copy()->startOfMonth();
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subYear();
        }

        return [$from->toDateString(), $to->toDateString()];
    }
}
