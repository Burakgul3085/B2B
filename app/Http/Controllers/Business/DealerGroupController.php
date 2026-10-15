<?php

namespace App\Http\Controllers\Business;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\DealerGroup;
use App\Models\DiscountRule;
use App\Models\PriceList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DealerGroupController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        return view('business.dealers.groups', [
            'groups' => DealerGroup::with('priceList')->withCount('relationships')->orderBy('name')->get(),
            'priceLists' => PriceList::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $group = DealerGroup::create($this->validated($request));
        $this->audit->log('dealer.group_created', $group, [], $group->only(['name', 'price_list_id']));

        return back()->with('status', 'Bayi grubu eklendi.');
    }

    public function update(Request $request, DealerGroup $group): RedirectResponse
    {
        $data = $this->validated($request, $group);
        $old = $group->only(array_keys($data));
        $group->update($data);
        $this->audit->log('dealer.group_updated', $group, $old, $data);

        return back()->with('status', 'Bayi grubu güncellendi.');
    }

    public function destroy(DealerGroup $group): RedirectResponse
    {
        if ($group->relationships()->exists() || DiscountRule::where('dealer_group_id', $group->id)->exists()) {
            return back()->withErrors(['group' => "\"{$group->name}\" grubunda bayi veya ona bağlı indirim var; önce bunları kaldırın."]);
        }
        $group->delete();

        return back()->with('status', 'Bayi grubu silindi.');
    }

    private function validated(Request $request, ?DealerGroup $group = null): array
    {
        $businessId = $request->user()->business_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('dealer_groups', 'name')->where('business_id', $businessId)->ignore($group?->id)],
            'price_list_id' => ['nullable', 'integer', Rule::exists('price_lists', 'id')->where('business_id', $businessId)],
        ], [], ['name' => 'grup adı', 'price_list_id' => 'fiyat listesi']);
    }
}
