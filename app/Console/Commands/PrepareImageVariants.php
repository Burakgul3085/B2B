<?php

namespace App\Console\Commands;

use App\Domain\Files\ImageVariants;
use App\Jobs\GenerateImageVariants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Küçük boyutu eksik ürün görsellerini (mağaza ve B2B) kuyruğa verir. Yeni yüklenen görseller zaten yüklemede
 * hazırlanır; bu komut önceden yüklenmiş görseller içindir ve her yayında çalışır (yalnızca eksikleri bulur, hızlıdır).
 * Tablolar model yerine doğrudan okunur: komut iki modülün de dosya yollarını yalnızca okur, kayıtlarına dokunmaz.
 */
class PrepareImageVariants extends Command
{
    protected $signature = 'images:prepare {--now : Kuyruğa vermeden hemen üret}';

    protected $description = 'Küçük boyutu eksik ürün görsellerini hazırlar (mağaza ve B2B)';

    public function handle(ImageVariants $variants): int
    {
        $queued = 0;
        foreach (['product_images', 'store_product_images'] as $table) {
            DB::table($table)->orderBy('id')->select(['id', 'path'])->chunkById(500, function ($rows) use ($variants, &$queued) {
                foreach ($rows as $row) {
                    if ($variants->isPrepared($row->path)) {
                        continue;
                    }
                    $this->option('now') ? $variants->prepare($row->path) : GenerateImageVariants::dispatch($row->path);
                    $queued++;
                }
            });
        }

        $this->info($queued === 0 ? 'Tüm ürün görsellerinin küçük boyutları hazır.' : "{$queued} görselin küçük boyutları ".($this->option('now') ? 'hazırlandı.' : 'kuyruğa verildi.'));

        return self::SUCCESS;
    }
}
