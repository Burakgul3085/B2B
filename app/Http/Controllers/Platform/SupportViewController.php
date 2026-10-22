<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\SupportAccess;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\DealerRelationship;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Destek görünümü: toptancının verdiği süreli erişimle siparişler, bayiler ve ürünler SALT OKUNUR listelenir.
 * Erişim yoksa veya süresi dolduysa 403. Her bakış işletmenin destek kayıtlarına yazılır.
 */
class SupportViewController extends Controller
{
    public const SECTIONS = ['orders' => 'Siparişler', 'dealers' => 'Bayiler', 'products' => 'Ürünler'];

    public function __invoke(Request $request, Business $business, SupportAccess $support, TenantContext $tenant, string $section = 'orders'): View
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404);
        $grant = $support->activeFor($business->id, $request->user('platform')->id);
        abort_unless($grant, 403, 'Bu işletme için etkin destek erişiminiz yok.');
        $support->logView($grant, $section);

        $rows = $tenant->runAs($business->id, fn () => match ($section) {
            'orders' => Order::with('relationship.dealer:id,legal_name')->latest('id')->limit(50)->get(),
            'dealers' => DealerRelationship::with('dealer:id,legal_name,city')->latest('id')->limit(100)->get(),
            'products' => Product::with('unit:id,name')->latest('id')->limit(100)->get(['id', 'sku', 'name', 'list_price', 'is_active', 'unit_id']),
        });

        return view('platform.support-view', compact('business', 'grant', 'section', 'rows') + ['sections' => self::SECTIONS]);
    }
}
