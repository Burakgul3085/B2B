<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\AccountEmailService;
use App\Domain\Auth\Area;
use App\Domain\Mail\EmailSettingService;
use App\Domain\Mail\MailFailure;
use App\Domain\Mail\MailTransport;
use App\Domain\Platform\CreateBusiness;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessModule;
use App\Models\DealerRelationship;
use App\Models\EmailSetting;
use App\Rules\NotBefore;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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

    public function create(): View|RedirectResponse
    {
        if (! CreateBusiness::allowed()) {
            return redirect()->route('platform.businesses.index')->withErrors(['business' => 'Sistem tek toptancı içindir; yeni işletme açılamaz.']);
        }

        return view('platform.businesses.create');
    }

    public function store(Request $request, CreateBusiness $action): RedirectResponse
    {
        if (! CreateBusiness::allowed()) {
            return redirect()->route('platform.businesses.index')->withErrors(['business' => 'Sistem tek toptancı içindir; yeni işletme açılamaz.']);
        }

        $request->merge(['slug' => Str::slug($request->scalar('slug'))]);
        $data = $request->validate($this->businessRules() + [
            'slug' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[a-z0-9-]+$/', 'unique:businesses,slug'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:business_users,email'],
            'owner_password' => ['required', 'string', Password::defaults()],
            'plan' => ['required', 'string', 'max:40'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', new NotBefore('starts_on')],
        ], [], $this->attributes());

        try {
            $result = $action->handle(
                collect($data)->only(['slug', 'legal_name', 'display_name', 'tax_office', 'tax_number', 'phone', 'email', 'city', 'district', 'address'])->all(),
                ['name' => $data['owner_name'], 'email' => Str::lower($data['owner_email']), 'password' => $data['owner_password']],
                ['plan' => $data['plan'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'] ?? null],
            );
        } catch (DomainException $e) {
            return redirect()->route('platform.businesses.index')->withErrors(['business' => $e->getMessage()]);
        }

        return redirect()->route('platform.businesses.show', $result['business'])
            ->with('status', 'İşletme oluşturuldu. Yetkili, belirlediğiniz e-posta ve şifreyle toptancı paneline hemen giriş yapabilir.');
    }

    public function show(Business $business): View
    {
        $business->load(['user', 'licenses', 'modules', 'settings']);

        // Yalnızca adet: platform ticari tutarları görmez.
        $usage = [
            'Aktif bayi' => DealerRelationship::withoutBusinessScope()->where('business_id', $business->id)->where('status', 'active')->count(),
        ];

        $emailSetting = EmailSetting::withoutBusinessScope()->where('business_id', $business->id)->first();

        return view('platform.businesses.show', compact('business', 'usage', 'emailSetting'));
    }

    /** İşletmenin ana Gmail ayarı: toptancı panelindeki E-posta Ayarları ile aynı kurallar. */
    public function updateEmail(Request $request, Business $business, EmailSettingService $settings, TenantContext $tenant): RedirectResponse
    {
        $changed = $tenant->runAs($business->id, fn () => $settings->save(
            $request->only(['from_email', 'from_name', 'app_password'])
        ));

        return back()->with('status', $changed ? 'E-posta ayarı kaydedildi. Şimdi “Bağlantıyı test et” ile doğrulayın.' : 'E-posta ayarı kaydedildi.');
    }

    public function testEmail(Business $business, MailTransport $transport, EmailSettingService $settings, TenantContext $tenant): RedirectResponse
    {
        try {
            $to = $tenant->runAs($business->id, function () use ($settings, $transport) {
                abort_unless(EmailSetting::exists(), 404);

                return $settings->test($transport);
            });
        } catch (MailFailure $e) {
            return back()->withErrors(['mail' => $e->getMessage()]);
        }

        return back()->with('status', "Bağlantı başarılı. {$to} adresine test e-postası gönderildi.");
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
            'ends_on' => ['nullable', 'date', new NotBefore('starts_on')],
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

    /** İşletme yetkilisinin adı ve giriş e-postası: doğrudan değişir, gerekçe zorunlu, eski ve yeni adrese bildirim. */
    public function updateUser(Request $request, Business $business, AccountEmailService $emails): RedirectResponse
    {
        $user = $business->user ?? abort(404);
        $data = $request->validate([
            'name' => ['bail', 'required', 'string', 'max:120'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'reason' => ['bail', 'required', 'string', 'min:5', 'max:500'],
        ], [], ['name' => 'ad soyad', 'email' => 'giriş e-postası', 'password' => 'yeni şifre', 'reason' => 'gerekçe']);

        try {
            if ($emails->normalize($data['email']) !== Str::lower($user->email)) {
                $emails->changeByPlatform(Area::Business, $user, $data['email'], $data['reason']);
            }
        } catch (DomainException $e) {
            return back()->withErrors(['user_email' => $e->getMessage()])->withInput($request->except(['password', 'password_confirmation']));
        }
        if (filled($data['password'] ?? null)) {
            $emails->setPassword(Area::Business, $user, $data['password'], 'platform.password_set', ['reason' => $data['reason']]);
        }
        if ($data['name'] !== $user->fresh()->name) {
            $old = $user->name;
            $user->forceFill(['name' => $data['name']])->save();
            $this->audit->log('platform.user_name_changed', $user, ['name' => $old], ['name' => $data['name'], 'reason' => $data['reason']], businessId: $business->id);
        }

        return back()->with('status', 'Yetkili bilgileri güncellendi.'.(filled($data['password'] ?? null) ? ' Yeni şifre kaydedildi.' : ''));
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
