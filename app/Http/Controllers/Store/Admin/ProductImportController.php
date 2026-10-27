<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Audit\AuditLogger;
use App\Domain\Store\Catalog\ProductImporter;
use App\Domain\Support\ImportTemplate;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
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
        $help = [
            'sku' => [18, true, 'Ürünün benzersiz kodu. Mağazada varsa ürün güncellenir, yoksa yeni ürün eklenir.', 'DFT-A4-80'],
            'name' => [34, true, 'Müşterinin göreceği ürün adı.', 'Spiralli Defter A4 80 Yaprak'],
            'group' => [18, false, 'Seçenekli ürünlerde (renk, boyut) aynı ürünün bütün satırlarına aynı değeri yazın, ör. ilk seçeneğin stok kodu. Tek seçenekli üründe boş bırakın.', 'JEL-07-MAVI'],
            'variant_name' => [14, false, 'Seçenek türü: Renk, Boyut gibi. Yalnızca seçenekli ürünlerde.', 'Renk'],
            'option_value' => [14, false, 'Bu satırdaki seçenek: Mavi, A5 gibi.', 'Mavi'],
            'barcode' => [17, false, 'EAN-13 barkod (varsa).', '8690000000100'],
            'category' => [28, false, 'Kategori. Alt kategori ">" ile yazılır. Mağazada yoksa yükleme ekranındaki seçenekle oluşturulur.', 'Defterler > Spiralli Defter'],
            'brand' => [16, false, 'Marka adı (varsa).', 'Faber-Castell'],
            'description' => [36, false, 'Ürün açıklaması.', 'Kareli, sert kapak'],
            'price' => [16, true, 'KDV dahil satış fiyatı. Virgül veya nokta kullanılabilir. Yeni ürün için zorunlu.', '89,90'],
            'compare_at_price' => [14, false, 'İndirimi göstermek için eski fiyat; satış fiyatından yüksek olmalı.', '109,90'],
            'vat_rate' => [13, true, 'KDV oranı. Yeni ürün için zorunlu.', '20', ['0', '1', '10', '20']],
            'desi' => [9, false, 'Kargo desisi.', '0,5'],
            'weight_grams' => [12, false, 'Gram cinsinden ağırlık.', '450'],
            'is_published' => [11, false, '"Evet" yazılmazsa yeni ürün taslak eklenir; görselleri ekledikten sonra yayına alabilirsiniz.', 'Evet', ['Evet', 'Hayır']],
            'stock' => [10, false, 'Yeni üründe ilk stok girişi; mevcut üründe stok bu adede getirilir (sayım gibi). Boşsa stoğa dokunulmaz.', '120'],
            'critical_stock' => [12, false, 'Stok bu adede inince uyarı verilir.', '10'],
        ];
        $columns = [];
        foreach (ProductImporter::COLUMNS as $key => $title) {
            [$width, $required, $hint, $example] = $help[$key];
            $columns[] = ['title' => $title, 'width' => $width, 'required' => $required, 'hint' => $hint, 'example' => $example] + (isset($help[$key][4]) ? ['options' => $help[$key][4]] : []);
        }

        return response()->streamDownload(function () use ($columns) {
            (new ImportTemplate)->write('php://output', [
                'company' => StoreSetting::get('company_title') ?: config('store.name'),
                'title' => 'Mağaza ürün yükleme şablonu',
                'intro' => [
                    'Her satır bir ürün (veya bir ürünün bir seçeneği) olmalıdır. Turuncu başlıklı sütunlar yeni ürün için zorunludur.',
                    'Stok Kodu mağazada varsa ürün güncellenir; güncellemede boş bıraktığınız hücreler değişmez. Görseller panelden eklenir.',
                    'Seçenekli ürünlerde (renk, boyut) her seçenek ayrı satırdır; aynı ürünün satırlarına aynı Ürün Grubu değerini yazın.',
                    'Bir hücreye tıkladığınızda o sütunun açıklaması görünür. Yayında ve KDV Oranı sütunlarında açılır listeden seçin.',
                    'Hatalı satırlar atlanır, diğerleri kaydedilir; hatalar satır numarasıyla gösterilir. Düzeltip yeniden yüklediğinizde ürünler çoğalmaz.',
                ],
                'notes' => ['En fazla '.ProductImporter::MAX_ROWS.' satır ve 10 MB. Yalnızca ilk sayfa (Ürünler) okunur; başlık satırını değiştirmeyin.'],
                'columns' => $columns,
                'examples' => [
                    ['DFT-A4-80', 'Spiralli Defter A4 80 Yaprak', '', '', '', '8690000000100', 'Defterler > Spiralli Defter', 'Örnek Marka', 'Kareli, sert kapak', '89,90', '109,90', '20', '0,5', '450', 'Evet', '120', '10'],
                    ['JEL-07-MAVI', 'Jel Kalem 0.7 mm', 'JEL-07-MAVI', 'Renk', 'Mavi', '', 'Kalemler > Jel Kalem', 'Örnek Marka', 'Hızlı kuruyan mürekkep', '24,90', '', '20', '', '15', 'Evet', '300', '20'],
                    ['JEL-07-SIYAH', 'Jel Kalem 0.7 mm', 'JEL-07-MAVI', 'Renk', 'Siyah', '', 'Kalemler > Jel Kalem', 'Örnek Marka', '', '24,90', '', '20', '', '15', 'Evet', '250', '20'],
                ],
            ]);
        }, 'magaza-urun-sablonu.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
