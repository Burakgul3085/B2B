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
 * Günlük ödeme hatırlatması (docs/03 §6.1): bir borcun son ödeme gününe 3 gün kaldığında, son gün ve geciktiğinde
 * (1. gün, sonra haftada bir) bayiye e-posta. Aynı gün bir bayiye en fazla bir e-posta gider.
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
                    $due = $aging['items']->contains(fn ($i) => in_array($i['days_left'], [3, 0], true)
                        || ($i['bucket'] === 'overdue' && $i['days_overdue'] % 7 === 1));
                    if ($due) {
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
