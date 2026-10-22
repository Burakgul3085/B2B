<?php

/*
| Kupon kullanım sınırı eşzamanlılık testi için ayrı PHP süreci. Kullanım:
|   php store_coupon_worker.php {cartId} {phone} {email} {startAtMicrotime} {holdMs}
| Belirtilen anda başlar; sepeti kuponla kapıda ödemeli siparişe çevirir ve işlemi holdMs boyunca açık tutar
| (indirim satırının kilidi tutulur, diğer süreç beklemek zorunda kalır).
| Çıktı: OK | REFUSED | ERROR:<mesaj>
*/

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Orders\CheckoutService;
use App\Models\Store\District;
use App\Models\Store\StoreCart;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $cartId, $phone, $email, $startAt, $holdMs] = $argv;

$cart = StoreCart::findOrFail((int) $cartId);
$checkout = app(CheckoutService::class);
// Taslak (indirim dahil) önceden hazırlanır: iki süreç de kuponu geçerli görür.
$draft = $checkout->validate([
    'email' => $email,
    'address' => ['first_name' => 'Test', 'last_name' => 'Müşteri', 'phone' => $phone, 'province_id' => 26,
        'district_id' => District::where('province_id', 26)->where('name', 'Tepebaşı')->value('id'), 'address_line' => 'Atatürk Bulvarı No 5 D 2'],
    'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => 'local', 'payment_method' => 'cod', 'consent' => '1',
], null, app(CartService::class)->summary($cart), $cart->coupon_code);

while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

try {
    DB::transaction(function () use ($checkout, $draft, $cart, $holdMs) {
        $checkout->place($draft, null, $cart, null, null);
        usleep((int) $holdMs * 1000);
    });
    echo 'OK';
} catch (DomainException) {
    echo 'REFUSED';
} catch (Throwable $e) {
    echo 'ERROR:'.get_class($e).':'.$e->getMessage();
}
