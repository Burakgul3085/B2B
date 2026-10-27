<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Audit\AuditLogger;
use App\Domain\Store\Reports\StoreReport;
use App\Domain\Store\Reports\StoreReportExporter;
use App\Domain\Store\Reports\StoreReportRegistry;
use App\Domain\Store\Reports\StoreReportRunner;
use App\Domain\Store\Reports\StoreReports;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreSavedReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mağaza rapor merkezi: dönem özeti (genel bakış) ve filtrelenip sıralanabilen, sütunları seçilebilen raporlar.
 * Ekran ve indirilen Excel/PDF aynı hazırlığı kullanır; filtreler adıyla kaydedilebilir.
 */
class ReportController extends Controller
{
    public function __construct(private StoreReportRunner $runner) {}

    public function index(Request $request, StoreReports $reports): View
    {
        [$from, $to] = $this->period($request);
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        [$prevFrom, $prevTo] = [Carbon::parse($from)->subDays($days)->toDateString(), Carbon::parse($from)->subDay()->toDateString()];
        $all = collect(StoreReportRegistry::all());

        return view('store.admin.reports.index', [
            'from' => $from, 'to' => $to, 'prevFrom' => $prevFrom, 'prevTo' => $prevTo,
            'summary' => $reports->summary($from, $to),
            'previous' => $reports->summary($prevFrom, $prevTo),
            'daily' => $reports->daily($from, $to),
            'methods' => $reports->byMethod($from, $to),
            'products' => $reports->topProducts($from, $to, 8),
            'coupons' => $reports->coupons($from, $to),
            'reasons' => $reports->returnReasons($from, $to),
            'groups' => $all->groupBy(fn (StoreReport $r) => $r->group()),
            'saved' => $this->savedQuery($request)->orderBy('name')->get()->filter(fn ($s) => $all->has($s->report_key)),
            'titles' => $all->map(fn (StoreReport $r) => $r->title()),
        ]);
    }

    public function show(Request $request, string $key): View
    {
        $report = StoreReportRegistry::find($key) ?? abort(404);
        $prepared = $this->runner->prepare($report, $request->query());

        return view('store.admin.reports.show', [
            'report' => $report,
            'prepared' => $prepared,
            'rows' => $this->runner->paginate($report, $prepared, max(1, (int) $request->query('page', 1)))->withQueryString(),
            'totals' => $this->runner->totals($report, $prepared),
            'query' => $request->query(),
            'saved' => $this->savedQuery($request)->where('report_key', $key)->orderBy('name')->get(),
        ]);
    }

    public function export(Request $request, string $key, string $format, StoreReportExporter $exporter, AuditLogger $audit): Response|RedirectResponse
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $report = StoreReportRegistry::find($key) ?? abort(404);
        $prepared = $this->runner->prepare($report, $request->query());
        $admin = $request->user('store_admin');
        $audit->log('store.report_exported', null, [], ['report' => $key, 'format' => $format, 'filters' => $prepared['filters']]);

        if ($format === 'xlsx') {
            return $exporter->excel($report, $prepared, $admin->name);
        }

        return $exporter->pdf($report, $prepared, $admin->name)
            ?? back()->withErrors(['report' => 'PDF en fazla '.number_format($exporter->pdfRowLimit, 0, ',', '.').' satır içerebilir. Filtreleri daraltın veya Excel olarak indirin.']);
    }

    /** Ekrandaki filtreleri (sıralama ve sütunlarla) adıyla kaydeder; kaydetmeden önce rapor kurallarıyla doğrulanır. */
    public function save(Request $request, string $key): RedirectResponse
    {
        $report = StoreReportRegistry::find($key) ?? abort(404);
        $name = trim((string) $request->validate(['name' => ['required', 'string', 'max:80']], [], ['name' => 'ad'])['name']);
        $query = json_decode((string) $request->input('query', '[]'), true);
        $query = is_array($query) ? $query : [];
        $this->runner->prepare($report, $query); // geçersiz filtre kaydedilmez

        $allowed = array_merge(array_keys($report->filters()), ['sort', 'dir', 'cols']);
        $filters = array_filter(array_intersect_key($query, array_flip($allowed)), fn ($v) => $v !== null && $v !== '' && $v !== []);

        if ($this->savedQuery($request)->count() >= StoreSavedReport::MAX_PER_ADMIN) {
            return back()->withErrors(['report' => 'En fazla '.StoreSavedReport::MAX_PER_ADMIN.' kayıtlı rapor tutulabilir; kullanmadıklarınızı silin.']);
        }
        StoreSavedReport::create(['admin_id' => $request->user('store_admin')->id, 'report_key' => $key, 'name' => $name, 'filters' => $filters]);

        return back()->with('status', "\"{$name}\" kaydedildi. Raporlar sayfasından tek tıkla açabilirsiniz.");
    }

    public function destroySaved(Request $request, int $saved): RedirectResponse
    {
        $this->savedQuery($request)->whereKey($saved)->firstOrFail()->delete();

        return back()->with('status', 'Kayıtlı rapor silindi.');
    }

    private function savedQuery(Request $request)
    {
        return StoreSavedReport::where('admin_id', $request->user('store_admin')->id);
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
