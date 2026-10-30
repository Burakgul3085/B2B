<?php

namespace App\Http\Controllers\Business;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\AccountEmailService;
use App\Domain\Auth\Area;
use App\Domain\Dealers\DealerOnboarding;
use App\Domain\Notifications\Notifier;
use App\Domain\Pricing\PriceResolver;
use App\Http\Controllers\Controller;
use App\Models\DealerAddress;
use App\Models\DealerGroup;
use App\Models\DealerProductPrice;
use App\Models\DealerRelationship;
use App\Models\Product;
use App\Rules\NotBefore;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class DealerController extends Controller
{
    public function __construct(private DealerOnboarding $onboarding, private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(DealerRelationship::STATUSES))],
            'group' => ['nullable', 'integer'],
        ]);

        $relationships = DealerRelationship::query()
            ->with(['dealer.user', 'group'])
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = addcslashes($term, '%_\\');
                $q->where(fn ($q) => $q->where('dealer_code', 'like', "{$like}%")
                    ->orWhereHas('dealer', fn ($d) => $d->where('legal_name', 'like', "%{$like}%")->orWhere('email', 'like', "%{$like}%")));
            })
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['group'] ?? null, fn ($q, $g) => $q->where('dealer_group_id', $g))
            ->orderBy('dealer_code')
            ->paginate(25)
            ->withQueryString();

        return view('business.dealers.index', [
            'relationships' => $relationships,
            'filters' => $filters,
            'groups' => DealerGroup::orderBy('name')->pluck('name', 'id'),
            'hasAny' => DealerRelationship::exists(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('business.dealers.create', [
            'groups' => DealerGroup::orderBy('name')->pluck('name', 'id'),
            'defaultTerm' => $request->user()->business->settings->default_payment_term_days,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['can_edit_addresses' => $request->boolean('can_edit_addresses'), 'allow_open_account' => $request->boolean('allow_open_account'),
            'allow_on_delivery' => $request->boolean('allow_on_delivery')]);
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'city' => ['required', 'string', 'max:60'],
            'district' => ['required', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:500'],
            'tax_office' => ['nullable', 'string', 'max:120'],
            'tax_number' => ['nullable', 'regex:/^\d{10,11}$/'],
            'dealer_code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
            'dealer_group_id' => ['nullable', 'integer', Rule::exists('dealer_groups', 'id')->where('business_id', $request->user()->business_id)],
            'payment_term_days' => ['required', 'integer', 'min:0', 'max:365'],
            'can_edit_addresses' => ['boolean'],
            'password' => ['nullable', 'string', Password::defaults()],
        ], [], $this->attributes() + ['password' => 'şifre']);

        try {
            $result = $this->onboarding->createDirect($data, $data['contact_name'], $request->user()->id,
                array_filter(collect($data)->only(['dealer_code', 'dealer_group_id', 'payment_term_days', 'can_edit_addresses'])->all(), fn ($v) => $v !== null),
                $data['password'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors([str_contains($e->getMessage(), 'şifre') ? 'password' : 'email' => $e->getMessage()])->withInput($request->except('password'));
        }

        $emailed = app(Notifier::class)->dealerCreated($result['relationship'], $result['existing_account'])->status !== 'skipped';

        return redirect()->route('business.dealers.show', $result['relationship'])
            ->with('status', ($result['existing_account']
                ? 'Bu e-postayla kayıtlı bir bayi hesabı olduğu için mevcut hesabına sizinle çalışma yetkisi eklendi; bayi kendi şifresiyle giriş yapar.'
                : "Bayi hesabı açıldı. Bayi {$result['email']} ve belirlediğiniz şifreyle hemen giriş yapabilir.")
                .($emailed ? ' Bilgilendirme e-postası bayiye gönderiliyor.' : ''));
    }

    public function show(Request $request, DealerRelationship $dealer): View
    {
        $dealer->load(['dealer.user', 'group.priceList', 'currentAccount', 'addresses']);
        $tab = in_array($request->query('tab'), ['addresses', 'prices', 'preview'], true) ? $request->query('tab') : 'general';

        $data = ['relationship' => $dealer, 'tab' => $tab, 'groups' => DealerGroup::orderBy('name')->pluck('name', 'id')];

        if ($tab === 'prices') {
            $data['prices'] = $dealer->productPrices()->with('product:id,name,sku')->orderByDesc('id')->paginate(30)->withQueryString();
        }

        if ($tab === 'preview') {
            $term = trim($request->scalar('q'));
            $products = Product::notArchived()->where('is_active', true)->with(['unit', 'taxRate'])
                ->when($term !== '', function ($q) use ($term) {
                    $like = addcslashes($term, '%_\\');
                    $q->where(fn ($q) => $q->where('name', 'like', "%{$like}%")->orWhere('sku', 'like', "{$like}%"));
                })
                ->orderBy('name')->limit(30)->get();

            $data['previewProducts'] = $products;
            $data['previewPrices'] = app(PriceResolver::class)->resolveMany($dealer, $products);
        }

        return view('business.dealers.show', $data);
    }

    public function update(Request $request, DealerRelationship $dealer): RedirectResponse
    {
        $request->merge(['can_edit_addresses' => $request->boolean('can_edit_addresses')]);
        $data = $request->validate([
            'dealer_code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('dealer_relationships', 'dealer_code')->where('business_id', $request->user()->business_id)->ignore($dealer->id)],
            'dealer_group_id' => ['nullable', 'integer', Rule::exists('dealer_groups', 'id')->where('business_id', $request->user()->business_id)],
            'payment_term_days' => ['required', 'integer', 'min:0', 'max:365'],
            'can_edit_addresses' => ['boolean'],
            'allow_open_account' => ['boolean'],
            'allow_on_delivery' => ['boolean'],
        ], [], $this->attributes());

        $old = $dealer->only(array_keys($data));
        $dealer->update($data);
        $this->audit->log('dealer.terms_updated', $dealer, $old, $data);

        return back()->with('status', 'Bayi koşulları güncellendi.');
    }

    public function updateStatus(Request $request, DealerRelationship $dealer): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(DealerRelationship::STATUSES))],
            'reason' => ['nullable', 'required_unless:status,active', 'string', 'max:500'],
        ], [], ['reason' => 'gerekçe']);

        $old = $dealer->status;
        $dealer->update(['status' => $data['status']]);
        $this->audit->log('dealer.status_changed', $dealer, ['status' => $old], $data);

        return back()->with('status', 'Bayi durumu "'.DealerRelationship::STATUSES[$data['status']].'" olarak güncellendi.'
            .($data['status'] !== 'active' ? ' Bayi sizinle ilgili ekranlara artık erişemez ve sipariş veremez.' : ''));
    }

    /** Bayi henüz giriş yapmadıysa hatalı yazılmış giriş e-postasını düzeltir. */
    public function correctEmail(Request $request, DealerRelationship $dealer): RedirectResponse
    {
        $data = $request->validate(['email' => ['bail', 'required', 'string', 'email', 'max:255']], [], ['email' => 'e-posta adresi']);

        try {
            $email = $this->onboarding->correctEmailBeforeFirstLogin($dealer, $data['email']);
        } catch (DomainException $e) {
            return back()->withErrors(['account_email' => $e->getMessage()])->withInput();
        }

        return back()->with('status', "Giriş e-postası {$email} olarak düzeltildi.");
    }

    /** Toptancı bayisine yeni şifre belirler (bağlantı gönderilmez). */
    public function setPassword(Request $request, DealerRelationship $dealer, AccountEmailService $accounts): RedirectResponse
    {
        $data = $request->validate(['password' => ['bail', 'required', 'string', 'confirmed', Password::defaults()]], [], ['password' => 'yeni şifre']);
        $user = $dealer->dealer->user ?? abort(404);

        $accounts->setPassword(Area::Dealer, $user, $data['password'], 'dealer.password_set_by_business', ['dealer_relationship_id' => $dealer->id]);

        return back()->with('status', "Bayinin şifresi güncellendi. Bayi {$user->email} ve yeni şifreyle giriş yapabilir.");
    }

    public function storeAddress(Request $request, DealerRelationship $dealer): RedirectResponse
    {
        $data = $request->validate(self::addressRules(), [], self::addressAttributes());
        DB::transaction(function () use ($dealer, $data, $request) {
            $first = ! $dealer->addresses()->exists();
            $address = $dealer->addresses()->create($data + ['is_default' => $first || $request->boolean('is_default')]);
            if ($address->is_default) {
                self::makeOnlyDefault($address);
            }
        });

        return redirect()->route('business.dealers.show', [$dealer, 'tab' => 'addresses'])->with('status', 'Adres eklendi.');
    }

    public function updateAddress(Request $request, DealerRelationship $dealer, DealerAddress $address): RedirectResponse
    {
        abort_unless($address->dealer_relationship_id === $dealer->id && $address->archived_at === null, 404);
        $address->update($request->validate(self::addressRules(), [], self::addressAttributes()));
        if ($request->boolean('is_default')) {
            self::makeOnlyDefault($address);
        }

        return redirect()->route('business.dealers.show', [$dealer, 'tab' => 'addresses'])->with('status', 'Adres güncellendi.');
    }

    public function archiveAddress(DealerRelationship $dealer, DealerAddress $address): RedirectResponse
    {
        abort_unless($address->dealer_relationship_id === $dealer->id, 404);
        self::archive($address);

        return redirect()->route('business.dealers.show', [$dealer, 'tab' => 'addresses'])->with('status', 'Adres kaldırıldı. Geçmiş siparişlerdeki adres bilgisi değişmez.');
    }

    public function storePrice(Request $request, DealerRelationship $dealer): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('business_id', $request->user()->business_id)->whereNull('archived_at')],
            'price' => ['required', 'decimal:0,4', 'min:0', 'max:99999999999'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', new NotBefore('starts_on')],
        ], [], ['product_id' => 'ürün', 'price' => 'fiyat', 'starts_on' => 'başlangıç', 'ends_on' => 'bitiş']);

        $price = $dealer->productPrices()->create($data);
        $this->audit->log('pricing.dealer_price_set', $price, [], $data + ['dealer_relationship_id' => $dealer->id]);

        return redirect()->route('business.dealers.show', [$dealer, 'tab' => 'prices'])->with('status', 'Bayiye özel fiyat kaydedildi.');
    }

    public function destroyPrice(DealerRelationship $dealer, DealerProductPrice $price): RedirectResponse
    {
        abort_unless($price->dealer_relationship_id === $dealer->id, 404);
        $this->audit->log('pricing.dealer_price_removed', $price, $price->only(['product_id', 'price', 'starts_on', 'ends_on']));
        $price->delete();

        return redirect()->route('business.dealers.show', [$dealer, 'tab' => 'prices'])->with('status', 'Özel fiyat kaldırıldı. Geçmiş siparişler etkilenmez.');
    }

    public static function addressRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:60'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9 +()\-]{7,30}$/'],
            'city' => ['required', 'string', 'max:60'],
            'district' => ['required', 'string', 'max:60'],
            'address_line' => ['required', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function addressAttributes(): array
    {
        return ['title' => 'adres başlığı', 'recipient_name' => 'teslim alacak kişi', 'address_line' => 'açık adres', 'district' => 'ilçe', 'city' => 'il'];
    }

    public static function makeOnlyDefault(DealerAddress $address): void
    {
        DB::transaction(function () use ($address) {
            DealerAddress::where('dealer_relationship_id', $address->dealer_relationship_id)->whereKeyNot($address->id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });
    }

    /** Adres silinmez, arşivlenir: geçmiş siparişler zaten kendi kopyasını taşır. */
    public static function archive(DealerAddress $address): void
    {
        DB::transaction(function () use ($address) {
            $wasDefault = $address->is_default;
            $address->forceFill(['archived_at' => now(), 'is_default' => false])->save();
            if ($wasDefault) {
                DealerAddress::where('dealer_relationship_id', $address->dealer_relationship_id)->whereNull('archived_at')->orderBy('id')->first()?->update(['is_default' => true]);
            }
        });
    }

    private function attributes(): array
    {
        return [
            'legal_name' => 'işletme unvanı', 'contact_name' => 'yetkili adı', 'dealer_code' => 'bayi kodu',
            'dealer_group_id' => 'bayi grubu', 'payment_term_days' => 'vade (gün)', 'tax_number' => 'vergi numarası',
        ];
    }
}
