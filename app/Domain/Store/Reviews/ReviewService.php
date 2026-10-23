<?php

namespace App\Domain\Store\Reviews;

use App\Domain\Audit\AuditLogger;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreOrderItem;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreReview;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Ürün yorumları. Yalnızca ürünü teslim almış üye yazabilir (her ürüne bir yorum); yorum yönetici onayından
 * sonra yayımlanır. Puan ortalaması yalnızca onaylı yorumlardan hesaplanır, başka hiçbir yerden puan gösterilmez.
 */
class ReviewService
{
    public function __construct(private AuditLogger $audit) {}

    /** guest | not_purchased | reviewed | allowed */
    public function eligibility(?StoreCustomer $customer, StoreProduct $product): string
    {
        if (! $customer) {
            return 'guest';
        }
        if (StoreReview::where('customer_id', $customer->id)->where('product_id', $product->id)->exists()) {
            return 'reviewed';
        }

        return $this->hasReceived($customer, $product) ? 'allowed' : 'not_purchased';
    }

    public function hasReceived(StoreCustomer $customer, StoreProduct $product): bool
    {
        return StoreOrderItem::query()
            ->join('store_orders', 'store_orders.id', '=', 'store_order_items.order_id')
            ->where('store_orders.customer_id', $customer->id)
            ->where('store_orders.status', 'delivered')
            ->where('store_order_items.product_id', $product->id)
            ->exists();
    }

    /** @throws ValidationException */
    public function submit(StoreCustomer $customer, StoreProduct $product, array $input): StoreReview
    {
        $data = Validator::make($input, [
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:80'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ], ['rating.required' => 'Puan seçin.'], ['rating' => 'puan', 'title' => 'başlık', 'body' => 'yorum'])->validate();

        $status = $this->eligibility($customer, $product);
        if ($status !== 'allowed') {
            throw ValidationException::withMessages(['body' => $status === 'reviewed'
                ? 'Bu ürün için zaten yorum yazdınız.'
                : 'Yalnızca bu ürünü satın alıp teslim almış müşterilerimiz yorum yazabilir.']);
        }

        return StoreReview::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'author_name' => self::displayName($customer),
            'rating' => (int) $data['rating'],
            'title' => trim((string) ($data['title'] ?? '')) ?: null,
            'body' => trim($data['body']),
        ]);
    }

    /** "Ayşe Kaya" → "Ayşe K." */
    public static function displayName(StoreCustomer $customer): string
    {
        $last = trim((string) $customer->last_name);

        return trim($customer->first_name.($last !== '' ? ' '.mb_strtoupper(mb_substr($last, 0, 1)).'.' : ''));
    }

    /** @throws ValidationException */
    public function moderate(StoreReview $review, string $status, ?string $reply): void
    {
        Validator::make(['status' => $status, 'reply' => $reply], [
            'status' => ['required', 'in:approved,rejected'],
            'reply' => ['nullable', 'string', 'max:1000'],
        ], [], ['reply' => 'mağaza yanıtı'])->validate();

        $old = $review->only(['status', 'reply']);
        $review->update(['status' => $status, 'reply' => trim((string) $reply) ?: null, 'moderated_at' => now()]);
        $this->audit->log('store.review_moderated', $review, $old, $review->only(['status', 'reply']));
    }

    public function delete(StoreReview $review): void
    {
        $this->audit->log('store.review_deleted', $review, $review->only(['product_id', 'rating', 'status']));
        $review->delete();
    }

    /** @return array{average: ?float, count: int, bars: array<int, int>} yıldıza göre dağılım (5→1) */
    public static function summary(StoreProduct $product): array
    {
        $counts = StoreReview::approved()->where('product_id', $product->id)->groupBy('rating')->selectRaw('rating, COUNT(*) as c')->pluck('c', 'rating');
        $total = (int) $counts->sum();
        $bars = [];
        foreach ([5, 4, 3, 2, 1] as $star) {
            $bars[$star] = (int) ($counts[$star] ?? 0);
        }

        return [
            'average' => $total ? round($counts->map(fn ($c, $r) => $c * $r)->sum() / $total, 1) : null,
            'count' => $total,
            'bars' => $bars,
        ];
    }
}
