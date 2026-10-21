<?php

/*
| Mağaza stok eşzamanlılık testi için ayrı PHP süreci. Kullanım:
|   php store_stock_worker.php {hold|sell} {variantId} {qty} {key} {startAtMicrotime} {holdMs}
| Belirtilen anda başlar; ayırma veya satış dener, kilidi holdMs boyunca tutup commit eder.
| Çıktı: OK | INSUFFICIENT | ERROR:<mesaj>
*/

use App\Domain\Store\Inventory\InsufficientStoreStock;
use App\Domain\Store\Inventory\StoreStockService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $action, $variantId, $qty, $key, $startAt, $holdMs] = $argv;

while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

try {
    DB::transaction(function () use ($action, $variantId, $qty, $key, $holdMs) {
        $stock = app(StoreStockService::class);
        $action === 'hold'
            ? $stock->hold($key, [(int) $variantId => (int) $qty])
            : $stock->sell([(int) $variantId => (int) $qty], $key, 'test', 1);
        usleep((int) $holdMs * 1000); // kilidi tut: diğer süreç beklemek zorunda kalsın
    });
    echo 'OK';
} catch (InsufficientStoreStock) {
    echo 'INSUFFICIENT';
} catch (Throwable $e) {
    echo 'ERROR:'.get_class($e).':'.$e->getMessage();
}
