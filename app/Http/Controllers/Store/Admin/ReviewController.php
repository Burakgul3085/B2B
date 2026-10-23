<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Reviews\ReviewService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Katalog → Yorumlar: onay bekleyen, yayındaki ve yayımlanmayan ürün yorumları. */
class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(array_keys(StoreReview::STATUSES))]])['status'] ?? 'pending';

        return view('store.admin.reviews.index', [
            'reviews' => StoreReview::with('product:id,name,slug')->where('status', $status)->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'counts' => StoreReview::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function update(Request $request, StoreReview $storeReview): RedirectResponse
    {
        $this->reviews->moderate($storeReview, (string) $request->input('status'), $request->input('reply'));

        return back()->with('status', $storeReview->status === 'approved' ? 'Yorum yayımlandı.' : 'Yorum yayımlanmadı olarak işaretlendi.');
    }

    public function destroy(StoreReview $storeReview): RedirectResponse
    {
        $this->reviews->delete($storeReview);

        return back()->with('status', 'Yorum silindi.');
    }
}
