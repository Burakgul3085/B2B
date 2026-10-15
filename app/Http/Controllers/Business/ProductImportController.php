<?php

namespace App\Http\Controllers\Business;

use App\Domain\Catalog\ProductImporter;
use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportController extends Controller
{
    public function __construct(private ProductImporter $importer) {}

    public function index(): View
    {
        return view('business.catalog.import', [
            'jobs' => ImportJob::where('type', 'products')->latest('id')->limit(15)->get(),
            'columns' => ProductImporter::COLUMNS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,csv,txt'],
        ], [], ['file' => 'dosya']);

        // Büyük dosyalarda istek zaman aşımına uğramasın (en fazla 5.000 satır).
        set_time_limit(300);

        try {
            $job = $this->importer->import($request->file('file'), $request->boolean('create_missing', true), $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('business.products.import.show', $job);
    }

    public function show(ImportJob $job): View
    {
        return view('business.catalog.import-show', [
            'job' => $job,
            'importErrors' => $job->errors()->limit(500)->get(),
        ]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValuesWithStyle(array_values(ProductImporter::COLUMNS), (new Style)->withFontBold(true)));
            $writer->addRow(Row::fromValues([
                'KLM-001', '8690000000017', 'Tükenmez Kalem Mavi', '1.0 mm uç', 'Kalem > Tükenmez Kalem', 'Örnek Marka', 'TK-10',
                'ADET', 50, '4,20', '8,50', '7,25', 20, 'Hayır', 50, 50, 500, 'Evet', 'Evet', 1000,
            ]));
            $writer->close();
        }, 'urun-ice-aktarma-sablonu.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
