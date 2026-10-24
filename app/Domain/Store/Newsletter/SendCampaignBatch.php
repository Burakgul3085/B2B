<?php

namespace App\Domain\Store\Newsletter;

use App\Models\Store\StoreNewsletterCampaign;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Kampanyanın bir partisini gönderir; bekleyen kaldıkça kendini bir dakika sonrasına yeniden kuyruğa ekler. */
class SendCampaignBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $campaignId) {}

    public function handle(Campaigns $campaigns): void
    {
        $campaign = StoreNewsletterCampaign::find($this->campaignId);
        if ($campaign && $campaigns->sendBatch($campaign)) {
            self::dispatch($this->campaignId)->delay(now()->addSeconds(Campaigns::BATCH_DELAY));
        }
    }
}
