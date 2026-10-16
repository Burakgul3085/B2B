<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Area;
use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Domain\Reporting\ReportExporter;
use App\Domain\Reporting\ReportRegistry;
use App\Domain\Reporting\ReportRunner;
use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Raporlama merkezi (toptancı ve bayi). Ekran ve dosya aynı hazırlığı kullanır. */
class ReportController extends Controller
{
    public function __construct(private ReportRunner $runner, private TenantContext $tenant) {}

    public function index(Request $request): View
    {
        $area = $this->area($request);

        return view('reports.index', [
            'area' => $area,
            'groups' => collect(ReportRegistry::for($this->audience($area)))->groupBy(fn (Report $r) => $r->group()),
        ]);
    }

    public function show(Request $request, string $key): View
    {
        $area = $this->area($request);
        [$report, $context] = $this->resolve($area, $key);
        $prepared = $this->runner->prepare($report, $request->query(), $context);
        $missing = $report->missingRequirement($prepared['filters']);

        $rows = $missing ? null : $this->runner->paginate($report, $prepared, $context, max(1, (int) $request->query('page', 1)))->withQueryString();

        return view('reports.show', [
            'area' => $area,
            'report' => $report,
            'context' => $context,
            'prepared' => $prepared,
            'rows' => $rows,
            'totals' => $missing ? [] : $this->runner->totals($report, $prepared, $context),
            'missing' => $missing,
            'query' => $request->query(),
        ]);
    }

    public function export(Request $request, string $key, string $format, ReportExporter $exporter, AuditLogger $audit): Response|RedirectResponse
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $area = $this->area($request);
        [$report, $context] = $this->resolve($area, $key);
        $prepared = $this->runner->prepare($report, $request->query(), $context);

        if ($message = $report->missingRequirement($prepared['filters'])) {
            return back()->withErrors(['report' => $message]);
        }

        $business = Business::findOrFail($context->businessId);
        $summary = $this->summary($report, $prepared, $context);
        $audit->log('report.exported', null, [], ['report' => $key, 'format' => $format, 'filters' => $prepared['filters']], businessId: $context->businessId);

        if ($format === 'xlsx') {
            return $exporter->excel($report, $prepared, $context, $business, $summary);
        }

        return $exporter->pdf($report, $prepared, $context, $business, $summary)
            ?? back()->withErrors(['report' => 'PDF en fazla '.number_format($exporter->pdfRowLimit, 0, ',', '.').' satır içerebilir. Filtreleri daraltın veya Excel olarak indirin.']);
    }

    /** Dosyalarda gösterilen filtre özeti: etiket => okunur değer. */
    private function summary(Report $report, array $prepared, ReportContext $context): array
    {
        $summary = [];
        foreach ($report->filters($context) as $name => $f) {
            $value = $prepared['filters'][$name] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $summary[$f['label']] = match ($f['type']) {
                'select' => $f['options'][$value] ?? $value,
                'date' => Carbon::parse($value)->format('d.m.Y'),
                default => (string) $value,
            };
        }

        return $summary;
    }

    /** @return array{0: Report, 1: ReportContext} */
    private function resolve(Area $area, string $key): array
    {
        $report = ReportRegistry::find($this->audience($area), $key) ?? abort(404);
        $context = new ReportContext($this->audience($area), $this->tenant->businessId(), $area === Area::Dealer ? $this->tenant->dealerRelationshipId() : null);

        return [$report, $context];
    }

    private function area(Request $request): Area
    {
        $area = Area::fromRequest($request);
        abort_unless(in_array($area, [Area::Business, Area::Dealer], true), 404);

        return $area;
    }

    private function audience(Area $area): string
    {
        return $area === Area::Dealer ? 'dealer' : 'business';
    }
}
