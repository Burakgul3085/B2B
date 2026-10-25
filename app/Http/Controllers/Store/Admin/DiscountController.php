<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Discounts\DiscountAdmin;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreDiscount;
use App\Models\Store\StoreDiscountRedemption;
use App\Models\Store\StoreProduct;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Kampanya ve kuponlar: liste (durum süzgeci, kullanım sayısı ve toplam indirimle), ekleme, kopyalama, düzenleme, silme. */
class DiscountController extends Controller
{
    /** Liste süzgeçleri: anahtar => [etiket, kapsadığı durumlar]. */
    private const FILTERS = [
        'yayinda' => ['Yayında', ['live']],
        'planli' => ['Planlandı', ['scheduled']],
        'bitti' => ['Sona erdi', ['ended', 'limit']],
        'pasif' => ['Pasif', ['inactive']],
    ];

    public function __construct(private DiscountAdmin $discounts) {}

    public function index(Request $request): View
    {
        $all = StoreDiscount::query()
            ->withCount(['redemptions as used_count' => fn ($q) => $q->where('status', 'active')])
            ->withSum(['redemptions as used_amount' => fn ($q) => $q->where('status', 'active')], 'amount')
            ->orderByDesc('is_active')->latest('id')->get()
            ->each(fn (StoreDiscount $d) => $d->setAttribute('state', $d->status($d->used_count)));

        $filter = array_key_exists((string) $request->query('durum'), self::FILTERS) ? (string) $request->query('durum') : null;
        $counts = collect(self::FILTERS)->map(fn ($f) => $all->whereIn('state', $f[1])->count());

        return view('store.admin.discounts.index', [
            'discounts' => $filter ? $all->whereIn('state', self::FILTERS[$filter][1])->values() : $all,
            'filters' => collect(self::FILTERS)->map(fn ($f) => $f[0]),
            'counts' => $counts,
            'total' => $all->count(),
            'filter' => $filter,
        ]);
    }

    /** Yeni indirim; ?kopya=ID ile var olan indirimin ayarları (kod ve görsel hariç) forma doldurulur. */
    public function create(Request $request): View
    {
        $discount = new StoreDiscount(['type' => 'percent']);
        if ($source = StoreDiscount::find($request->integer('kopya') ?: null)) {
            $discount = $source->replicate(['code', 'image_path', 'show_on_home']);
            $discount->name = mb_substr($source->name.' (kopya)', 0, 120);
            $discount->is_active = false;
        }

        return view('store.admin.discounts.form', ['discount' => $discount, 'used' => false, 'copyKind' => $source ? ($source->code ? 'coupon' : 'campaign') : null]
            + $this->formData($discount));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->validateImage($request);
        $discount = DB::transaction(function () use ($request) {
            $discount = $this->discounts->save($request->except('image'));
            if ($request->hasFile('image')) {
                $this->discounts->setImage($discount, $request->file('image'));
            }

            return $discount;
        });

        return redirect()->route('store_admin.discounts.edit', $discount)->with('status', 'İndirim oluşturuldu.');
    }

    public function edit(StoreDiscount $storeDiscount): View
    {
        return view('store.admin.discounts.form', [
            'discount' => $storeDiscount,
            'used' => $storeDiscount->redemptions()->exists(),
            'copyKind' => null,
            'recent' => $storeDiscount->redemptions()->with('order:id,order_no,status')->latest('id')->limit(20)->get(),
            'codeCount' => $storeDiscount->codes()->count(),
            'usedCodeCount' => StoreDiscountRedemption::where('discount_id', $storeDiscount->id)->where('status', 'active')
                ->whereIn('code', $storeDiscount->codes()->select('code'))->count(),
        ] + $this->formData($storeDiscount));
    }

    /** Ürün seçici: ad veya SKU ile arar (en fazla 20 ürün). */
    public function lookup(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(StoreProduct::query()
            ->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', addcslashes($q, '%_\\').'%')))
            ->with('variants:id,product_id,sku')->orderBy('name')->limit(20)->get(['id', 'name'])
            ->map(fn (StoreProduct $p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->variants->first()?->sku]));
    }

    /** Kampanya kartı görseli (yönetim önizlemesi). */
    public function image(StoreDiscount $storeDiscount): StreamedResponse
    {
        abort_unless($storeDiscount->image_path && Storage::disk('local')->exists($storeDiscount->image_path), 404);

        return Storage::disk('local')->response($storeDiscount->image_path, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=86400']);
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

    /** Kapsam seçimi için girintili kategori ağacı ve seçili ürünler. */
    private function formData(StoreDiscount $discount): array
    {
        $ids = array_map('intval', (array) old('product_ids', $discount->product_ids ?? []));

        return [
            'categories' => $this->tree(StoreCategory::orderBy('sort')->orderBy('name')->get(['id', 'name', 'parent_id'])),
            'products' => $ids ? StoreProduct::whereIn('id', $ids)->with('variants:id,product_id,sku')->orderBy('name')->get(['id', 'name'])
                ->map(fn (StoreProduct $p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->variants->first()?->sku])->values()->all() : [],
            'skus' => '',
        ];
    }

    /** @return Collection<int, StoreCategory> ağaç sırasıyla, her birinde "depth" */
    private function tree(Collection $all, ?int $parent = null, int $depth = 0): Collection
    {
        return $all->where('parent_id', $parent)->flatMap(fn (StoreCategory $c) => collect([$c->setAttribute('depth', $depth)])
            ->merge($depth < 3 ? $this->tree($all, $c->id, $depth + 1) : []));
    }

    private function validateImage(Request $request): void
    {
        $request->validate(['image' => ['nullable', 'file', 'max:6144']], [], ['image' => 'görsel']);
    }

    public function update(Request $request, StoreDiscount $storeDiscount): RedirectResponse
    {
        $this->validateImage($request);
        try {
            DB::transaction(function () use ($request, $storeDiscount) {
                $this->discounts->save($request->except('image'), $storeDiscount);
                if ($request->hasFile('image') || $request->boolean('remove_image')) {
                    $this->discounts->setImage($storeDiscount, $request->file('image'));
                }
            });
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
