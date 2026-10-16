<?php

/*
| Eşzamanlılık testleri için ayrı PHP süreci: verilen iş işlemini belirlenen anda başlatır.
|   php action_worker.php {action} {businessId} {startAtMicrotime} {arg1} {arg2} ...
| Çıktı: OK | INSUFFICIENT | DOMAIN:<mesaj> | ERROR:<sınıf>:<mesaj>
*/

use App\Domain\Inventory\InsufficientStock;
use App\Domain\Ledger\LedgerService;
use App\Domain\Ordering\OrderService;
use App\Domain\Ordering\ReturnService;
use App\Domain\Tenancy\TenantContext;
use App\Models\CollectionEntry;
use App\Models\CurrentAccount;
use App\Models\DealerRelationship;
use App\Models\Order;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $action, $businessId, $startAt] = $argv;
$args = array_slice($argv, 4);
app(TenantContext::class)->set((int) $businessId);

// Kayıtlar başlamadan önce yüklenir; iki süreç aynı anda yazmaya başlar.
$run = match ($action) {
    'approve' => (function () use ($args) {
        $order = Order::findOrFail((int) $args[0]);

        return fn () => app(OrderService::class)->approve($order, [], (int) $args[1], [], (int) $args[2]);
    })(),
    'cancel_business' => (function () use ($args) {
        $order = Order::findOrFail((int) $args[0]);

        return fn () => app(OrderService::class)->cancelByBusiness($order, 'Eşzamanlı iptal');
    })(),
    'cancel_dealer' => (function () use ($args) {
        $order = Order::findOrFail((int) $args[0]);

        return fn () => app(OrderService::class)->cancelByDealer($order, 'Bayi vazgeçti');
    })(),
    'reject' => (function () use ($args) {
        $order = Order::findOrFail((int) $args[0]);

        return fn () => app(OrderService::class)->reject($order, 'Eşzamanlı ret');
    })(),
    'submit' => (function () use ($args) {
        $rel = DealerRelationship::findOrFail((int) $args[0]);

        return fn () => app(OrderService::class)->submit($rel, (int) $args[1], null, $args[2]);
    })(),
    'cancel_collection' => (function () use ($args) {
        $collection = CollectionEntry::findOrFail((int) $args[0]);

        return fn () => app(LedgerService::class)->cancelCollection($collection, 'Eşzamanlı iptal');
    })(),
    'opening' => (function () use ($args) {
        $account = CurrentAccount::findOrFail((int) $args[0]);

        return fn () => app(LedgerService::class)->setOpening($account, $args[1], '2026-01-01', null);
    })(),
    'return' => (function () use ($args) {
        $order = Order::findOrFail((int) $args[0]);

        return fn () => app(ReturnService::class)->create($order, [(int) $args[1] => $args[2]], 'Eşzamanlı iade', (int) $args[3]);
    })(),
};

while (microtime(true) < (float) $startAt) {
    usleep(500);
}

try {
    $run();
    echo 'OK';
} catch (InsufficientStock) {
    echo 'INSUFFICIENT';
} catch (DomainException $e) {
    echo 'DOMAIN:'.$e->getMessage();
} catch (Throwable $e) {
    echo 'ERROR:'.get_class($e).':'.$e->getMessage();
}
