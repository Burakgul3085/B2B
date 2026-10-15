<?php

namespace App\Jobs;

use App\Domain\Mail\MailFailure;
use App\Domain\Mail\MailTransport;
use App\Domain\Mail\SecretBox;
use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use App\Models\EmailLog;
use App\Models\EmailSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Kuyruktaki tek e-posta. Kalıcı hatalarda (yanlış şifre vb.) tekrar denenmez. */
class SendBusinessEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $businessId, public int $logId) {}

    /** @return int[] saniye: 1 dk, 5 dk, 30 dk */
    public function backoff(): array
    {
        return [60, 300, 1800];
    }

    public function handle(MailTransport $transport, SecretBox $secrets, TenantContext $tenant): void
    {
        $tenant->runAs($this->businessId, function () use ($transport, $secrets) {
            $log = EmailLog::find($this->logId);
            if (! $log || $log->status === 'sent') {
                return; // tekrar çalışırsa ikinci kez göndermez
            }

            $setting = EmailSetting::first();
            if (! $setting) {
                $log->update(['status' => 'skipped', 'last_error_code' => 'not_configured']);

                return;
            }

            $business = Business::findOrFail($this->businessId);
            $log->increment('attempts');

            try {
                $transport->send(
                    ['email' => $setting->from_email, 'name' => $setting->from_name ?: $business->display_name, 'password' => $secrets->decrypt($setting->app_password_encrypted)],
                    [
                        'to' => $log->to_email,
                        'subject' => $log->subject,
                        'html' => view('emails.business', ['business' => $business, 'subject' => $log->subject] + $log->payload)->render(),
                        'text' => $log->payload['intro']."\n\n".($log->payload['action_url'] ?? ''),
                    ],
                );
            } catch (MailFailure $e) {
                $log->update(['status' => 'failed', 'last_error_code' => $e->reason]);
                if ($e->reason === 'auth') {
                    $setting->update(['status' => 'failed', 'last_error_code' => 'auth']);
                }
                if (! $e->isPermanent()) {
                    throw $e; // geçici hata: kuyruk tekrar dener
                }

                return;
            }

            $log->update(['status' => 'sent', 'sent_at' => now(), 'last_error_code' => null]);
        });
    }

    public function failed(?Throwable $e): void
    {
        app(TenantContext::class)->runAs($this->businessId, fn () => EmailLog::whereKey($this->logId)->where('status', '!=', 'sent')
            ->update(['status' => 'failed', 'last_error_code' => $e instanceof MailFailure ? $e->reason : 'unknown']));
    }
}
