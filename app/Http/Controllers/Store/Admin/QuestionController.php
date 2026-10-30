<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Reviews\QuestionService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreProductQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Katalog → Ürün Soruları: cevap bekleyen, yayındaki ve gizlenen sorular; cevaplayınca yayımlanır ve soranın e-postasına gider. */
class QuestionController extends Controller
{
    public function __construct(private QuestionService $questions) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(StoreProductQuestion::STATUSES))],
            'q' => ['nullable', 'string', 'max:60'],
        ]);
        $status = $filters['status'] ?? 'pending';

        return view('store.admin.questions.index', [
            'questions' => StoreProductQuestion::with('product:id,name,slug')->where('status', $status)
                ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('question', 'like', "%{$t}%")->orWhere('email', 'like', "%{$t}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$t}%"))))
                ->orderBy($status === 'pending' ? 'id' : 'answered_at', $status === 'pending' ? 'asc' : 'desc')->paginate(30)->withQueryString(),
            'status' => $status,
            'filters' => $filters,
            'counts' => StoreProductQuestion::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function answer(Request $request, StoreProductQuestion $storeProductQuestion): RedirectResponse
    {
        $first = $storeProductQuestion->answered_at === null;
        $this->questions->answer($storeProductQuestion, $request->only(['answer', 'question']), $request->user('store_admin')->id);

        return back()->with('status', $first ? 'Cevap yayımlandı ve soranın e-postasına gönderildi.' : 'Cevap güncellendi.');
    }

    public function visibility(Request $request, StoreProductQuestion $storeProductQuestion): RedirectResponse
    {
        $hidden = $request->boolean('hidden');
        $this->questions->setHidden($storeProductQuestion, $hidden);

        return back()->with('status', $hidden ? 'Soru gizlendi; vitrinde görünmez.' : 'Soru yeniden görünür.');
    }

    public function destroy(StoreProductQuestion $storeProductQuestion): RedirectResponse
    {
        $this->questions->delete($storeProductQuestion);

        return back()->with('status', 'Soru silindi.');
    }
}
