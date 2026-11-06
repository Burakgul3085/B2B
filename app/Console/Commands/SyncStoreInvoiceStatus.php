<?php

namespace App\Console\Commands;

use App\Domain\Store\Invoicing\EInvoiceService;
use DomainException;
use Illuminate\Console\Command;

/**
 * Uyumsoft'a gönderilmiş faturaların son durumunu sorgular (zamanlayıcı her dakika çalıştırır). Uyumsoft fatura
 * numarasını hemen verir, GİB denetimini birkaç saniye sonra yapar: reddedilen fatura "Reddedildi" olur ve müşteriye
 * gösterilmez; portaldan elle iptal edilen fatura panelde de iptal görünür; onaylanınca müşteriye e-posta gider.
 */
class SyncStoreInvoiceStatus extends Command
{
    protected $signature = 'store:invoice-status {--limit=20 : Bir çalışmada sorgulanacak en fazla fatura}';

    protected $description = 'Uyumsoft\'a gönderilmiş mağaza faturalarının son durumunu sorgular';

    public function handle(EInvoiceService $invoices): int
    {
        foreach (EInvoiceService::statusCandidates()->limit(max(1, (int) $this->option('limit')))->get() as $invoice) {
            try {
                $state = $invoices->syncStatus($invoice);
                $this->line("{$invoice->invoice_no}: {$state}".($invoice->provider_status ? " ({$invoice->provider_status})" : ''));
            } catch (DomainException $e) {
                $this->warn("{$invoice->invoice_no}: {$e->getMessage()}");

                return self::SUCCESS; // sağlayıcıya ulaşılamıyor; bir sonraki çalışmada yeniden denenir
            }
        }

        return self::SUCCESS;
    }
}
