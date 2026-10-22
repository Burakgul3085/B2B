<?php

namespace App\Http\Controllers\Business;

use App\Domain\Dealers\DealerOnboarding;
use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\DealerApplication;
use App\Models\DealerGroup;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DealerApplicationController extends Controller
{
    public function __construct(private DealerOnboarding $onboarding, private Notifier $notifier) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([...array_keys(DealerApplication::STATUSES), 'open'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filters['status'] ?? 'open';

        $applications = DealerApplication::query()
            ->when($status === 'open', fn ($q) => $q->whereIn('status', DealerApplication::OPEN))
            ->when($status !== 'open', fn ($q) => $q->where('status', $status))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = addcslashes($term, '%_\\');
                $q->where(fn ($q) => $q->where('legal_name', 'like', "%{$like}%")->orWhere('email', 'like', "%{$like}%")->orWhere('contact_name', 'like', "%{$like}%"));
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $counts = DealerApplication::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return view('business.dealers.applications', compact('applications', 'status', 'filters', 'counts'));
    }

    public function show(DealerApplication $application): View
    {
        $application->load(['attachments', 'relationship']);

        return view('business.dealers.application-show', [
            'application' => $application,
            'groups' => DealerGroup::orderBy('name')->pluck('name', 'id'),
            'defaultTerm' => request()->user()->business->settings->default_payment_term_days,
        ]);
    }

    public function review(Request $request, DealerApplication $application): RedirectResponse
    {
        return $this->run(fn () => $this->onboarding->markInReview($application, $request->user()->id), 'Başvuru "İnceleniyor" olarak işaretlendi.');
    }

    public function requestInfo(Request $request, DealerApplication $application): RedirectResponse
    {
        $note = $request->validate(['note' => ['required', 'string', 'max:2000']], [], ['note' => 'istenen bilgi'])['note'];

        return $this->run(function () use ($application, $note, $request) {
            $token = $this->onboarding->requestInfo($application, $note, $request->user()->id);
            $this->notifier->applicationInfoRequested($application->fresh(), route('public.application.reply', $token));
        }, 'Ek bilgi talebi kaydedildi ve e-posta ayarınız varsa başvuru sahibine gönderildi.');
    }

    public function reject(Request $request, DealerApplication $application): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']], [], ['reason' => 'ret gerekçesi'])['reason'];

        return $this->run(function () use ($application, $reason, $request) {
            $this->onboarding->reject($application, $reason, $request->user()->id);
            $this->notifier->applicationRejected($application->fresh());
        }, 'Başvuru reddedildi.');
    }

    public function cancel(Request $request, DealerApplication $application): RedirectResponse
    {
        return $this->run(fn () => $this->onboarding->cancel($application, $request->user()->id), 'Başvuru iptal edildi.');
    }

    public function approve(Request $request, DealerApplication $application): RedirectResponse
    {
        $terms = $this->validateTerms($request);
        $login = self::validateLogin($request);

        try {
            $result = $this->onboarding->approve($application, $request->user()->id, $terms, $login);
        } catch (DomainException $e) {
            return back()->withErrors(['approve' => $e->getMessage()])->withInput($request->except('password'));
        }

        $emailed = $this->notifier->applicationApproved($application->fresh(), $result['email'], $result['existing_account'])->status !== 'skipped';

        return redirect()->route('business.dealers.show', $result['relationship'])
            ->with('status', self::approvedMessage($result).($emailed ? ' Bilgilendirme e-postası bayiye gönderiliyor.' : ''));
    }

    /** Onayda giriş bilgileri: e-posta (başvurudaki veya düzeltilmiş) ve onaylayanın belirlediği şifre. */
    public static function validateLogin(Request $request): array
    {
        return $request->validate([
            'login_email' => ['bail', 'required', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', Password::defaults()],
        ], [], ['login_email' => 'giriş e-postası', 'password' => 'şifre']) + ['email' => $request->input('login_email')];
    }

    public static function approvedMessage(array $result): string
    {
        return $result['existing_account']
            ? 'Başvuru onaylandı. Bu e-postayla kayıtlı bir bayi hesabı olduğu için mevcut hesabına erişim eklendi; bayi kendi şifresiyle giriş yapar.'
            : "Başvuru onaylandı ve bayi hesabı açıldı. Bayi {$result['email']} ve belirlediğiniz şifreyle hemen giriş yapabilir.";
    }

    public function attachment(DealerApplication $application, Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->attachable_type === 'dealer_application' && $attachment->attachable_id === $application->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->stored_path), 404);

        return Storage::disk('local')->download($attachment->stored_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function validateTerms(Request $request): array
    {
        $request->merge(['can_edit_addresses' => $request->boolean('can_edit_addresses')]);

        return $request->validate([
            'dealer_group_id' => ['nullable', 'integer', Rule::exists('dealer_groups', 'id')->where('business_id', $request->user()->business_id)],
            'payment_term_days' => ['required', 'integer', 'min:0', 'max:365'],
            'can_edit_addresses' => ['boolean'],
        ], [], ['dealer_group_id' => 'bayi grubu', 'payment_term_days' => 'vade (gün)']);
    }

    private function run(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->withErrors(['approve' => $e->getMessage()]);
        }

        return back()->with('status', $message);
    }
}
