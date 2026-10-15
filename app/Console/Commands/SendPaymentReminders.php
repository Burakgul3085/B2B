<?php

namespace App\Console\Commands;

use App\Domain\Ledger\AccountAnalysis;
use App\Domain\Notifications\Notifier;
use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use App\Models\CurrentAccount;
use App\Models\EmailSetting;
use Illuminate\Console\Command;

/**
 * Haftalık vade hatırlatması: vadesi geçmiş veya 7 gün içinde vadesi gelecek borcu olan bayilere.
 * Yalnızca hatırlatmayı açmış ve e-posta ayarı doğrulanmış işletmeler için çalışır.
 */
class SendPaymentReminders extends Command
{
    protected $signature = 'ledger:send-reminders';

    protected $description = 'Vadesi geçmiş / yaklaşan cari bakiyeler için bayilere hatırlatma gönderir';

    public function handle(TenantContext $tenant, AccountAnalysis $analysis, Notifier $notifier): int
    {
        $businessIds = EmailSetting::withoutBusinessScope()->where('payment_reminders', true)->where('status', 'verified')->pluck('business_id');
        $sent = 0;

        foreach (Business::whereIn('id', $businessIds)->where('status', 'active')->get() as $business) {
            $tenant->runAs($business->id, function () use ($analysis, $notifier, &$sent) {
                $accounts = CurrentAccount::with('relationship')->where('balance', '>', 0)->get()
                    ->filter(fn ($a) => $a->relationship->isActive());

                foreach ($analysis->agingMany($accounts) as $accountId => $aging) {
                    if (bccomp($aging['overdue'], '0', 2) > 0 || bccomp($aging['due_soon'], '0', 2) > 0) {
                        $notifier->paymentReminder($accounts->firstWhere('id', $accountId)->relationship, $aging['overdue'], $aging['due_soon']);
                        $sent++;
                    }
                }
            });
        }

        $this->info("{$sent} hatırlatma kuyruğa alındı.");

        return self::SUCCESS;
    }
}
