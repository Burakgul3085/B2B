<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Print\PrintSettings;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreOrder;
use App\Models\Store\StorePrintJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Fotokopi: fiyat ve sınır ayarları; siparişteki baskı belgelerinin indirilmesi. */
class PrintController extends Controller
{
    public function edit(PrintSettings $settings): View
    {
        return view('store.admin.print.settings', ['s' => $settings->all()]);
    }

    public function update(Request $request, PrintSettings $settings): RedirectResponse
    {
        $settings->save($request->all());

        return back()->with('status', 'Fotokopi ayarları kaydedildi.');
    }

    /** kind: "pdf" (baskıya giden dosya) veya "ozgun" (müşterinin yüklediği dosya). */
    public function download(StoreOrder $storeOrder, StorePrintJob $storePrintJob, string $kind): StreamedResponse
    {
        abort_unless($storePrintJob->order_id === $storeOrder->id, 404);
        $file = $storePrintJob->file;
        $path = $kind === 'pdf' ? $file->printablePath() : $file->path;
        abort_unless($file->status !== 'deleted' && $path && Storage::disk('local')->exists($path), 404);
        $base = pathinfo($file->original_name, PATHINFO_FILENAME);
        $name = $storeOrder->order_no.'-'.$storePrintJob->id.'-'.$base.'.'.pathinfo($path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download($path, $name);
    }
}
