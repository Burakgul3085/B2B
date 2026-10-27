<?php

use App\Domain\Platform\BackupService;
use App\Domain\Store\Inventory\StockAlerts;
use App\Domain\Store\Orders\OrderService;
use App\Domain\Store\Payments\PaymentService;
use App\Domain\Support\OpsStatus;
use App\Jobs\QueueHeartbeat;
use Database\Seeders\Support\DemoImages;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schedule;

/*
| Zamanlanmış görevler. Sunucuda tek cron satırı yeterlidir:
|   * * * * * cd /var/www/b2b && php artisan schedule:run >> /dev/null 2>&1
*/

// Canlılık izleri (Mağaza → Yayın hazırlığı): zamanlayıcı her dakika, kuyruk işçisi beş dakikada bir iz bırakır.
Schedule::call(fn () => app(OpsStatus::class)->recordScheduler())->everyMinute()->name('ops:scheduler-heartbeat');
Schedule::job(new QueueHeartbeat)->everyFiveMinutes()->name('ops:queue-heartbeat');

// Haftalık vade hatırlatması (yalnızca hatırlatmayı açmış işletmeler).
Schedule::command('ledger:send-reminders')->weeklyOn(1, '09:00')->timezone('Europe/Istanbul')->withoutOverlapping();

// Gece yedeği: veritabanı + yüklenen dosyalar (Platform → Yedekler). Son BACKUP_KEEP yedek saklanır.
Artisan::command('backup:run', function () {
    $name = app(BackupService::class)->create();
    $this->info("Yedek alındı: {$name}");
})->purpose('Veritabanı ve yüklenen dosyaların yedeğini alır');
Schedule::command('backup:run')->dailyAt('03:30')->timezone('Europe/Istanbul')->withoutOverlapping();

/*
| Demo vitrin videoları (yalnızca yerel geliştirme): ürün çizimlerinin saydam hâllerini dışa aktarır,
| Python betiğiyle iki kısa WebM (VP9) video ve kapak görselleri üretir. Çıktı: storage/app/demo-media
| StorefrontDemoSeeder bu dosyalar varsa video slaytları da ekler. Python, numpy, Pillow ve opencv-python gerekir.
*/
Artisan::command('demo:videolar', function () {
    abort_unless(app()->environment('local'), 403, 'Yalnızca yerel geliştirme ortamında çalışır.');

    $dir = storage_path('app/demo-media');
    File::ensureDirectoryExists($dir.'/sprites');
    $images = new DemoImages;
    $sprites = [
        'notebook_orange' => ['notebook', ['main' => '#f97316', 'accent' => '#9a3412', 'label' => 'A4 · 100']],
        'notebook_blue' => ['notebook', ['main' => '#2563eb', 'accent' => '#1e3a8a', 'label' => 'A5 · 80']],
        'pencilbox' => ['pencilbox', ['main' => '#facc15', 'accent' => '#1e3a8a', 'box' => '#0f766e', 'label' => 'HB × 12']],
        'pencilbox_color' => ['pencilbox', ['main' => '#ef4444', 'accent' => '#7c2d12', 'box' => '#1e3a8a', 'label' => '12 Renk', 'pencils' => '#ef4444,#f59e0b,#22c55e,#3b82f6,#8b5cf6,#ec4899']],
        'ruler' => ['ruler', ['main' => '#38bdf8', 'accent' => '#0c4a6e']],
        'gel_green' => ['gel', ['main' => '#16a34a', 'accent' => '#14532d']],
        'paint' => ['paint', ['set' => '#ef4444,#f97316,#facc15,#22c55e,#06b6d4,#3b82f6,#8b5cf6,#ec4899,#78350f,#111827,#ffffff,#64748b']],
        'markers' => ['markers', ['set' => '#ef4444,#f97316,#22c55e,#3b82f6,#a855f7']],
        'highlighter' => ['highlighter', ['main' => '#fde047', 'accent' => '#ca8a04']],
        'sticky' => ['sticky', ['set' => '#fde047,#f9a8d4,#93c5fd']],
        'stapler' => ['stapler', ['main' => '#dc2626']],
        'paper' => ['paper', ['main' => '#0f766e', 'accent' => '#134e4a']],
        'glue' => ['glue', ['main' => '#7c3aed', 'accent' => '#4c1d95', 'label' => '21 gr']],
    ];
    foreach ($sprites as $name => [$shape, $colors]) {
        imagepng($images->cutout($shape, $colors, 600), "{$dir}/sprites/{$name}.png");
    }
    $this->info(count($sprites).' ürün çizimi dışa aktarıldı, videolar üretiliyor…');

    $result = Process::timeout(900)->run(['python', '-I', database_path('seeders/Support/demo_videos.py'), $dir]);
    if (! $result->successful()) {
        $this->error(trim($result->errorOutput()) ?: 'Video üretilemedi.');

        return 1;
    }
    foreach (File::glob($dir.'/*.webm') as $file) {
        $this->line(basename($file).' · '.round(filesize($file) / 1048576, 1).' MB');
    }

    return 0;
})->purpose('Demo vitrin videolarını üretir (yerel)');

// Mağaza: geri dönüşü gelmeyen kart ödemelerini iyzico'dan sorgulayıp sonuçlandırır, süresi dolanları kapatır (stok serbest kalır).
Schedule::command('store:expire-pending-payments')->everyFiveMinutes()->withoutOverlapping();

// Mağaza: süresinde ödenmeyen havale / EFT siparişlerini iptal eder (stok geri döner, müşteriye e-posta gider).
Schedule::command('store:expire-transfers')->hourly()->withoutOverlapping();

Artisan::command('store:expire-transfers', function (OrderService $orders) {
    $this->info($orders->expireTransfers().' havale siparişi iptal edildi.');
})->purpose('Mağaza: ödeme süresi geçen havale / EFT siparişlerini iptal eder');

Artisan::command('store:expire-pending-payments', function (PaymentService $payments) {
    $this->info($payments->expireStale().' bekleyen ödeme işlendi.');
})->purpose('Mağaza: 30 dakikayı geçen ödeme bekleyen siparişleri sonuçlandırır');

// Mağaza: "stoğa gelince haber ver" isteklerinden stoğa girenlere e-posta gönderir.
Schedule::command('store:stock-alerts')->everyTenMinutes()->withoutOverlapping();

Artisan::command('store:stock-alerts', function (StockAlerts $alerts) {
    $this->info($alerts->sendDue().' stok bildirimi gönderildi.');
})->purpose('Mağaza: stoğa giren ürünler için bekleyen bildirimleri gönderir');
