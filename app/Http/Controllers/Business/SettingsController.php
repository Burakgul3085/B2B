<?php

namespace App\Http\Controllers\Business;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\ImageUploader;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** İşletme ayarları: iletişim bilgileri ve bayi panelindeki davranış ayarları. */
class SettingsController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function edit(Request $request): View
    {
        $business = $request->user()->business->load('settings');

        return view('business.settings', ['business' => $business, 'settings' => $business->settings]);
    }

    public function update(Request $request): RedirectResponse
    {
        $business = $request->user()->business->load('settings');

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:60'],
            'district' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:500'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'stock_display' => ['required', Rule::in(['exact', 'level'])],
            'ledger_post_on' => ['required', Rule::in(['approval', 'completion'])],
            'default_payment_term_days' => ['required', 'integer', 'min:0', 'max:365'],
            'price_display' => ['required', Rule::in(['excl_vat', 'incl_vat'])],
        ], [], [
            'display_name' => 'görünen ad', 'accent_color' => 'vurgu rengi',
            'default_payment_term_days' => 'varsayılan vade (gün)',
        ]);

        $businessFields = ['display_name', 'phone', 'email', 'city', 'district', 'address', 'accent_color'];
        $settingFields = ['stock_display', 'ledger_post_on', 'default_payment_term_days', 'price_display'];

        $old = $business->only($businessFields) + $business->settings->only($settingFields);

        $business->update(array_intersect_key($data, array_flip($businessFields)));
        $business->settings->update(array_intersect_key($data, array_flip($settingFields)));

        $this->audit->log('business.settings_updated', $business, $old, $data);

        return back()->with('status', 'Ayarlar kaydedildi.');
    }

    /** Logo: başvuru sayfası, bayi paneli ve e-postalarda kullanılır. Görsel yeniden kodlanır. */
    public function updateLogo(Request $request, ImageUploader $images): RedirectResponse
    {
        $business = $request->user()->business;

        if ($request->boolean('remove')) {
            $images->delete($business->logo_path);
            $business->update(['logo_path' => null]);

            return back()->with('status', 'Logo kaldırıldı.');
        }

        $request->validate(['logo' => ['required', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp']], [], ['logo' => 'logo']);

        try {
            $path = $images->store($request->file('logo'), 'businesses/'.$business->id.'/logo', 480);
        } catch (DomainException $e) {
            return back()->withErrors(['logo' => $e->getMessage()]);
        }

        $images->delete($business->logo_path);
        $business->update(['logo_path' => $path]);
        $this->audit->log('business.logo_updated', $business);

        return back()->with('status', 'Logo güncellendi.');
    }
}
