<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\PaymentCodes\PaymentCodeService;
use App\Domain\Store\PaymentCodes\PaymentCodeSettings;
use App\Http\Controllers\Controller;
use App\Models\Store\StorePaymentCode;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Özel ödeme kodları: oluşturma, iptal ve "Aradığınızı bulamadınız mı?" kutusu ayarları. */
class PaymentCodeController extends Controller
{
    public function __construct(private PaymentCodeService $codes, private PaymentCodeSettings $settings) {}

    public function index(Request $request): View
    {
        $filter = $request->validate(['durum' => ['nullable', Rule::in(array_keys(StorePaymentCode::STATES))], 'q' => ['nullable', 'string', 'max:60']]);
        $state = $filter['durum'] ?? null;
        $term = trim((string) ($filter['q'] ?? ''));

        $query = StorePaymentCode::with(['order', 'creator'])->latest('id');
        if ($term !== '') {
            $code = PaymentCodeService::normalize($term);
            $query->where(fn ($q) => $q->where('title', 'like', '%'.addcslashes($term, '%_\\').'%')->when($code, fn ($w) => $w->orWhere('code', $code)));
        }
        if ($state) {
            $released = StorePaymentCode::RELEASING_ORDER_STATUSES;
            $live = fn ($q) => $q->whereNotNull('order_id')->whereHas('order', fn ($o) => $o->whereNotIn('status', $released));
            $notLive = fn ($q) => $q->where(fn ($w) => $w->whereNull('order_id')->orWhereHas('order', fn ($o) => $o->whereIn('status', $released)));
            match ($state) {
                'used' => $query->where($live),
                'cancelled' => $query->where($notLive)->where('is_cancelled', true),
                'expired' => $query->where($notLive)->where('is_cancelled', false)->whereNotNull('expires_at')->where('expires_at', '<', now()),
                'active' => $query->where($notLive)->where('is_cancelled', false)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now())),
            };
        }

        return view('store.admin.payment-codes.index', [
            'codes' => $query->paginate(30)->withQueryString(),
            'state' => $state,
            'term' => $term,
            's' => $this->settings->all(),
            'created' => ($id = session('created_code')) ? StorePaymentCode::find($id) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $code = $this->codes->create($request->only(['title', 'amount', 'vat_rate', 'days', 'note']), $request->user('store_admin')?->id);

        return redirect()->route('store_admin.payment-codes.index')->with('created_code', $code->id)
            ->with('status', "Ödeme kodu oluşturuldu: {$code->display()}");
    }

    public function cancel(StorePaymentCode $storePaymentCode): RedirectResponse
    {
        try {
            $this->codes->cancel($storePaymentCode);
        } catch (DomainException $e) {
            return back()->withErrors(['payment_code' => $e->getMessage()]);
        }

        return back()->with('status', "{$storePaymentCode->display()} kodu iptal edildi.");
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->settings->save($request->only(['enabled', 'item_name', 'box_enabled', 'box_title', 'box_text', 'code_hint']));

        return redirect()->to(route('store_admin.payment-codes.index').'#ayarlar')->with('status', 'Ayarlar kaydedildi.');
    }
}
