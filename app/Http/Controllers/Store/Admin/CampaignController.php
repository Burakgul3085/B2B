<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Store\Newsletter\Campaigns;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreNewsletterCampaign;
use App\Models\Store\StoreNewsletterSubscriber;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Pazarlama → Toplu E-posta: taslak, önizleme, deneme gönderimi, gönderim ve ilerleme. */
class CampaignController extends Controller
{
    private const FIELDS = ['subject', 'preheader', 'body', 'button_label', 'button_url'];

    public function __construct(private Campaigns $campaigns) {}

    public function index(): View
    {
        return view('store.admin.newsletter.campaigns', [
            'campaigns' => StoreNewsletterCampaign::latest('id')->paginate(30),
            'activeCount' => StoreNewsletterSubscriber::where('status', 'active')->count(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new StoreNewsletterCampaign);
    }

    public function edit(StoreNewsletterCampaign $storeNewsletterCampaign): View
    {
        return $this->form($storeNewsletterCampaign);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, StoreNewsletterCampaign $storeNewsletterCampaign): RedirectResponse
    {
        return $this->save($request, $storeNewsletterCampaign);
    }

    public function destroy(StoreNewsletterCampaign $storeNewsletterCampaign): RedirectResponse
    {
        try {
            $this->campaigns->delete($storeNewsletterCampaign);
        } catch (DomainException $e) {
            return back()->withErrors(['campaign' => $e->getMessage()]);
        }

        return redirect()->route('store_admin.campaigns.index')->with('status', 'E-posta silindi.');
    }

    public function preview(StoreNewsletterCampaign $storeNewsletterCampaign): Response
    {
        return response($this->campaigns->render($storeNewsletterCampaign, null));
    }

    public function test(Request $request, StoreNewsletterCampaign $storeNewsletterCampaign): RedirectResponse
    {
        $to = $request->user('store_admin')->email;

        return $this->campaigns->sendTest($storeNewsletterCampaign, $to)
            ? back()->with('status', "Deneme e-postası {$to} adresine gönderildi.")
            : back()->withErrors(['campaign' => 'Deneme e-postası gönderilemedi. Mağaza Ayarları → E-posta gönderimi bölümünü kontrol edin.']);
    }

    public function send(StoreNewsletterCampaign $storeNewsletterCampaign): RedirectResponse
    {
        try {
            $count = $this->campaigns->start($storeNewsletterCampaign);
        } catch (DomainException $e) {
            return back()->withErrors(['campaign' => $e->getMessage()]);
        }

        return back()->with('status', "Gönderim başladı: {$count} aboneye arka planda gönderiliyor.");
    }

    public function cancel(StoreNewsletterCampaign $storeNewsletterCampaign): RedirectResponse
    {
        $this->campaigns->cancel($storeNewsletterCampaign);

        return back()->with('status', 'Gönderim durduruldu; kalan abonelere gönderilmeyecek.');
    }

    public function progress(StoreNewsletterCampaign $storeNewsletterCampaign): JsonResponse
    {
        $c = $storeNewsletterCampaign->fresh();

        return response()->json(['status' => $c->status, 'label' => $c->statusLabel(), 'sent' => $c->sent_count, 'failed' => $c->failed_count,
            'total' => $c->recipients_count, 'percent' => $c->progress()]);
    }

    public function image(StoreNewsletterCampaign $storeNewsletterCampaign): StreamedResponse
    {
        abort_unless($storeNewsletterCampaign->image_path && Storage::disk('local')->exists($storeNewsletterCampaign->image_path), 404);

        return Storage::disk('local')->response($storeNewsletterCampaign->image_path, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=86400']);
    }

    private function form(StoreNewsletterCampaign $campaign): View
    {
        return view('store.admin.newsletter.campaign', [
            'campaign' => $campaign,
            'activeCount' => StoreNewsletterSubscriber::where('status', 'active')->count(),
            'mailReady' => app(StoreMailer::class)->isConfigured(),
        ]);
    }

    private function save(Request $request, ?StoreNewsletterCampaign $campaign): RedirectResponse
    {
        $request->validate(['image' => ['nullable', 'file', 'max:6144']], [], ['image' => 'görsel']);
        try {
            $campaign = $this->campaigns->save($campaign, $request->only(self::FIELDS), $request->file('image'), $request->boolean('remove_image'));
        } catch (DomainException $e) {
            return back()->withErrors(['campaign' => $e->getMessage()])->withInput();
        }

        return redirect()->route('store_admin.campaigns.edit', $campaign)->with('status', 'Taslak kaydedildi.');
    }
}
