<?php

/*
| Eşzamanlı sipariş onayı testi için ayrı PHP süreci.
|   php approve_worker.php {businessId} {orderId} {warehouseId} {userId} {startAtMicrotime}
| Çıktı: OK | INSUFFICIENT | DOMAIN:<mesaj> | ERROR:<mesaj>
*/

use App\Domain\Inventory\InsufficientStock;
use App\Domain\Ordering\OrderService;
use App\Domain\Tenancy\TenantContext;
use App\Models\Order;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $businessId, $orderId, $warehouseId, $userId, $startAt] = $argv;

app(TenantContext::class)->set((int) $businessId);
$order = Order::findOrFail((int) $orderId);

while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

try {
    app(OrderService::class)->approve($order, [], (int) $warehouseId, [], (int) $userId);
    echo 'OK';
} catch (InsufficientStock) {
    echo 'INSUFFICIENT';
} catch (DomainException $e) {
    echo 'DOMAIN:'.$e->getMessage();
} catch (Throwable $e) {
    echo 'ERROR:'.get_class($e).':'.$e->getMessage();
}
