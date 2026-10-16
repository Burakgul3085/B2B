<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\AccountEmailService;
use App\Domain\Auth\Area;
use App\Domain\Reporting\Reports\Options;
use App\Http\Controllers\Controller;
use App\Models\DealerRelationship;
use App\Models\DealerUser;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Platform: bayi giriş hesapları. Bayi hesabı bayiye aittir ve birden çok toptancıyla kullanılabilir; bu yüzden
 * giriş e-postası yalnızca bayinin kendisi veya platform yöneticisi tarafından değiştirilir. Parola hiçbir zaman
 * görülmez veya belirlenmez; yalnızca sıfırlama bağlantısı üretilir. Her işlem gerekçeli ve denetim kaydına yazılır.
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
            'reason' => ['bail', 'required', 'string', 'min:5', 'max:500'],
        ], [], ['name' => 'ad soyad', 'email' => 'giriş e-postası', 'reason' => 'gerekçe']);

        try {
            if ($emails->normalize($data['email']) !== Str::lower($dealerUser->email)) {
                $emails->changeByPlatform(Area::Dealer, $dealerUser, $data['email'], $data['reason']);
            }
        } catch (DomainException $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput();
        }
        if ($data['name'] !== $dealerUser->fresh()->name) {
            $old = $dealerUser->name;
            $dealerUser->forceFill(['name' => $data['name']])->save();
            $this->audit->log('platform.user_name_changed', $dealerUser, ['name' => $old], ['name' => $data['name'], 'reason' => $data['reason']]);
        }

        $note = $dealerUser->fresh()->status === 'pending_activation'
            ? ' Hesap henüz etkinleşmediği için eski aktivasyon bağlantıları geçersiz oldu; toptancı bayi ekranından yeni bağlantı gönderebilir.' : '';

        return back()->with('status', 'Bayi hesabı güncellendi; eski ve yeni adrese bilgi verildi.'.$note);
    }

    public function sendPasswordReset(DealerUser $dealerUser, AccountEmailService $emails): RedirectResponse
    {
        if ($dealerUser->status !== 'active') {
            return back()->withErrors(['reset' => 'Parola sıfırlama yalnızca etkin hesaplar için gönderilebilir.']);
        }
        $result = $emails->sendPasswordReset(Area::Dealer, $dealerUser, 'Platform yöneticisi');
        $this->audit->log('platform.password_reset_sent', $dealerUser);

        return back()->with('status', "Parola sıfırlama bağlantısı {$dealerUser->email} adresine gönderildi.")->with('reset_url', $result['url']);
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
