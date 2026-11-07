<?php

namespace App\Console\Commands;

use App\Domain\Invoicing\InvoiceService;
use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use App\Models\InvoiceSetting;
use DomainException;
use Illuminate\Console\Command;

/**
 * B2B faturaları (dakikada bir, her işletme kendi bağlamında):
 *  1. Sonucu belirsiz kalan Uyumsoft gönderimlerini sorgular (kesildiyse tamamlar, kesilmediyse taslağa alır).
 *  2. Kesilen faturaların Uyumsoft / GİB durumunu sorgular (onaylandı → bayiye bildirim, reddedildi / iptal → kapanır).
 *  3. Otomatik kesim açıksa ödemesi alınan / yola çıkan siparişlerin faturasını keser.
 * Mağaza faturalarıyla ilgisi yoktur (store:invoice-status ayrı çalışır).
 */
class ProcessB2bInvoices extends Command
{
    protected $signature = 'b2b:invoices';

    protected $description = 'B2B: otomatik fatura kesimi ve Uyumsoft durum sorgusu';

    public function handle(TenantContext $tenant): int
    {
        $businessIds = InvoiceSetting::withoutBusinessScope()->pluck('business_id');

        foreach (Business::whereIn('id', $businessIds)->where('status', 'active')->get() as $business) {
            $tenant->runAs($business->id, function () use ($business) {
                $service = app(InvoiceService::class);
                $checked = $synced = 0;

                foreach (InvoiceService::unsettledCandidates()->limit(10)->get() as $invoice) {
                    try {
                        $service->check($invoice);
                        $checked++;
                    } catch (DomainException) {
                        // sağlayıcıya ulaşılamadı: bir sonraki çalışmada yeniden denenir
                    }
                }
                foreach (InvoiceService::statusCandidates()->limit(30)->get() as $invoice) {
                    try {
                        $service->syncStatus($invoice);
                        $synced++;
                    } catch (DomainException) {
                    }
                }
                $auto = $service->autoIssue();

                if ($checked || $synced || $auto['sent'] || $auto['failed']) {
                    $this->line("{$business->id}: {$checked} belirsiz sorgulandı, {$synced} durum güncellendi, {$auto['sent']} fatura kesildi, {$auto['failed']} sorunlu.");
                }
            });
        }

        return self::SUCCESS;
    }
}
