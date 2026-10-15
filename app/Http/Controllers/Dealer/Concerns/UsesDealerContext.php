<?php

namespace App\Http\Controllers\Dealer\Concerns;

use App\Domain\Tenancy\TenantContext;
use App\Models\BusinessSetting;
use App\Models\DealerRelationship;

trait UsesDealerContext
{
    /** Oturumda seçili ve SetDealerContext tarafından doğrulanmış ilişki. */
    protected function relationship(): DealerRelationship
    {
        return DealerRelationship::with('group.priceList')->findOrFail(app(TenantContext::class)->dealerRelationshipId());
    }

    protected function settings(): BusinessSetting
    {
        return BusinessSetting::where('business_id', app(TenantContext::class)->businessId())->firstOrFail();
    }
}
