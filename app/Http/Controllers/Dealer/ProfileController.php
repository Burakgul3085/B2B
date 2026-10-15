<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Bayi işletme profili. Unvan ve vergi bilgileri toptancı onayıyla belirlendiği için yalnızca görüntülenir. */
class ProfileController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function show(Request $request): View
    {
        return view('dealer.profile', ['dealer' => $request->user()->dealer]);
    }

    public function update(Request $request): RedirectResponse
    {
        $dealer = $request->user()->dealer;
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9 +()\-]{7,30}$/'],
            'city' => ['required', 'string', 'max:60'],
            'district' => ['required', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:500'],
        ], [], ['phone' => 'telefon', 'city' => 'il', 'district' => 'ilçe', 'address' => 'adres']);

        $old = $dealer->only(array_keys($data));
        $dealer->update($data);
        $this->audit->log('dealer.profile_updated', $dealer, $old, $data);

        return back()->with('status', 'İşletme bilgileriniz güncellendi.');
    }
}
