<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Dealers\DealerOnboarding;
use App\Domain\Notifications\Notifier;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Business\DealerApplicationController;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\DealerApplication;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform: bütün toptancılara gelen bayilik başvuruları. Platform başvuruyu inceleyip toptancı adına onaylayabilir
 * (bayi hesabı hemen açılır; giriş e-postası ve şifre burada belirlenir) veya reddedebilir.
 */
class ApplicationController extends Controller
{
    public function __construct(private DealerOnboarding $onboarding, private TenantContext $tenant) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', ...array_keys(DealerApplication::STATUSES)])],
            'business' => ['nullable', 'integer'],
        ]);
        $status = $filters['status'] ?? 'open';

        $applications = DealerApplication::withoutBusinessScope()->with('business:id,display_name')
            ->when($status === 'open', fn ($q) => $q->whereIn('status', DealerApplication::OPEN), fn ($q) => $q->where('status', $status))
            ->when($filters['business'] ?? null, fn ($q, $b) => $q->where('business_id', $b))
            ->latest('id')->paginate(30)->withQueryString();

        return view('platform.applications.index', [
            'applications' => $applications,
            'filters' => ['status' => $status] + $filters,
            'businesses' => Business::orderBy('display_name')->pluck('display_name', 'id'),
        ]);
    }

    public function show(int $application): View
    {
        $a = $this->find($application);

        return view('platform.applications.show', [
            'a' => $a->load(['business', 'attachments' => fn ($q) => $q->withoutGlobalScopes()]),
            'defaultTerm' => BusinessSetting::where('business_id', $a->business_id)->value('default_payment_term_days') ?? 30,
        ]);
    }

    public function approve(Request $request, int $application): RedirectResponse
    {
        $a = $this->find($application);
        $login = DealerApplicationController::validateLogin($request);
        $terms = $request->validate(['payment_term_days' => ['required', 'integer', 'min:0', 'max:365']], [], ['payment_term_days' => 'vade (gün)'])
            + ['can_edit_addresses' => true];

        try {
            $result = $this->tenant->runAs($a->business_id, function () use ($a, $terms, $login) {
                $result = $this->onboarding->approve($a, null, $terms, $login);
                app(Notifier::class)->applicationApproved($a->fresh(), $result['email'], $result['existing_account']);

                return $result;
            });
        } catch (DomainException $e) {
            return back()->withErrors(['approve' => $e->getMessage()])->withInput($request->except('password'));
        }

        return redirect()->route('platform.applications.show', $a->id)->with('status', DealerApplicationController::approvedMessage($result));
    }

    public function reject(Request $request, int $application): RedirectResponse
    {
        $a = $this->find($application);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']], [], ['reason' => 'gerekçe'])['reason'];

        try {
            $this->tenant->runAs($a->business_id, function () use ($a, $reason) {
                $this->onboarding->reject($a, $reason, null);
                app(Notifier::class)->applicationRejected($a->fresh());
            });
        } catch (DomainException $e) {
            return back()->withErrors(['approve' => $e->getMessage()]);
        }

        return back()->with('status', 'Başvuru reddedildi; başvuru sahibine bilgi verildi.');
    }

    public function attachment(int $application, int $attachment): StreamedResponse
    {
        $a = $this->find($application);
        $attachment = Attachment::withoutBusinessScope()->where('business_id', $a->business_id)->findOrFail($attachment);
        abort_unless($attachment->attachable_type === 'dealer_application' && $attachment->attachable_id === $a->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->stored_path), 404);

        return Storage::disk('local')->download($attachment->stored_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime, 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function find(int $id): DealerApplication
    {
        return DealerApplication::withoutBusinessScope()->findOrFail($id);
    }
}
