<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Invoicing\InvoiceService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Bayi → Faturalarım: seçili toptancının bu bayiye kestiği faturalar (yalnızca kesilmiş olanlar). Bayi yalnızca kendi
 * siparişlerinin faturasını görür ve indirir; başka bayinin / işletmenin faturası 404 döner.
 */
class InvoiceController extends Controller
{
    public function index(): View
    {
        return view('dealer.invoices.index', [
            'invoices' => self::visible()->with('order')->orderByDesc('issue_date')->orderByDesc('id')->paginate(30),
        ]);
    }

    public function pdf(Invoice $invoice): Response
    {
        abort_unless(self::visible()->whereKey($invoice->id)->exists(), 404);
        $pdf = InvoiceService::pdfContents($invoice);
        abort_if($pdf === null, 404);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.preg_replace('/[^A-Za-z0-9._-]/', '', (string) $invoice->invoice_no).'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Bu bayinin (oturumdaki ilişki) kesilmiş faturaları. */
    public static function visible(): Builder
    {
        $relationshipId = app(TenantContext::class)->dealerRelationshipId();

        return Invoice::query()->where('status', 'sent')
            ->whereHas('order', fn ($q) => $q->where('dealer_relationship_id', $relationshipId));
    }
}
