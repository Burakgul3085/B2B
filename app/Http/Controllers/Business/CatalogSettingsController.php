<?php

namespace App\Http\Controllers\Business;

use App\Domain\Audit\AuditLogger;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * KDV oranları ve birimler. Kullanılan bir KDV oranı silinmez/değiştirilmez; pasifleştirilir ve
 * yeni oran eklenir — böylece geçmiş kayıtlar etkilenmez.
 */
class CatalogSettingsController extends Controller
{
    public function __construct(private AuditLogger $audit, private TenantContext $tenant) {}

    public function index(): View
    {
        return view('business.catalog.settings', [
            'taxRates' => TaxRate::withCount('products')->orderBy('rate')->get(),
            'units' => Unit::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function storeTaxRate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rate' => ['required', 'decimal:0,2', 'min:0', 'max:100',
                Rule::unique('tax_rates', 'rate')->where('business_id', $this->tenant->businessId())],
            'name' => ['nullable', 'string', 'max:40'],
        ], ['rate.unique' => 'Bu oran zaten tanımlı.'], ['rate' => 'KDV oranı']);

        $rate = TaxRate::create([
            'rate' => $data['rate'],
            'name' => $data['name'] ?: 'KDV %'.rtrim(rtrim(number_format((float) $data['rate'], 2, ',', ''), '0'), ','),
            'is_active' => true,
        ]);
        $this->audit->log('catalog.tax_rate_created', $rate, [], $rate->only(['rate', 'name']));

        return back()->with('status', 'KDV oranı eklendi.');
    }

    public function toggleTaxRate(TaxRate $taxRate): RedirectResponse
    {
        if ($taxRate->is_active && Product::where('tax_rate_id', $taxRate->id)->notArchived()->where('is_active', true)->exists()) {
            return back()->withErrors(['tax' => "{$taxRate->name} aktif ürünlerde kullanılıyor. Önce bu ürünlerin KDV oranını değiştirin."]);
        }

        $taxRate->update(['is_active' => ! $taxRate->is_active]);
        $this->audit->log('catalog.tax_rate_toggled', $taxRate, [], ['is_active' => $taxRate->is_active]);

        return back()->with('status', 'KDV oranı güncellendi.');
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $request->merge(['code' => Str::upper(Str::ascii($request->scalar('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/',
                Rule::unique('units', 'code')->where('business_id', $this->tenant->businessId())],
            'name' => ['required', 'string', 'max:40'],
            'allows_fraction' => ['boolean'],
        ], [], ['code' => 'birim kodu', 'name' => 'birim adı']);

        Unit::create($data + ['allows_fraction' => false]);

        return back()->with('status', 'Birim eklendi.');
    }

    public function updateUnit(Request $request, Unit $unit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:40']], [], ['name' => 'birim adı']);
        $unit->update($data);

        return back()->with('status', 'Birim güncellendi.');
    }
}
