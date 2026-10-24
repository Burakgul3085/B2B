<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Store\Newsletter\Subscriptions;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreNewsletterSubscriber;
use App\Models\Store\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Pazarlama → E-bülten: aboneler, dışa aktarma (liste ve İYS biçimi) ve alt bilgideki e-bülten alanının ayarları. */
class NewsletterController extends Controller
{
    public function __construct(private Subscriptions $subscriptions) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(StoreNewsletterSubscriber::STATUSES))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return view('store.admin.newsletter.subscribers', [
            'subscribers' => StoreNewsletterSubscriber::query()
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($filters['q'] ?? null, fn ($q, $t) => $q->where('email', 'like', '%'.addcslashes($t, '%_\\').'%'))
                ->latest('id')->paginate(50)->withQueryString(),
            'filters' => $filters,
            'counts' => StoreNewsletterSubscriber::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
            'settings' => StoreSetting::values(),
            'mailReady' => app(StoreMailer::class)->isConfigured(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'format' => ['required', Rule::in(['list', 'iys'])],
            'status' => ['nullable', Rule::in(array_keys(StoreNewsletterSubscriber::STATUSES))],
        ]);

        return $this->subscriptions->export($data['format'], $data['status'] ?? null);
    }

    public function settings(Request $request): RedirectResponse
    {
        $this->subscriptions->saveSettings($request->only(['newsletter_enabled', 'newsletter_title', 'newsletter_text', 'newsletter_consent']));

        return back()->with('status', 'E-bülten ayarları kaydedildi.');
    }

    public function destroy(StoreNewsletterSubscriber $storeNewsletterSubscriber): RedirectResponse
    {
        $this->subscriptions->delete($storeNewsletterSubscriber);

        return back()->with('status', 'Abone silindi.');
    }
}
