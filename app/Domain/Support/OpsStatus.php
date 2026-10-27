<?php

namespace App\Domain\Support;

use App\Domain\Platform\BackupService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Sunucu işletiminin canlılık sinyalleri (Yayın hazırlığı listesi için): zamanlanmış görevler her dakika, kuyruk işçisi
 * beş dakikada bir (zamanlayıcının kuyruğa attığı küçük iş) iz bırakır; son yedek, yedek klasöründeki en yeni dosyadır.
 * İzler kalıcı önbellekte (CACHE_STORE=database) tutulur.
 */
class OpsStatus
{
    private const SCHEDULER = 'ops:scheduler_seen';

    private const QUEUE = 'ops:queue_seen';

    public function recordScheduler(): void
    {
        Cache::forever(self::SCHEDULER, now()->timestamp);
    }

    public function recordQueue(): void
    {
        Cache::forever(self::QUEUE, now()->timestamp);
    }

    public function schedulerSeenAt(): ?Carbon
    {
        return $this->seen(self::SCHEDULER);
    }

    public function queueSeenAt(): ?Carbon
    {
        return $this->seen(self::QUEUE);
    }

    public function lastBackupAt(): ?Carbon
    {
        $disk = Storage::disk('local');
        $latest = collect($disk->files(BackupService::DIR))->filter(fn ($p) => str_ends_with($p, '.zip'))
            ->map(fn ($p) => $disk->lastModified($p))->max();

        return $latest ? Carbon::createFromTimestamp($latest) : null;
    }

    private function seen(string $key): ?Carbon
    {
        $ts = Cache::get($key);

        return $ts ? Carbon::createFromTimestamp($ts) : null;
    }
}
