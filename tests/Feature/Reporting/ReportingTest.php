<?php

namespace Tests\Feature\Reporting;

use App\Domain\Ledger\LedgerService;
use App\Domain\Ordering\OrderService;
use App\Domain\Reporting\DashboardMetrics;
use App\Domain\Reporting\ReportContext;
use App\Domain\Reporting\ReportExporter;
use App\Domain\Reporting\ReportRegistry;
use App\Domain\Reporting\ReportRunner;
use App\Models\Business;
use App\Models\BusinessModule;
use App\Models\CurrentAccount;
use App\Models\DealerRelationship;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** Aşama 8 — raporlama merkezi, dosya çıktıları ve dashboard grafik verisi. */
class ReportingTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    /** Onaylanmış sipariş: dealer_price × miktar + %20 KDV. */
    private function approvedOrder(Business $business, DealerRelationship $rel, string $qty, array $product = []): Order
    {
        $product = $this->makeProduct($business, $product + ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '1000');
        $order = $this->placeOrder($business, $rel, [$product->id => $qty]);

        return $this->inTenant($business, fn () => app(OrderService::class)
            ->approve($order, [], $this->defaultWarehouse($business)->id, [], $business->user->id)->fresh());
    }

    /** @return array<int, array<int, mixed>> XLSX satırları (hücre değerleri) */
    private function xlsxRows(TestResponse $response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'rpt').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(fn ($c) => $c instanceof FormulaCell ? 'FORMULA:'.$c->getValue() : $c->getValue(), $row->cells);
            }
            break;
        }
        $reader->close();
        @unlink($path);

        return $rows;
    }

    public function test_every_report_page_renders_for_its_area(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $this->approvedOrder($business, $rel, '3');

        $this->actingAs($business->user, 'business')->get(route('business.reports.index'))->assertOk();
        foreach (ReportRegistry::for('business') as $report) {
            $this->get(route('business.reports.show', $report->key()))->assertOk()->assertSee($report->title());
        }
        $this->get(route('business.reports.show', ['key' => 'dealer-prices', 'dealer' => $rel->id]))->assertOk();

        $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.reports.index'))->assertOk();
        foreach (ReportRegistry::for('dealer') as $report) {
            $this->get(route('dealer.reports.show', $report->key()))->assertOk();
        }
        // Toptancı raporu bayi alanında yoktur.
        $this->get(route('dealer.reports.show', 'products'))->assertNotFound();
    }

    public function test_screen_and_excel_totals_match(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $this->approvedOrder($business, $rel, '3');   // 30 + 6 KDV
        $this->approvedOrder($business, $rel, '7');   // 70 + 14 KDV

        $this->actingAs($business->user, 'business');
        $this->get(route('business.reports.show', 'orders'))->assertOk()->assertSee('₺120,00');

        $rows = $this->xlsxRows($this->get(route('business.reports.export', ['key' => 'orders', 'format' => 'xlsx']))->assertOk());
        $headerRow = collect($rows)->first(fn ($r) => in_array('Genel toplam', $r, true));
        $header = array_search('Genel toplam', $headerRow, true);
        $totalRow = collect($rows)->first(fn ($r) => ($r[0] ?? null) === 'TOPLAM');
        $this->assertNotNull($totalRow, 'Excel dosyasında TOPLAM satırı yok.');
        $this->assertEqualsWithDelta(120.00, $totalRow[$header], 0.001);
        // Antet: unvan, rapor adı, kayıt sayısı; tarih hücreleri gerçek tarih değeri.
        $this->assertSame($business->legal_name, $rows[0][0]);
        $this->assertSame('Siparişler', $rows[1][0]);
        $this->assertStringContainsString('Kayıt sayısı: 2', $rows[2][0]);
        $this->assertInstanceOf(\DateTimeInterface::class, $rows[array_search($headerRow, $rows, true) + 1][1]);

        $report = ReportRegistry::find('business', 'orders');
        $context = new ReportContext('business', $business->id);
        $totals = $this->inTenant($business, function () use ($report, $context) {
            $runner = app(ReportRunner::class);

            return $runner->totals($report, $runner->prepare($report, [], $context), $context);
        });
        $this->assertSame(120.0, (float) $totals['grand_total']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported']);
    }

    public function test_excel_text_cells_are_never_formulas(): void
    {
        $business = $this->makeBusiness();
        $this->makeProduct($business, ['name' => '=HYPERLINK("http://kotu.test","tikla")', 'sku' => '+SKU-1']);

        $rows = $this->xlsxRows($this->actingAs($business->user, 'business')
            ->get(route('business.reports.export', ['key' => 'products', 'format' => 'xlsx']))->assertOk());

        $flat = array_merge(...$rows);
        $this->assertEmpty(array_filter($flat, fn ($v) => is_string($v) && str_starts_with($v, 'FORMULA:')));
        $this->assertContains("'=HYPERLINK(\"http://kotu.test\",\"tikla\")", $flat);
        $this->assertContains("'+SKU-1", $flat);
    }

    public function test_pdf_is_generated_and_refused_above_row_limit(): void
    {
        $business = $this->makeBusiness();
        $this->makeProduct($business);
        $this->makeProduct($business);

        $this->actingAs($business->user, 'business');
        $pdf = $this->get(route('business.reports.export', ['key' => 'products', 'format' => 'pdf']))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->streamedContent());

        $exporter = app(ReportExporter::class);
        $exporter->pdfRowLimit = 1;
        $this->app->instance(ReportExporter::class, $exporter);

        $this->from(route('business.reports.show', 'products'))
            ->get(route('business.reports.export', ['key' => 'products', 'format' => 'pdf']))
            ->assertRedirect(route('business.reports.show', 'products'))->assertSessionHasErrors('report');
        $this->get(route('business.reports.export', ['key' => 'products', 'format' => 'csv']))->assertNotFound();
    }

    public function test_reports_are_isolated_between_businesses_and_dealers(): void
    {
        $business = $this->makeBusiness();
        $other = $this->makeBusiness();
        $relA = $this->orderingDealer($business);
        $relB = $this->orderingDealer($business);
        $foreign = $this->orderingDealer($other);
        $mine = $this->approvedOrder($business, $relA, '2');
        $sibling = $this->approvedOrder($business, $relB, '4');
        $this->approvedOrder($other, $foreign, '5');
        $this->makeProduct($other, ['name' => 'Yabancı Ürün']);

        // Sipariş numaraları işletme bazında sıralıdır; ayırt etmek için bayi unvanları kullanılır.
        [$nameA, $nameB, $nameForeign] = [$relA->dealer->legal_name, $relB->dealer->legal_name, $foreign->dealer->legal_name];

        $this->actingAs($business->user, 'business');
        $this->get(route('business.reports.show', 'orders'))->assertOk()
            ->assertSee($nameA)->assertSee($nameB)->assertDontSee($nameForeign)->assertSee('₺72,00');
        $this->get(route('business.reports.show', 'products'))->assertDontSee('Yabancı Ürün');
        // Başka işletmenin bayisi filtre olarak verilemez.
        $this->get(route('business.reports.show', ['key' => 'dealer-prices', 'dealer' => $foreign->id]))->assertSessionHasErrors('dealer');

        $this->actingAs($relA->dealer->user, 'dealer');
        $this->get(route('dealer.reports.show', 'my-orders'))->assertOk()
            ->assertSee($mine->order_no)->assertDontSee($sibling->order_no)->assertSee('₺24,00')->assertDontSee('₺48,00')->assertDontSee('₺60,00');
        $rows = $this->xlsxRows($this->get(route('dealer.reports.export', ['key' => 'my-orders', 'format' => 'xlsx']))->assertOk());
        $flat = array_merge(...$rows);
        $this->assertContains($mine->order_no, $flat);
        $this->assertNotContains($sibling->order_no, $flat);
    }

    public function test_dealer_reports_never_expose_purchase_price(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $this->approvedOrder($business, $rel, '2', ['purchase_price' => '3.17']);

        $this->actingAs($rel->dealer->user, 'dealer');
        foreach (ReportRegistry::for('dealer') as $report) {
            $this->assertArrayNotHasKey('purchase_price', $report->columns());
            $this->get(route('dealer.reports.show', $report->key()))->assertOk()->assertDontSee('3,17')->assertDontSee('Alış');
        }
        $this->get(route('dealer.reports.show', ['key' => 'my-products', 'cols' => ['purchase_price']]))->assertSessionHasErrors();
    }

    public function test_reports_module_can_be_disabled(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        BusinessModule::updateOrCreate(['business_id' => $business->id, 'module_key' => 'reports'], ['enabled' => false]);

        $this->actingAs($business->user, 'business')->get(route('business.reports.index'))->assertForbidden();
        $this->get(route('business.dashboard'))->assertOk()->assertDontSee(route('business.reports.index'));
        $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.reports.show', 'my-orders'))->assertForbidden();
    }

    public function test_dashboard_cache_is_invalidated_by_any_order_change(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $this->approvedOrder($business, $rel, '1');               // 12,00
        $this->travel(10)->seconds();

        $metrics = app(DashboardMetrics::class);
        $snapshot = fn () => $this->inTenant($business, fn () => $metrics->snapshot($metrics->range('month'), ['period' => 'month']));
        $this->assertSame('12.00', $snapshot()['kpis']['sales']);

        // Önbellekten okunur: sipariş sorgusu çalışmaz (yalnızca sürüm ve tahsilat sorguları).
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame('12.00', $snapshot()['kpis']['sales']);
        $this->assertLessThanOrEqual(2, count(DB::getQueryLog()));

        // Yeni onay: sürüm değişir, sonuç hemen güncellenir (önbellek eski veri döndürmez).
        $this->approvedOrder($business, $rel, '2');               // +24,00
        $this->assertSame('36.00', $snapshot()['kpis']['sales']);
        $this->travel(10)->seconds();
        $this->assertSame('36.00', $snapshot()['kpis']['sales']);

        // Tahsilat önbellekten bağımsız, her zaman canlı.
        $account = $this->inTenant($business, fn () => CurrentAccount::where('dealer_relationship_id', $rel->id)->sole());
        $this->inTenant($business, fn () => app(LedgerService::class)->recordCollection($account, [
            'collected_on' => now()->toDateString(), 'amount' => '5.00', 'method' => 'cash'], $business->user->id));
        $this->assertSame('5.00', $snapshot()['kpis']['collections']);
    }

    public function test_dashboard_metrics_use_only_approved_sales_and_compare_periods(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 20)->setTime(12, 0));
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);

        $this->travelTo(now()->setDate(2026, 5, 10));
        $this->approvedOrder($business, $rel, '5');                // önceki dönem: 60,00
        $this->travelTo(now()->setDate(2026, 6, 3));
        $this->approvedOrder($business, $rel, '10');               // bu dönem: 120,00
        $this->approvedOrder($business, $rel, '2', ['category_id' => null]); // 24,00
        $pending = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $pending, '10');
        $this->placeOrder($business, $rel, [$pending->id => '1']); // karar bekleyen: satış değil
        $this->travelTo(now()->setDate(2026, 6, 20));

        $metrics = app(DashboardMetrics::class);
        [$kpis, $series, $status, $dealers] = $this->inTenant($business, function () use ($metrics) {
            $range = $metrics->range('month');
            $f = ['period' => 'month'];

            return [$metrics->kpis($range, $f), $metrics->salesSeries($range, $f), $metrics->statusGroups($range, $f), $metrics->dealers($range, $f)];
        });

        $this->assertSame('144.00', $kpis['sales']);
        $this->assertSame(2, $kpis['orders']);
        $this->assertSame('72.00', $kpis['average']);
        $this->assertSame('0.00', $kpis['sales_prev']); // 12.05–31.05 aralığında satış yok
        $this->assertCount(20, $series['labels']);
        $this->assertSame(144.0, array_sum($series['current']));
        $this->assertSame(144.0, $series['current'][2]); // 03.06
        $this->assertSame(['Karar bekleyen' => 1, 'Onaylandı / hazırlanıyor' => 2, 'Tamamlandı' => 0, 'Reddedildi / iptal' => 0], $status);
        $this->assertSame([144.0], array_values($dealers));

        $twelve = $this->inTenant($business, fn () => $metrics->kpis($metrics->range('12m'), ['period' => '12m']));
        $this->assertSame('204.00', $twelve['sales']);

        // Bayi filtresi ve dashboard sayfası
        $other = $this->orderingDealer($business);
        $filtered = $this->inTenant($business, fn () => $metrics->kpis($metrics->range('month'), ['period' => 'month', 'dealer' => $other->id]));
        $this->assertSame('0.00', $filtered['sales']);

        $this->actingAs($business->user, 'business')->get(route('business.dashboard', ['period' => '12m']))->assertOk()
            ->assertSee('Satış eğrisi')->assertSee('data-chart', false);
        $this->get(route('business.dashboard', ['period' => 'yanlis']))->assertSessionHasErrors('period');

        $monthly = $this->inTenant($business, fn () => $metrics->dealerMonthly($rel->id));
        $this->assertCount(12, $monthly['values']);
        $this->assertSame([60.0, 144.0], array_slice($monthly['values'], -2));
        $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.dashboard'))->assertOk()->assertSee('Aylık alımlarınız');
    }
}
