<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Dealers\DealerLogo;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Bayinin kendi logosu: yalnızca oturumdaki bayi kendi logosunu yükler, kaldırır ve görür (rota parametresi yok). */
class LogoController extends Controller
{
    public function update(Request $request, DealerLogo $logos): RedirectResponse
    {
        $request->validate(['logo' => ['required', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp']], [], ['logo' => 'logo']);

        try {
            $logos->replace($request->user('dealer')->dealer, $request->file('logo'));
        } catch (DomainException $e) {
            return back()->withErrors(['logo' => $e->getMessage()]);
        }

        return back()->with('status', 'Logonuz güncellendi.');
    }

    public function destroy(Request $request, DealerLogo $logos): RedirectResponse
    {
        $logos->remove($request->user('dealer')->dealer);

        return back()->with('status', 'Logonuz kaldırıldı.');
    }

    public function show(Request $request): StreamedResponse
    {
        $path = $request->user('dealer')->dealer->logo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }
}
