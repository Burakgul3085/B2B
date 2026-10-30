<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Print\PrintOptions;
use App\Domain\Store\Print\PrintPricing;
use App\Domain\Store\Print\PrintSettings;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreOrder;
use App\Models\Store\StorePrintJob;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Fotokopi: fiyat ve sınır ayarları, kayıtlı fiyatlarla deneme hesabı; siparişteki baskı belgelerinin indirilmesi. */
class PrintController extends Controller
{
    public function edit(Request $request, PrintSettings $settings, PrintPricing $pricing): View
    {
        $trial = ['input' => (array) $request->query('deneme', []), 'quote' => null, 'error' => null];
        if ($trial['input']) {
            $pages = filter_var($trial['input']['pages'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
            try {
                if ($pages === false) {
                    throw new DomainException('Sayfa sayısını 1 ile 100000 arasında yazın.');
                }
                $options = PrintOptions::fromInput(array_diff_key($trial['input'], ['range' => 1, 'note' => 1]), $pages, $settings);
                $trial['quote'] = $pricing->quote($pages, $options) + ['summary' => $options->summary($pages)];
            } catch (ValidationException $e) {
                $trial['error'] = collect($e->errors())->flatten()->first();
            } catch (DomainException $e) {
                $trial['error'] = $e->getMessage();
            }
        }

        return view('store.admin.print.settings', ['s' => $settings->all(), 'warnings' => $settings->warnings(), 'trial' => $trial]);
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
