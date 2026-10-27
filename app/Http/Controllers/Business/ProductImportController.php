<?php

namespace App\Http\Controllers\Business;

use App\Domain\Catalog\ProductImporter;
use App\Domain\Support\ImportTemplate;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ImportJob;
use App\Models\TaxRate;
use App\Models\Unit;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
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

    public function template(TenantContext $tenant): StreamedResponse
    {
        // Açılır listeler işletmenin kendi birimlerinden ve aktif KDV oranlarından gelir.
        $units = Unit::orderBy('code')->pluck('code')->all();
        $rates = TaxRate::where('is_active', true)->orderBy('rate')->pluck('rate')->map(fn ($r) => rtrim(rtrim((string) $r, '0'), '.'))->unique()->values()->all();
        $yesNo = ['Evet', 'Hayır'];
        $help = [
            'sku' => [16, true, 'Ürünün benzersiz kodu. Sistemde varsa ürün güncellenir, yoksa yeni ürün oluşturulur.', 'KLM-001'],
            'barcode' => [17, false, 'EAN-13 barkod (varsa).', '8690000000017'],
            'name' => [32, true, 'Bayilerin göreceği ürün adı. Yeni ürün için zorunlu.', 'Tükenmez Kalem Mavi'],
            'description' => [30, false, 'Ürün açıklaması.', '1.0 mm uç'],
            'category' => [26, false, 'Kategori. Alt kategori ">" ile yazılır (en fazla iki seviye). Yoksa yükleme ekranındaki seçenekle oluşturulur.', 'Kalem > Tükenmez Kalem'],
            'brand' => [16, false, 'Marka adı (varsa).', 'Örnek Marka'],
            'model' => [12, false, 'Model veya üretici kodu.', 'TK-10'],
            'unit' => [10, false, 'Satış birimi. Boşsa ADET kabul edilir.', 'ADET', $units ?: null],
            'pack_quantity' => [12, false, 'Bir kolideki miktar.', '50'],
            'purchase_price' => [13, false, 'Alış fiyatı (bayiler görmez).', '4,20'],
            'list_price' => [13, true, 'Liste fiyatı. Virgül veya nokta kullanılabilir. Yeni ürün için zorunlu.', '8,50'],
            'dealer_price' => [13, false, 'Bayilere uygulanan fiyat.', '7,25'],
            'tax_rate' => [13, true, 'KDV oranı; işletmenizde tanımlı ve aktif olmalı. Yeni ürün için zorunlu.', '20', $rates ?: null],
            'price_includes_tax' => [10, false, 'Fiyatlar KDV dahil mi?', 'Hayır', $yesNo],
            'min_order_qty' => [12, false, 'Bayinin en az sipariş edebileceği miktar.', '50'],
            'order_step' => [11, false, 'Sipariş miktarı bu sayının katları olmalı (ör. koli içi).', '50'],
            'critical_stock_level' => [11, false, 'Stok bu seviyeye inince uyarı verilir.', '500'],
            'is_active' => [9, false, 'Ürün satışta mı?', 'Evet', $yesNo],
            'visible_to_dealers' => [12, false, 'Ürün bayi kataloğunda görünsün mü?', 'Evet', $yesNo],
            'opening_stock' => [13, false, 'Yalnızca yeni üründe, varsayılan sevk deposuna ilk stok olarak yazılır.', '1000'],
        ];
        $columns = [];
        foreach (ProductImporter::COLUMNS as $key => $title) {
            [$width, $required, $hint, $example] = $help[$key];
            $columns[] = ['title' => $title, 'width' => $width, 'required' => $required, 'hint' => $hint, 'example' => $example] + (isset($help[$key][4]) ? ['options' => $help[$key][4]] : []);
        }
        $company = Business::find($tenant->businessId())?->legal_name ?? config('app.name');

        return response()->streamDownload(function () use ($columns, $company) {
            (new ImportTemplate)->write('php://output', [
                'company' => $company,
                'title' => 'Ürün içe aktarma şablonu',
                'intro' => [
                    'Her satır bir üründür. Turuncu başlıklı sütunlar yeni ürün için zorunludur.',
                    'Ürün Kodu sistemde varsa ürün güncellenir; güncellemede yalnızca dosyada dolu olan sütunlar değişir.',
                    'Fiyatlar 8,50 veya 8.50 biçiminde yazılabilir. Kategori "Ana > Alt" biçiminde olabilir.',
                    'Bir hücreye tıkladığınızda o sütunun açıklaması görünür. Birim, KDV ve Evet/Hayır sütunlarında açılır listeden seçin.',
                    'Hatalı satırlar atlanır, diğerleri kaydedilir; hatalar satır numarasıyla listelenir.',
                ],
                'notes' => ['En fazla 5.000 satır ve 10 MB. Yalnızca ilk sayfa (Ürünler) okunur; başlık satırını değiştirmeyin.'],
                'columns' => $columns,
                'examples' => [
                    ['KLM-001', '8690000000017', 'Tükenmez Kalem Mavi', '1.0 mm uç', 'Kalem > Tükenmez Kalem', 'Örnek Marka', 'TK-10', 'ADET', '50', '4,20', '8,50', '7,25', '20', 'Hayır', '50', '50', '500', 'Evet', 'Evet', '1000'],
                    ['DFT-A4-80', '8690000000024', 'Spiralli Defter A4 80 Yaprak', 'Kareli, sert kapak', 'Defter > Spiralli Defter', 'Örnek Marka', 'SD-80', 'ADET', '24', '38,00', '65,00', '', '20', 'Hayır', '24', '24', '100', 'Evet', 'Evet', '480'],
                ],
            ]);
        }, 'urun-ice-aktarma-sablonu.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
