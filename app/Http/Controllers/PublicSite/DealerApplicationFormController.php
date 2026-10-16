<?php

namespace App\Http\Controllers\PublicSite;

use App\Domain\Dealers\DealerOnboarding;
use App\Domain\Notifications\Notifier;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\DealerApplication;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Toptancıya özel, herkese açık bayilik başvuru formu: /basvuru/{slug}.
 * Ürün, fiyat veya stok bilgisi göstermez; yalnızca başvuru toplar.
 */
class DealerApplicationFormController extends Controller
{
    public function __construct(private DealerOnboarding $onboarding, private TenantContext $tenant) {}

    /**
     * Bayi giriş sayfasındaki "Bayimiz olmak ister misiniz?" bağlantısı: tek toptancı varsa doğrudan onun formuna,
     * birden çok toptancı varsa seçim sayfasına gider.
     */
    public function index(): View|RedirectResponse
    {
        $businesses = Business::where('status', 'active')->orderBy('display_name')->get(['id', 'slug', 'display_name', 'legal_name', 'city', 'logo_path']);
        if ($businesses->count() === 1) {
            return redirect()->route('public.application', $businesses->first()->slug);
        }

        return view('public.apply-choose', ['businesses' => $businesses]);
    }

    public function show(string $slug): View
    {
        $business = $this->business($slug);

        return view('public.application', ['business' => $business, 'types' => DealerApplication::BUSINESS_TYPES]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $business = $this->business($slug);

        // Bot tuzağı: görünmeyen alan doluysa kayıt oluşturmadan teşekkür sayfasına gönder.
        if (filled($request->input('website'))) {
            return redirect()->route('public.application.thanks', $business->slug);
        }

        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9 +()\-]{7,30}$/'],
            'email' => ['required', 'email', 'max:255'],
            'city' => ['required', 'string', 'max:60'],
            'district' => ['required', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:500'],
            'business_type' => ['nullable', Rule::in(array_keys(DealerApplication::BUSINESS_TYPES))],
            'activity_area' => ['nullable', 'string', 'max:160'],
            'tax_office' => ['nullable', 'string', 'max:120'],
            'tax_number' => ['nullable', 'regex:/^\d{10,11}$/'],
            'note' => ['nullable', 'string', 'max:2000'],
            'documents' => ['nullable', 'array', 'max:3'],
            'documents.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Devam etmek için aydınlatma metnini okuduğunuzu onaylayın.'], [
            'legal_name' => 'işletme unvanı', 'contact_name' => 'yetkili adı soyadı', 'business_type' => 'işletme türü',
            'activity_area' => 'faaliyet alanı', 'tax_office' => 'vergi dairesi', 'tax_number' => 'vergi / TC kimlik no',
            'note' => 'açıklama', 'documents' => 'belgeler', 'documents.*' => 'belge',
        ]);

        try {
            $this->tenant->runAs($business->id, function () use ($data, $request) {
                $application = $this->onboarding->submit($data, $request->file('documents', []), $request->ip(), $request->userAgent());
                app(Notifier::class)->applicationReceived($application);
            });
        } catch (DomainException $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput();
        }

        return redirect()->route('public.application.thanks', $business->slug);
    }

    public function thanks(string $slug): View
    {
        return view('public.application-thanks', ['business' => $this->business($slug)]);
    }

    /** İşletme logosu herkese açıktır (başvuru sayfası ve e-postalarda kullanılır). */
    public function logo(string $slug): Response
    {
        $business = Business::where('slug', $slug)->where('status', 'active')->firstOrFail();
        abort_unless($business->logo_path && Storage::disk('local')->exists($business->logo_path), 404);

        return Storage::disk('local')->response($business->logo_path, null, [
            'Content-Type' => 'image/webp', 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function business(string $slug): Business
    {
        return Business::where('slug', $slug)->where('status', 'active')->firstOrFail();
    }
}
