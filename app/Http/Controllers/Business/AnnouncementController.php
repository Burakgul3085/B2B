<?php

namespace App\Http\Controllers\Business;

use App\Domain\Audit\AuditLogger;
use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\DealerGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('business.announcements', [
            'announcements' => Announcement::with('group')->latest('id')->paginate(20),
            'groups' => DealerGroup::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, Notifier $notifier, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'dealer_group_id' => ['nullable', 'integer', Rule::exists('dealer_groups', 'id')->where('business_id', $request->user()->business_id)],
        ], [], ['title' => 'başlık', 'body' => 'duyuru metni', 'dealer_group_id' => 'bayi grubu']);

        $announcement = Announcement::create($data + [
            'send_email' => $request->boolean('send_email'), 'published_at' => now(), 'created_by' => $request->user()->id,
        ]);
        $count = $notifier->announcement($announcement);
        $audit->log('announcement.published', $announcement, [], ['recipients' => $count]);

        return back()->with('status', "Duyuru {$count} bayiye yayımlandı.".($announcement->send_email ? ' E-postalar kuyruğa alındı.' : ''));
    }
}
