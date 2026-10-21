<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Audit\AuditLogger;
use App\Domain\Store\Catalog\ProductImporter;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Excel/CSV ile mağaza ürünü ekleme ve güncelleme. Sonuç aynı sayfada gösterilir. */
class ProductImportController extends Controller
{
    public function show(): View
    {
        return view('store.admin.catalog.import', ['result' => session('import_result')]);
    }

    public function store(Request $request, ProductImporter $importer, AuditLogger $audit): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:'.(ProductImporter::MAX_BYTES / 1024)]], [], ['file' => 'dosya']);

        try {
            $result = $importer->import($request->file('file'), $request->boolean('create_missing'));
        } catch (DomainException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $audit->log('store.products_imported', null, [], [
            'file' => mb_substr($request->file('file')->getClientOriginalName(), 0, 200),
        ] + array_intersect_key($result, array_flip(['total', 'created', 'updated', 'failed'])));

        return redirect()->route('store_admin.products.import')->with('import_result', $result);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValuesWithStyle(array_values(ProductImporter::COLUMNS), (new Style)->withFontBold(true)));
            // Tek seçenekli ürün
            $writer->addRow(Row::fromValues(['DFT-A4-80', 'Spiralli Defter A4 80 Yaprak', '', '', '', '8690000000100', 'Defterler > Spiralli Defter', 'Örnek Marka',
                'Kareli, sert kapak', '89,90', '109,90', 20, '0,5', 450, 'Evet']));
            // Seçenekli ürün: aynı "Ürün Grubu" değerine sahip satırlar tek ürünün renkleridir
            $writer->addRow(Row::fromValues(['JEL-07-MAVI', 'Jel Kalem 0.7 mm', 'JEL-07-MAVI', 'Renk', 'Mavi', '', 'Kalemler > Jel Kalem', 'Örnek Marka',
                'Hızlı kuruyan mürekkep', '24,90', '', 20, '', 15, 'Evet']));
            $writer->addRow(Row::fromValues(['JEL-07-SIYAH', 'Jel Kalem 0.7 mm', 'JEL-07-MAVI', 'Renk', 'Siyah', '', 'Kalemler > Jel Kalem', 'Örnek Marka',
                '', '24,90', '', 20, '', 15, 'Evet']));
            $writer->close();
        }, 'magaza-urun-sablonu.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
