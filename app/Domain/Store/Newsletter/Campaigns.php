<?php

namespace App\Domain\Store\Newsletter;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\ImageUploader;
use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Store\Storefront\ImageVariants;
use App\Models\Store\StoreNewsletterCampaign;
use App\Models\Store\StoreNewsletterSubscriber;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Toplu e-posta: taslak yazılır, kendine deneme gönderilir, "Gönder" ile o anki tüm onaylı abonelere gönderim
 * satırları açılır ve kuyruk parti parti gönderir (SendCampaignBatch). Aynı aboneye aynı kampanya iki kez gitmez;
 * her e-postada kişiye özel abonelikten çıkma bağlantısı ve List-Unsubscribe başlığı bulunur.
 */
class Campaigns
{
    /** Bir partide gönderilen e-posta ve partiler arası bekleme (saniye). Dakikada ~40 e-posta. */
    public const BATCH_SIZE = 40;

    public const BATCH_DELAY = 60;

    public function __construct(private AuditLogger $audit, private ImageUploader $images, private ImageVariants $variants, private StoreMailer $mailer) {}

    /** @throws ValidationException|DomainException */
    public function save(?StoreNewsletterCampaign $campaign, array $input, ?UploadedFile $image, bool $removeImage): StoreNewsletterCampaign
    {
        if ($campaign && ! $campaign->isDraft()) {
            throw new DomainException('Gönderimi başlamış e-posta düzenlenemez.');
        }
        $data = Validator::make($input, [
            'subject' => ['required', 'string', 'max:150'],
            'preheader' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:30000'],
            'button_label' => ['nullable', 'required_with:button_url', 'string', 'max:40'],
            'button_url' => ['nullable', 'required_with:button_label', 'url:https', 'max:255'],
        ], [], ['subject' => 'konu', 'preheader' => 'ön izleme metni', 'body' => 'metin', 'button_label' => 'düğme yazısı', 'button_url' => 'düğme adresi'])->validate();

        $campaign ??= new StoreNewsletterCampaign;
        $campaign->fill(array_map(fn ($v) => is_string($v) ? (trim($v) ?: null) : $v, $data))->save();

        if ($image || $removeImage) {
            $old = $campaign->image_path;
            $campaign->forceFill(['image_path' => $image ? $this->images->store($image, 'store/newsletter', 1200, 6 * 1024 * 1024, 84) : null])->save();
            if ($old) {
                $this->images->delete($old);
                $this->variants->forget($old);
            }
        }
        $this->audit->log('store.newsletter_campaign_saved', $campaign, [], ['subject' => $campaign->subject]);

        return $campaign;
    }

    public function delete(StoreNewsletterCampaign $campaign): void
    {
        if ($campaign->status === 'sending') {
            throw new DomainException('Gönderilmekte olan e-posta silinemez; önce durdurun.');
        }
        $this->audit->log('store.newsletter_campaign_deleted', $campaign, ['subject' => $campaign->subject]);
        $campaign->delete();
        if ($campaign->image_path) {
            $this->images->delete($campaign->image_path);
        }
    }

    public function sendTest(StoreNewsletterCampaign $campaign, string $to): bool
    {
        return $this->deliver($campaign, $to, null, '[Deneme] ');
    }

    /** @throws DomainException */
    public function start(StoreNewsletterCampaign $campaign): int
    {
        $count = DB::transaction(function () use ($campaign) {
            $campaign = StoreNewsletterCampaign::lockForUpdate()->findOrFail($campaign->id);
            if (! $campaign->isDraft()) {
                throw new DomainException('Bu e-posta zaten gönderildi veya gönderiliyor.');
            }
            $ids = StoreNewsletterSubscriber::where('status', 'active')->pluck('id');
            if ($ids->isEmpty()) {
                throw new DomainException('Gönderilecek onaylı abone yok.');
            }
            foreach ($ids->chunk(1000) as $chunk) {
                DB::table('store_newsletter_deliveries')->insert($chunk->map(fn ($id) => ['campaign_id' => $campaign->id, 'subscriber_id' => $id, 'status' => 'pending'])->values()->all());
            }
            $campaign->forceFill(['status' => 'sending', 'recipients_count' => $ids->count(), 'started_at' => now()])->save();

            return $ids->count();
        });
        $this->audit->log('store.newsletter_campaign_started', $campaign, [], ['recipients' => $count]);
        SendCampaignBatch::dispatch($campaign->id);

        return $count;
    }

    public function cancel(StoreNewsletterCampaign $campaign): void
    {
        if ($campaign->status !== 'sending') {
            return;
        }
        $campaign->deliveries()->where('status', 'pending')->update(['status' => 'skipped']);
        $campaign->forceFill(['status' => 'cancelled', 'finished_at' => now()])->save();
        $this->audit->log('store.newsletter_campaign_cancelled', $campaign);
    }

    /** Bir parti gönderir; bekleyen kalırsa true. */
    public function sendBatch(StoreNewsletterCampaign $campaign): bool
    {
        if ($campaign->status !== 'sending') {
            return false;
        }
        $batch = $campaign->deliveries()->with('subscriber')->where('status', 'pending')->orderBy('id')->limit(self::BATCH_SIZE)->get();
        foreach ($batch as $delivery) {
            $subscriber = $delivery->subscriber;
            if (! $subscriber || $subscriber->status !== 'active') {
                $delivery->update(['status' => 'skipped']);
                $campaign->decrement('recipients_count');

                continue;
            }
            $ok = $this->deliver($campaign, $subscriber->email, $subscriber);
            $delivery->update(['status' => $ok ? 'sent' : 'failed', 'sent_at' => $ok ? now() : null]);
            $campaign->increment($ok ? 'sent_count' : 'failed_count');
        }

        $remaining = $campaign->deliveries()->where('status', 'pending')->exists();
        if (! $remaining) {
            $campaign->forceFill(['status' => 'sent', 'finished_at' => now()])->save();
        }

        return $remaining;
    }

    /** Kampanyanın e-posta HTML'i (önizleme ve gönderim). */
    public function render(StoreNewsletterCampaign $campaign, ?StoreNewsletterSubscriber $subscriber): string
    {
        return view('emails.newsletter', [
            'campaign' => $campaign,
            'body' => Str::markdown((string) $campaign->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'imageUrl' => $campaign->image_path ? route('store.media.newsletter', ['storeNewsletterCampaign' => $campaign->id, 'w' => 1200]) : null,
            'unsubscribeUrl' => $subscriber ? route('store.newsletter.unsubscribe', $subscriber->token) : route('store.home'),
            'settings' => StoreSetting::values(),
        ])->render();
    }

    private function deliver(StoreNewsletterCampaign $campaign, string $to, ?StoreNewsletterSubscriber $subscriber, string $prefix = ''): bool
    {
        $headers = $subscriber ? ['List-Unsubscribe' => '<'.route('store.newsletter.unsubscribe', $subscriber->token).'>'] : [];
        $text = strip_tags(Str::markdown((string) $campaign->body))."\n\n".($subscriber ? 'Abonelikten çıkmak için: '.route('store.newsletter.unsubscribe', $subscriber->token) : '');

        return $this->mailer->sendHtml($to, $prefix.$campaign->subject, $this->render($campaign, $subscriber), $text, $headers);
    }
}
