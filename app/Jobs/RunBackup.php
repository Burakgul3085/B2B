<?php

namespace App\Jobs;

use App\Domain\Platform\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Platform ekranındaki "Şimdi yedek al": istek zaman aşımına uğramasın diye kuyrukta çalışır. */
class RunBackup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function handle(BackupService $backups): void
    {
        $backups->create();
    }
}
