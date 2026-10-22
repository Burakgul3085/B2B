<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Discounts\DiscountAdmin;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreDiscount;
use App\Models\Store\StoreDiscountRedemption;
use App\Models\Store\StoreProductVariant;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Kampanya ve kuponlar: liste (kullanım sayısı ve toplam indirimle), ekleme, düzenleme, silme. */
class DiscountController extends Controller
{
    public function __construct(private DiscountAdmin $discounts) {}

    public function index(): View
    {
        return view('store.admin.discounts.index', [
            'discounts' => StoreDiscount::query()
                ->withCount(['redemptions as used_count' => fn ($q) => $q->where('status', 'active')])
                ->withSum(['redemptions as used_amount' => fn ($q) => $q->where('status', 'active')], 'amount')
                ->orderByDesc('is_active')->latest('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('store.admin.discounts.form', ['discount' => new StoreDiscount(['type' => 'percent']), 'used' => false] + $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $discount = $this->discounts->save($request->all());

        return redirect()->route('store_admin.discounts.edit', $discount)->with('status', 'İndirim oluşturuldu.');
    }

    public function edit(StoreDiscount $storeDiscount): View
    {
        return view('store.admin.discounts.form', [
            'discount' => $storeDiscount,
            'used' => $storeDiscount->redemptions()->exists(),
            'recent' => $storeDiscount->redemptions()->with('order:id,order_no,status')->latest('id')->limit(20)->get(),
            'codeCount' => $storeDiscount->codes()->count(),
            'usedCodeCount' => StoreDiscountRedemption::where('discount_id', $storeDiscount->id)->where('status', 'active')
                ->whereIn('code', $storeDiscount->codes()->select('code'))->count(),
        ] + $this->formData($storeDiscount));
    }

    public function generateCodes(Request $request, StoreDiscount $storeDiscount): RedirectResponse
    {
        try {
            $count = $this->discounts->generateCodes($storeDiscount, $request->only(['count', 'prefix']));
        } catch (DomainException $e) {
            return back()->withErrors(['count' => $e->getMessage()]);
        }

        return back()->with('status', "{$count} kod üretildi. Listeyi Excel/CSV olarak indirebilirsiniz.");
    }

    /** Toplu kodların CSV listesi (kullanıldı bilgisiyle). */
    public function exportCodes(StoreDiscount $storeDiscount): StreamedResponse
    {
        $used = StoreDiscountRedemption::where('discount_id', $storeDiscount->id)->where('status', 'active')->whereNotNull('code')->pluck('code')->flip();

        return response()->streamDownload(function () use ($storeDiscount, $used) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel'in Türkçe karakterleri doğru açması için
            fputcsv($out, ['Kod', 'Kullanıldı'], ';');
            $storeDiscount->codes()->select(['id', 'code'])->chunk(1000, function ($codes) use ($out, $used) {
                foreach ($codes as $c) {
                    fputcsv($out, [$c->code, isset($used[$c->code]) ? 'Evet' : 'Hayır'], ';');
                }
            });
            fclose($out);
        }, 'kupon-kodlari-'.$storeDiscount->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Kapsam seçimi için kategoriler ve mevcut ürün SKU'ları. */
    private function formData(?StoreDiscount $discount): array
    {
        return [
            'categories' => StoreCategory::orderBy('name')->get(['id', 'name', 'parent_id']),
            'skus' => $discount?->product_ids
                ? StoreProductVariant::whereIn('product_id', $discount->product_ids)->orderBy('sku')->pluck('sku')->implode(', ')
                : '',
        ];
    }

    public function update(Request $request, StoreDiscount $storeDiscount): RedirectResponse
    {
        try {
            $this->discounts->save($request->all(), $storeDiscount);
        } catch (DomainException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'İndirim güncellendi.');
    }

    public function destroy(StoreDiscount $storeDiscount): RedirectResponse
    {
        try {
            $this->discounts->delete($storeDiscount);
        } catch (DomainException $e) {
            return back()->withErrors(['discount' => $e->getMessage()]);
        }

        return redirect()->route('store_admin.discounts.index')->with('status', 'İndirim silindi.');
    }
}
