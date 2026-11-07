<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\PlatformAdminService;
use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Platform → Yöneticiler: platform yöneticisi hesaplarının listesi ve silinmesi. */
class PlatformAdminController extends Controller
{
    public function __construct(private PlatformAdminService $admins) {}

    public function index(Request $request): View
    {
        return view('platform.admins.index', [
            'admins' => PlatformAdmin::orderByDesc('is_active')->orderBy('name')->get(),
            'me' => $request->user('platform'),
        ]);
    }

    public function destroy(Request $request, PlatformAdmin $platformAdmin): RedirectResponse
    {
        try {
            $this->admins->delete($platformAdmin, $request->user('platform'));
        } catch (DomainException $e) {
            return back()->withErrors(['admin' => $e->getMessage()]);
        }

        return redirect()->route('platform.admins.index')->with('status', "{$platformAdmin->name} hesabı silindi.");
    }
}
