<?php

namespace App\Http\Controllers;

use App\Domain\Auth\Area;
use App\Domain\Tenancy\TenantContext;
use App\Models\AppNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Bildirim merkezi (toptancı ve bayi). Bayi yalnızca seçili toptancıdan gelen bildirimleri görür. */
class NotificationController extends Controller
{
    public function __construct(private TenantContext $tenant) {}

    public function index(Request $request): View
    {
        $area = Area::fromRequest($request);

        return view('notifications.index', [
            'area' => $area,
            'notifications' => $this->query($area)->latest('id')->paginate(30),
        ]);
    }

    /** Bildirimi okundu işaretler ve hedef sayfaya gider (yalnızca uygulama içi adresler). */
    public function open(Request $request, AppNotification $notification): RedirectResponse
    {
        $area = Area::fromRequest($request);
        abort_unless($this->query($area)->whereKey($notification->id)->exists(), 404);

        $notification->update(['read_at' => $notification->read_at ?? now()]);
        $url = (string) $notification->url;

        return str_starts_with($url, url('/').'/') ? redirect()->to($url) : redirect()->route($area->homeRoute());
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->query(Area::fromRequest($request))->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'Tüm bildirimler okundu olarak işaretlendi.');
    }

    private function query(Area $area): Builder
    {
        return AppNotification::query()->for($area === Area::Dealer ? 'dealer' : 'business', $this->tenant->dealerRelationshipId());
    }
}
