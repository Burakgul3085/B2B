<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\AccountEmailService;
use App\Domain\Auth\Area;
use App\Domain\Dealers\DealerOnboarding;
use App\Domain\Notifications\Notifier;
use App\Domain\Reporting\Reports\Options;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\DealerRelationship;
use App\Models\DealerUser;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Platform: bayi giriş hesapları. Bayi hesabı bayiye aittir ve birden çok toptancıyla kullanılabilir. Platform bayi
 * açabilir (toptancı seçerek), giriş e-postasını ve adını değiştirebilir, yeni şifre belirleyebilir, hesabı kapatıp açabilir.
 * Her işlem gerekçeli ve denetim kaydına yazılır.
 */
class DealerAccountController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending_activation', 'active', 'disabled'])],
        ]);
        $like = Options::like((string) ($filters['q'] ?? ''));

        $users = DealerUser::query()->with('dealer')
            ->when($filters['q'] ?? null, fn ($q) => $q->where(fn ($q) => $q->where('email', 'like', $like)->orWhere('name', 'like', $like)
                ->orWhereHas('dealer', fn ($d) => $d->where('legal_name', 'like', $like))))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('email')->paginate(30)->withQueryString();

        $relationships = DealerRelationship::withoutBusinessScope()->with('business:id,display_name')
            ->whereIn('dealer_id', $users->pluck('dealer_id'))->get()->groupBy('dealer_id');

        return view('platform.dealer-accounts.index', compact('users', 'relationships', 'filters'));
    }

    public function create(): View
    {
        return view('platform.dealer-accounts.create', [
            'businesses' => Business::where('status', 'active')->orderBy('display_name')->pluck('display_name', 'id'),
        ]);
    }

    /** Platformdan bayi açma: hangi toptancının bayisi olacağı seçilir; giriş e-postası ve şifreyi platform belirler. */
    public function store(Request $request, DealerOnboarding $onboarding, TenantContext $tenant): RedirectResponse
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', Rule::exists('businesses', 'id')->where('status', 'active')],
            'legal_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'city' => ['required', 'string', 'max:60'],
            'district' => ['required', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:500'],
            'tax_office' => ['nullable', 'string', 'max:120'],
            'tax_number' => ['nullable', 'regex:/^\d{10,11}$/'],
            'password' => ['nullable', 'string', Password::defaults()],
        ], [], ['business_id' => 'toptancı', 'legal_name' => 'işletme unvanı', 'contact_name' => 'yetkili adı soyadı', 'email' => 'giriş e-postası',
            'phone' => 'telefon', 'city' => 'il', 'district' => 'ilçe', 'address' => 'adres', 'tax_number' => 'vergi / TC kimlik no', 'password' => 'şifre']);

        try {
            $result = $tenant->runAs((int) $data['business_id'], function () use ($onboarding, $data) {
                $result = $onboarding->createDirect($data, $data['contact_name'], null, [], $data['password'] ?? null);
                app(Notifier::class)->dealerCreated($result['relationship'], $result['existing_account']);

                return $result;
            });
        } catch (DomainException $e) {
            return back()->withErrors([str_contains($e->getMessage(), 'şifre') ? 'password' : 'email' => $e->getMessage()])->withInput($request->except('password'));
        }

        $user = DealerUser::where('email', $result['email'])->firstOrFail();

        return redirect()->route('platform.dealer-accounts.show', $user)->with('status', $result['existing_account']
            ? 'Bu e-postayla kayıtlı bayi hesabına seçilen toptancıyla çalışma yetkisi eklendi; bayi kendi şifresiyle giriş yapar.'
            : "Bayi hesabı açıldı. Bayi {$result['email']} ve belirlediğiniz şifreyle hemen giriş yapabilir.");
    }

    public function show(DealerUser $dealerUser): View
    {
        return view('platform.dealer-accounts.show', [
            'account' => $dealerUser->load('dealer'),
            'relationships' => DealerRelationship::withoutBusinessScope()->with('business:id,display_name,legal_name')
                ->where('dealer_id', $dealerUser->dealer_id)->get(),
        ]);
    }

    public function update(Request $request, DealerUser $dealerUser, AccountEmailService $emails): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['bail', 'required', 'string', 'max:120'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'reason' => ['bail', 'required', 'string', 'min:5', 'max:500'],
        ], [], ['name' => 'ad soyad', 'email' => 'giriş e-postası', 'password' => 'yeni şifre', 'reason' => 'gerekçe']);

        try {
            if ($emails->normalize($data['email']) !== Str::lower($dealerUser->email)) {
                $emails->changeByPlatform(Area::Dealer, $dealerUser, $data['email'], $data['reason']);
            }
        } catch (DomainException $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput($request->except(['password', 'password_confirmation']));
        }
        if (filled($data['password'] ?? null)) {
            $emails->setPassword(Area::Dealer, $dealerUser, $data['password'], 'platform.password_set', ['reason' => $data['reason']]);
        }
        if ($data['name'] !== $dealerUser->fresh()->name) {
            $old = $dealerUser->name;
            $dealerUser->forceFill(['name' => $data['name']])->save();
            $this->audit->log('platform.user_name_changed', $dealerUser, ['name' => $old], ['name' => $data['name'], 'reason' => $data['reason']]);
        }

        return back()->with('status', 'Bayi hesabı güncellendi.'.(filled($data['password'] ?? null) ? ' Yeni şifre kaydedildi.' : ''));
    }

    public function updateStatus(Request $request, DealerUser $dealerUser): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'disabled'])],
            'reason' => ['bail', 'required', 'string', 'min:5', 'max:500'],
        ], [], ['reason' => 'gerekçe']);

        // Parolası hiç belirlenmemiş hesap yeniden açıldığında aktivasyon bekler.
        $status = $data['status'] === 'active' && $dealerUser->password === null ? 'pending_activation' : $data['status'];
        $old = $dealerUser->status;
        $dealerUser->forceFill(['status' => $status, 'remember_token' => Str::random(60)])->save();
        $this->audit->log('platform.dealer_user_status_changed', $dealerUser, ['status' => $old], ['status' => $status, 'reason' => $data['reason']]);

        return back()->with('status', $status === 'disabled' ? 'Bayi hesabı devre dışı bırakıldı; bayi hiçbir toptancıya giriş yapamaz.' : 'Bayi hesabı yeniden açıldı.');
    }
}
