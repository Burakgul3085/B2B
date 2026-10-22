<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Audit\AuditLogger;
use App\Domain\Platform\BackupService;
use App\Http\Controllers\Controller;
use App\Jobs\RunBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Yedekler: liste, şimdi yedek al (kuyrukta), indir, sil. Her işlem denetim kaydına yazılır. */
class BackupController extends Controller
{
    public function __construct(private BackupService $backups, private AuditLogger $audit) {}

    public function index(): View
    {
        return view('platform.backups', ['backups' => $this->backups->list(), 'keep' => (int) config('b2b.backup.keep')]);
    }

    public function store(): RedirectResponse
    {
        RunBackup::dispatch();
        $this->audit->log('platform.backup_requested');

        return back()->with('status', 'Yedek alma başlatıldı. Birkaç dakika içinde listede görünür (sayfayı yenileyin).');
    }

    public function download(string $name): StreamedResponse
    {
        $path = $this->backups->path($name);
        $this->audit->log('platform.backup_downloaded', null, [], ['name' => $name]);

        return Storage::disk('local')->download($path, $name, ['Content-Type' => 'application/zip']);
    }

    public function destroy(string $name): RedirectResponse
    {
        $this->backups->delete($name);
        $this->audit->log('platform.backup_deleted', null, ['name' => $name], []);

        return back()->with('status', 'Yedek silindi.');
    }
}
