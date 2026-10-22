<?php

namespace App\Http\Controllers\PublicSite;

use App\Domain\Dealers\DealerOnboarding;
use App\Domain\Notifications\Notifier;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Business;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bayilik başvurusunda ek bilgi yanıtı: başvurana e-postayla gönderilen süreli, tek kullanımlık bağlantı.
 * Giriş gerekmez; bağlantıyı bilen yalnızca o başvuruya yanıt verebilir. Belirtecin yalnızca özeti saklanır.
 */
class ApplicationReplyController extends Controller
{
    public function __construct(private DealerOnboarding $onboarding, private TenantContext $tenant) {}

    public function show(string $token): View
    {
        $application = DealerOnboarding::findByReplyToken($token);
        if (! $application) {
            return view('public.application-reply-expired');
        }

        return view('public.application-reply', ['application' => $application, 'business' => Business::findOrFail($application->business_id), 'token' => $token]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $application = DealerOnboarding::findByReplyToken($token) ?? abort(404);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:5', 'max:3000'],
            'documents' => ['nullable', 'array', 'max:3'],
            'documents.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], [], ['message' => 'açıklama', 'documents' => 'belgeler', 'documents.*' => 'belge']);

        try {
            $this->tenant->runAs($application->business_id, function () use ($application, $data, $request) {
                $this->onboarding->replyInfo($application, $data['message'], $request->file('documents', []));
                app(Notifier::class)->applicationInfoReplied($application);
            });
        } catch (DomainException $e) {
            return back()->withErrors(['message' => $e->getMessage()])->withInput();
        }

        return redirect()->route('public.application.thanks', Business::findOrFail($application->business_id)->slug)->with('replied', true);
    }
}
