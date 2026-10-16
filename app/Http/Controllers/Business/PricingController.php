<?php

namespace App\Http\Controllers\Business;

use App\Domain\Audit\AuditLogger;
use App\Domain\Support\Decimal;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DealerGroup;
use App\Models\DealerRelationship;
use App\Models\DiscountRule;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Rules\NotBefore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Fiyat listeleri ve indirim kuralları. Bayiye özel fiyatlar bayi sayfasındadır. */
class PricingController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'discounts' ? 'discounts' : 'lists';

        return view('business.pricing.index', [
            'tab' => $tab,
            'lists' => PriceList::withCount('items')->with('groups')->orderBy('name')->get(),
            'rules' => DiscountRule::with(['product:id,name,sku', 'category.parent', 'group', 'relationship.dealer'])->orderByDesc('is_active')->orderByDesc('id')->get(),
            'categories' => Category::with('parent')->get()->sortBy(fn ($c) => $c->fullName())->mapWithKeys(fn ($c) => [$c->id => $c->fullName()])->all(),
            'groups' => DealerGroup::orderBy('name')->pluck('name', 'id'),
            'dealers' => DealerRelationship::with('dealer')->orderBy('dealer_code')->get()->mapWithKeys(fn ($r) => [$r->id => $r->dealer_code.' · '.$r->dealer->legal_name]),
        ]);
    }

    public function storeList(Request $request): RedirectResponse
    {
        $list = PriceList::create($this->validateList($request));
        $this->audit->log('pricing.price_list_created', $list, [], $list->only(['name', 'starts_on', 'ends_on']));

        return redirect()->route('business.price-lists.show', $list)->with('status', 'Fiyat listesi oluşturuldu. Şimdi ürün fiyatlarını ekleyin ve listeyi bir bayi grubuna atayın.');
    }

    public function showList(Request $request, PriceList $list): View
    {
        $term = trim($request->scalar('q'));

        $items = $list->items()->with('product:id,name,sku,list_price,dealer_price,price_includes_tax')
            ->when($term !== '', fn ($q) => $q->whereHas('product', function ($p) use ($term) {
                $like = addcslashes($term, '%_\\');
                $p->where('name', 'like', "%{$like}%")->orWhere('sku', 'like', "{$like}%");
            }))
            ->orderBy('id')->paginate(50)->withQueryString();

        return view('business.pricing.list-show', ['list' => $list->load('groups'), 'items' => $items, 'term' => $term]);
    }

    public function updateList(Request $request, PriceList $list): RedirectResponse
    {
        $data = $this->validateList($request, $list);
        $old = $list->only(array_keys($data));
        $list->update($data);
        $this->audit->log('pricing.price_list_updated', $list, $old, $data);

        return back()->with('status', 'Fiyat listesi güncellendi.');
    }

    public function storeItem(Request $request, PriceList $list): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('business_id', $request->user()->business_id)->whereNull('archived_at')],
            'price' => ['required', 'decimal:0,4', 'min:0', 'max:99999999999'],
        ], [], ['product_id' => 'ürün', 'price' => 'fiyat']);

        $item = PriceListItem::where('price_list_id', $list->id)->where('product_id', $data['product_id'])->first();
        $old = $item?->price;
        $item = PriceListItem::updateOrCreate(['price_list_id' => $list->id, 'product_id' => $data['product_id']], ['price' => $data['price']]);
        $this->audit->log('pricing.price_list_item_set', $item, $old !== null ? ['price' => (string) $old] : [], ['price' => (string) $item->price, 'price_list_id' => $list->id]);

        return back()->with('status', 'Fiyat kaydedildi.');
    }

    public function destroyItem(PriceList $list, PriceListItem $item): RedirectResponse
    {
        abort_unless($item->price_list_id === $list->id, 404);
        $this->audit->log('pricing.price_list_item_removed', $item, $item->only(['product_id', 'price']));
        $item->delete();

        return back()->with('status', 'Ürün listeden çıkarıldı.');
    }

    /** Listeyi seçilen ürünlerin standart bayi (yoksa liste) fiyatı üzerinden yüzde farkla toplu doldurur. */
    public function fillList(Request $request, PriceList $list): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['nullable', 'integer'],
            'percent' => ['required', 'decimal:0,2', 'min:-99', 'max:500'],
            'overwrite' => ['boolean'],
        ], [], ['percent' => 'yüzde']);

        $factor = bcadd('1', bcdiv((string) $data['percent'], '100', 6), 6);
        $count = 0;

        Product::notArchived()
            ->when($data['category'] ?? null, fn ($q, $c) => $q->where(fn ($q) => $q->where('category_id', $c)->orWhereIn('category_id', Category::where('parent_id', $c)->select('id'))))
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($list, $factor, $request, &$count) {
                $existing = PriceListItem::where('price_list_id', $list->id)->whereIn('product_id', $products->modelKeys())->pluck('product_id')->flip();
                foreach ($products as $product) {
                    if ($existing->has($product->id) && ! $request->boolean('overwrite')) {
                        continue;
                    }
                    $basis = (string) ($product->dealer_price ?? $product->list_price);
                    $price = Decimal::round(bcmul($basis, $factor, 10), 4);
                    PriceListItem::updateOrCreate(['price_list_id' => $list->id, 'product_id' => $product->id], ['price' => $price]);
                    $count++;
                }
            });

        $this->audit->log('pricing.price_list_filled', $list, [], $data + ['count' => $count]);

        return back()->with('status', "{$count} ürünün fiyatı listeye yazıldı.");
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $businessId = $request->user()->business_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scope' => ['required', Rule::in(['product', 'category'])],
            'product_id' => ['nullable', 'required_if:scope,product', 'integer', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'category_id' => ['nullable', 'required_if:scope,category', 'integer', Rule::exists('categories', 'id')->where('business_id', $businessId)],
            'target' => ['required', Rule::in(array_keys(DiscountRule::TARGETS))],
            'dealer_group_id' => ['nullable', 'required_if:target,group', 'integer', Rule::exists('dealer_groups', 'id')->where('business_id', $businessId)],
            'dealer_relationship_id' => ['nullable', 'required_if:target,dealer', 'integer', Rule::exists('dealer_relationships', 'id')->where('business_id', $businessId)],
            'percent' => ['required', 'decimal:0,2', 'gt:0', 'lt:100'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', new NotBefore('starts_on')],
        ], ['*.required_if' => ':attribute seçilmelidir.'], [
            'name' => 'kural adı', 'product_id' => 'ürün', 'category_id' => 'kategori', 'dealer_group_id' => 'bayi grubu',
            'dealer_relationship_id' => 'bayi', 'percent' => 'indirim oranı', 'starts_on' => 'başlangıç', 'ends_on' => 'bitiş',
        ]);

        // Kapsam dışı alanlar temizlenir; kural her zaman tek bir hedefe bağlıdır.
        $data['product_id'] = $data['scope'] === 'product' ? $data['product_id'] : null;
        $data['category_id'] = $data['scope'] === 'category' ? $data['category_id'] : null;
        $data['dealer_group_id'] = $data['target'] === 'group' ? $data['dealer_group_id'] : null;
        $data['dealer_relationship_id'] = $data['target'] === 'dealer' ? $data['dealer_relationship_id'] : null;

        $rule = DiscountRule::create($data);
        $this->audit->log('pricing.discount_created', $rule, [], $data);

        return redirect()->route('business.pricing', ['tab' => 'discounts'])->with('status', 'İndirim kuralı eklendi.');
    }

    public function toggleRule(DiscountRule $rule): RedirectResponse
    {
        $rule->update(['is_active' => ! $rule->is_active]);
        $this->audit->log('pricing.discount_toggled', $rule, [], ['is_active' => $rule->is_active]);

        return redirect()->route('business.pricing', ['tab' => 'discounts'])->with('status', $rule->is_active ? 'Kural etkinleştirildi.' : 'Kural durduruldu.');
    }

    private function validateList(Request $request, ?PriceList $list = null): array
    {
        $request->merge(['is_active' => $request->boolean('is_active', true)]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('price_lists', 'name')->where('business_id', $request->user()->business_id)->ignore($list?->id)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', new NotBefore('starts_on')],
            'is_active' => ['boolean'],
        ], [], ['name' => 'liste adı', 'starts_on' => 'başlangıç', 'ends_on' => 'bitiş']);
    }
}
