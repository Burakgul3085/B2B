<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Newsletter\Subscriptions;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreNewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** E-bülten: alt bilgi formu, onay bağlantısı ve abonelikten çıkma. */
class NewsletterController extends Controller
{
    public function __construct(private Subscriptions $subscriptions) {}

    public function subscribe(Request $request): RedirectResponse
    {
        abort_unless($this->subscriptions->formAvailable(), 404);
        try {
            $result = $this->subscriptions->subscribe($request->only(['email', 'consent']), $request->ip());
        } catch (ValidationException $e) {
            // Alt bilgi her sayfada: hatalar sayfadaki diğer formlarla karışmasın diye ayrı torbada.
            throw $e->errorBag('newsletter')->redirectTo(url()->previous().'#e-bulten');
        }

        return redirect()->to(url()->previous().'#e-bulten')->with('newsletter', $result === 'active'
            ? 'Bu adres zaten e-bültenimize abone.'
            : 'Neredeyse tamam! E-postanıza gönderdiğimiz bağlantıyla aboneliğinizi onaylayın.');
    }

    public function confirm(string $token): View
    {
        $subscriber = $this->subscriptions->confirm($token);

        return view('store.front.newsletter', ['state' => $subscriber ? 'confirmed' : 'invalid', 'subscriber' => $subscriber]);
    }

    public function showUnsubscribe(string $token): View
    {
        $subscriber = StoreNewsletterSubscriber::firstWhere('token', $token);

        return view('store.front.newsletter', ['state' => $subscriber ? ($subscriber->status === 'unsubscribed' ? 'unsubscribed' : 'confirm-leave') : 'invalid', 'subscriber' => $subscriber]);
    }

    public function unsubscribe(string $token): View
    {
        $subscriber = $this->subscriptions->unsubscribe($token);

        return view('store.front.newsletter', ['state' => $subscriber ? 'unsubscribed' : 'invalid', 'subscriber' => $subscriber]);
    }
}
