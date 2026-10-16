<?php

namespace App\Http\Controllers\Business;

use App\Domain\Audit\AuditLogger;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function __construct(private AuditLogger $audit, private TenantContext $tenant) {}

    public function index(Request $request): View
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $totals = WarehouseStock::selectRaw('warehouse_id, SUM(quantity) total, SUM(quantity > 0) products')
            ->groupBy('warehouse_id')->get()->keyBy('warehouse_id');

        return view('business.inventory.warehouses', [
            'warehouses' => $warehouses,
            'totals' => $totals,
            'defaultId' => $request->user()->business->settings->default_warehouse_id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $warehouse = Warehouse::create($this->validated($request));
        $this->audit->log('inventory.warehouse_created', $warehouse, [], $warehouse->only(['code', 'name']));

        return back()->with('status', 'Depo eklendi.');
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $this->validated($request, $warehouse);
        $isDefault = $request->user()->business->settings->default_warehouse_id === $warehouse->id;

        if ($isDefault && ! $data['is_active']) {
            return back()->withErrors(['warehouse' => 'Varsayılan sevk deposu pasif yapılamaz. Önce başka bir depoyu varsayılan yapın.']);
        }

        $old = $warehouse->only(array_keys($data));
        $warehouse->update($data);
        $this->audit->log('inventory.warehouse_updated', $warehouse, $old, $data);

        return back()->with('status', 'Depo güncellendi.');
    }

    /** Bayiye gösterilen ve sipariş onayında düşülen stok bu depodandır (K3). */
    public function makeDefault(Request $request, Warehouse $warehouse): RedirectResponse
    {
        abort_unless($warehouse->is_active, 422, 'Pasif depo varsayılan yapılamaz.');

        $settings = $request->user()->business->settings;
        $old = $settings->default_warehouse_id;
        $settings->update(['default_warehouse_id' => $warehouse->id]);
        $this->audit->log('inventory.default_warehouse_changed', $warehouse, ['default_warehouse_id' => $old], ['default_warehouse_id' => $warehouse->id]);

        return back()->with('status', "\"{$warehouse->name}\" varsayılan sevk deposu yapıldı.");
    }

    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        $request->merge([
            'code' => Str::upper(Str::ascii($request->scalar('code'))),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('warehouses', 'code')->where('business_id', $this->tenant->businessId())->ignore($warehouse?->id)],
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ], [], ['code' => 'depo kodu', 'name' => 'depo adı']);
    }
}
