<?php

namespace App\Jobs;

use App\Domain\Store\Print\PrintFiles;
use App\Models\Store\StorePrintFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Yüklenen fotokopi belgesini hazırlar: PDF'e çevirme, sayfa sayımı, önizleme (PrintFiles::process). Kuyruk: print. */
class ProcessPrintFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 330;

    public function __construct(public int $fileId)
    {
        $this->onQueue('print'); // ayrı işçi (deploy/supervisor.conf): uzun çevirmeler e-postaları bekletmez
    }

    public function handle(PrintFiles $files): void
    {
        if ($file = StorePrintFile::find($this->fileId)) {
            $files->process($file);
        }
    }
}
