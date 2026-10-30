<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Content\ContactMessages;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Satış → Mesajlar: iletişim formundan gelen mesajlar. */
class ContactMessageController extends Controller
{
    public function __construct(private ContactMessages $messages) {}

    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(array_keys(StoreContactMessage::STATUSES))]])['status'] ?? null;

        return view('store.admin.messages.index', [
            'messages' => StoreContactMessage::query()->when($status, fn ($q, $s) => $q->where('status', $s))->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'counts' => StoreContactMessage::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(StoreContactMessage $storeContactMessage): View
    {
        $this->messages->markRead($storeContactMessage);

        return view('store.admin.messages.show', ['message' => $storeContactMessage, 'replies' => $storeContactMessage->replies()->get()]);
    }

    /** Müşteriye e-postayla yanıt. */
    public function reply(Request $request, StoreContactMessage $storeContactMessage): RedirectResponse
    {
        $this->messages->reply($storeContactMessage, $request->user('store_admin'), $request->only('body'));

        return back()->with('status', "Yanıtınız {$storeContactMessage->email} adresine e-postayla gönderildi.");
    }

    public function replied(StoreContactMessage $storeContactMessage): RedirectResponse
    {
        $this->messages->markReplied($storeContactMessage);

        return back()->with('status', 'Mesaj yanıtlandı olarak işaretlendi.');
    }

    /** Listede seçilen mesajları siler. */
    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['messages' => ['required', 'array', 'max:100'], 'messages.*' => ['integer']], [], ['messages' => 'mesajlar'])['messages'];
        $count = $this->messages->deleteMany($ids);

        return back()->with('status', "{$count} mesaj silindi.");
    }

    public function destroy(StoreContactMessage $storeContactMessage): RedirectResponse
    {
        $this->messages->delete($storeContactMessage);

        return redirect()->route('store_admin.messages.index')->with('status', 'Mesaj silindi.');
    }
}
