<?php

/*
| Eşzamanlılık testi için ayrı PHP süreci. Kullanım:
|   php stock_worker.php {businessId} {warehouseId} {productId} {qty} {sourceKey} {startAtMicrotime} {holdMs}
| Belirtilen anda başlar, stoktan düşmeyi dener, kilidi holdMs boyunca tutup commit eder.
| Çıktı: OK | INSUFFICIENT | ERROR:<mesaj>
*/

use App\Domain\Inventory\InsufficientStock;
use App\Domain\Inventory\StockService;
use App\Domain\Tenancy\TenantContext;
use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $businessId, $warehouseId, $productId, $qty, $sourceKey, $startAt, $holdMs] = $argv;

while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

app(TenantContext::class)->set((int) $businessId);

try {
    DB::transaction(function () use ($warehouseId, $productId, $qty, $sourceKey, $holdMs) {
        $product = Product::findOrFail((int) $productId);
        app(StockService::class)->applyMany((int) $warehouseId, 'sale', [
            ['product' => $product, 'qty' => '-'.$qty, 'source_key' => $sourceKey],
        ]);
        usleep((int) $holdMs * 1000); // kilidi tut: diğer süreç beklemek zorunda kalsın
    });
    echo 'OK';
} catch (InsufficientStock) {
    echo 'INSUFFICIENT';
} catch (Throwable $e) {
    echo 'ERROR:'.get_class($e).':'.$e->getMessage();
}
