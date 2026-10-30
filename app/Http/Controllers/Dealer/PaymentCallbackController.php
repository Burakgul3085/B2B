<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Payments\OrderPaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * iyzico'nun bayi kart ödemesi geri dönüşü (başka siteden POST; CSRF ve oturum çerezi gelmez). Belirteçten kayıtlı
 * ödeme ve işletmesi bulunur, sonuç iyzico'dan sorgulanır; bayi sipariş ekranına yönlendirilir (orada oturumla girer).
 * Bu istekte oturum açılmaz (rota StartSession'sız): yeni oturum çerezi bayinin açık oturumunu düşürürdü.
 */
class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, OrderPaymentService $payments): RedirectResponse
    {
        $token = $request->input('token');
        $order = is_string($token) && $token !== '' && strlen($token) <= 120 ? $payments->completeToken($token) : null;
        if (! $order) {
            return redirect()->route('dealer.orders.index');
        }

        // Sonuç mesajı sipariş ekranında siparişin gerçek ödeme durumuna göre gösterilir (parametreye güvenilmez).
        return redirect()->route('dealer.orders.show', [$order, 'odeme' => 'sonuc']);
    }
}
