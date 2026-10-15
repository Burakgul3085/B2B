<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Audit\AuditLogger;
use App\Domain\Platform\CreateBusiness;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessModule;
use App\Models\DealerRelationship;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Platform yöneticisinin işletme yönetimi. Ticari veriye (sipariş, fiyat, cari) erişmez;
 * yalnızca işletme kartını, lisansı, modülleri ve kullanım adetlerini görür.
 */
class BusinessController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(Business::STATUSES))],
        ]);

        $businesses = Business::query()
            ->with(['user', 'licenses'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('legal_name', 'like', "%{$term}%")
                ->orWhere('display_name', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%")))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('display_name')
            ->paginate(20)
            ->withQueryString();

        return view('platform.businesses.index', compact('businesses', 'filters'));
    }

    public function create(): View
    {
        return view('platform.businesses.create');
    }

    public function store(Request $request, CreateBusiness $action): RedirectResponse
    {
        $request->merge(['slug' => Str::slug((string) $request->input('slug'))]);
        $data = $request->validate($this->businessRules() + [
            'slug' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[a-z0-9-]+$/', 'unique:businesses,slug'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:business_users,email'],
            'plan' => ['required', 'string', 'max:40'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ], [], $this->attributes());

        $result = $action->handle(
            collect($data)->only(['slug', 'legal_name', 'display_name', 'tax_office', 'tax_number', 'phone', 'email', 'city', 'district', 'address'])->all(),
            ['name' => $data['owner_name'], 'email' => Str::lower($data['owner_email'])],
            ['plan' => $data['plan'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'] ?? null],
        );

        return redirect()->route('platform.businesses.show', $result['business'])
            ->with('status', 'İşletme oluşturuldu.')
            ->with('activation', $result);
    }

    public function show(Business $business): View
    {
        $business->load(['user', 'licenses', 'modules', 'settings']);

        // Yalnızca adet: platform ticari tutarları görmez.
        $usage = [
            'Aktif bayi' => DealerRelationship::withoutBusinessScope()->where('business_id', $business->id)->where('status', 'active')->count(),
        ];

        return view('platform.businesses.show', compact('business', 'usage'));
    }

    public function edit(Business $business): View
    {
        return view('platform.businesses.edit', compact('business'));
    }

    public function update(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate($this->businessRules(), [], $this->attributes());
        $old = $business->only(array_keys($data));

        $business->update($data);
        $this->audit->log('platform.business_updated', $business, $old, $business->only(array_keys($data)), businessId: $business->id);

        return redirect()->route('platform.businesses.show', $business)->with('status', 'İşletme bilgileri güncellendi.');
    }

    public function updateStatus(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Business::STATUSES))],
            'status_reason' => ['nullable', 'required_unless:status,active', 'string', 'max:500'],
        ], [], ['status_reason' => 'gerekçe']);

        $old = $business->only(['status', 'status_reason']);
        $business->update(['status' => $data['status'], 'status_reason' => $data['status'] === 'active' ? null : $data['status_reason']]);
        $this->audit->log('platform.business_status_changed', $business, $old, $business->only(['status', 'status_reason']), businessId: $business->id);

        return back()->with('status', 'İşletme durumu "'.Business::STATUSES[$data['status']].'" olarak güncellendi.');
    }

    public function storeLicense(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', 'max:40'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'max_dealers' => ['nullable', 'integer', 'min:1'],
            'max_products' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['plan' => 'plan', 'starts_on' => 'başlangıç', 'ends_on' => 'bitiş']);

        $license = $business->licenses()->create($data);
        $this->audit->log('platform.license_added', $license, [], $data, businessId: $business->id);

        return back()->with('status', 'Lisans dönemi eklendi.');
    }

    public function updateModules(Request $request, Business $business): RedirectResponse
    {
        $enabled = $request->validate([
            'modules' => ['array'],
            'modules.*' => [Rule::in(array_keys(BusinessModule::AVAILABLE))],
        ])['modules'] ?? [];

        foreach (array_keys(BusinessModule::AVAILABLE) as $key) {
            $business->modules()->updateOrCreate(['module_key' => $key], ['enabled' => in_array($key, $enabled, true)]);
        }
        $this->audit->log('platform.modules_updated', $business, [], ['enabled' => $enabled], businessId: $business->id);

        return back()->with('status', 'Modüller güncellendi.');
    }

    public function resendActivation(Business $business, CreateBusiness $action): RedirectResponse
    {
        $user = $business->user;
        abort_if($user === null || $user->status !== 'pending_activation', 422, 'Hesap zaten etkin.');

        $result = $action->sendActivation($user);
        $this->audit->log('platform.activation_resent', $user, businessId: $business->id);

        return back()->with('status', 'Yeni aktivasyon bağlantısı oluşturuldu.')->with('activation', $result);
    }

    private function businessRules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:120'],
            'tax_office' => ['nullable', 'string', 'max:120'],
            'tax_number' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:60'],
            'district' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function attributes(): array
    {
        return [
            'legal_name' => 'ticari unvan', 'display_name' => 'görünen ad', 'slug' => 'kısa ad',
            'tax_office' => 'vergi dairesi', 'tax_number' => 'vergi numarası', 'owner_name' => 'yetkili adı',
            'owner_email' => 'yetkili e-postası', 'starts_on' => 'lisans başlangıcı', 'ends_on' => 'lisans bitişi',
        ];
    }
}
