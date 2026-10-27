<?php

namespace App\Jobs;

use App\Domain\Support\OpsStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Zamanlayıcının beş dakikada bir kuyruğa attığı iş: çalışırsa kuyruk işçisi ayakta demektir (OpsStatus). */
class QueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public function handle(OpsStatus $ops): void
    {
        $ops->recordQueue();
    }
}
