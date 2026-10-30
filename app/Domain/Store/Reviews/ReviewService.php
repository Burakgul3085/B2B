<?php

namespace App\Domain\Store\Reviews;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\ImageUploader;
use App\Domain\Store\Notifications\AdminNotifier;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreOrderItem;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreReview;
use App\Models\Store\StoreReviewPhoto;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Ürün yorumları. Yalnızca ürünü teslim almış üye yazabilir (her ürüne bir yorum); yorum yönetici onayından
 * sonra yayımlanır. Puan ortalaması yalnızca onaylı yorumlardan hesaplanır, başka hiçbir yerden puan gösterilmez.
 * Yoruma en fazla 5 fotoğraf eklenebilir (yeniden kodlanır, özel diskte); vitrinde yalnızca yayındaki yorumunki görünür.
 * Üye, teslim aldığı ve henüz yorumlamadığı ürünleri Hesabım → Değerlendirmelerim'de görür.
 */
class ReviewService
{
    public function __construct(private AuditLogger $audit, private ImageUploader $images, private AdminNotifier $notifier) {}

    /**
     * Üyenin teslim aldığı, mağazada yayında olan ve henüz yorumlamadığı ürünler (en son teslim alınan önce).
     *
     * @return Collection<int, StoreProduct>
     */
    public function awaiting(StoreCustomer $customer): Collection
    {
        $ids = StoreOrderItem::query()
            ->join('store_orders', 'store_orders.id', '=', 'store_order_items.order_id')
            ->where('store_orders.customer_id', $customer->id)->where('store_orders.status', 'delivered')
            ->whereNotNull('store_order_items.product_id')
            ->whereNotIn('store_order_items.product_id', StoreReview::where('customer_id', $customer->id)->select('product_id'))
            ->groupBy('store_order_items.product_id')
            ->orderByRaw('MAX(store_orders.delivered_at) DESC')
            ->pluck('store_order_items.product_id');

        $products = StoreProduct::where('is_published', true)->whereIn('id', $ids)->with('primaryImage')->get()->keyBy('id');

        return $ids->map(fn ($id) => $products->get($id))->filter()->values();
    }

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
            'photos' => ['nullable', 'array', 'max:'.StoreReview::MAX_PHOTOS],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'rating.required' => 'Puan seçin.',
            'photos.max' => 'En fazla '.StoreReview::MAX_PHOTOS.' fotoğraf ekleyebilirsiniz.',
            'photos.*.mimes' => 'Fotoğraflar JPG, PNG veya WEBP olmalıdır.',
            'photos.*.max' => 'Her fotoğraf en fazla 10 MB olabilir.',
        ], ['rating' => 'puan', 'title' => 'başlık', 'body' => 'yorum', 'photos' => 'fotoğraflar'])->validate();

        $status = $this->eligibility($customer, $product);
        if ($status !== 'allowed') {
            throw ValidationException::withMessages(['body' => $status === 'reviewed'
                ? 'Bu ürün için zaten yorum yazdınız.'
                : 'Yalnızca bu ürünü satın alıp teslim almış müşterilerimiz yorum yazabilir.']);
        }

        $paths = [];
        try {
            foreach ($data['photos'] ?? [] as $file) {
                $paths[] = $this->images->store($file, 'store/reviews', 1600, 10 * 1024 * 1024);
            }
        } catch (DomainException $e) {
            array_map(fn ($p) => $this->images->delete($p), $paths);
            throw ValidationException::withMessages(['photos' => $e->getMessage()]);
        }

        try {
            $review = DB::transaction(function () use ($customer, $product, $data, $paths) {
                $review = StoreReview::create([
                    'product_id' => $product->id,
                    'customer_id' => $customer->id,
                    'author_name' => self::displayName($customer),
                    'rating' => (int) $data['rating'],
                    'title' => trim((string) ($data['title'] ?? '')) ?: null,
                    'body' => trim($data['body']),
                ]);
                foreach ($paths as $path) {
                    $review->photos()->create(['path' => $path, 'size' => (int) Storage::disk('local')->size($path), 'created_at' => now()]);
                }

                return $review;
            });
        } catch (\Throwable $e) {
            array_map(fn ($p) => $this->images->delete($p), $paths);
            throw $e;
        }
        $this->notifier->reviewSubmitted($review, $product);

        return $review;
    }

    /** Fotoğraf vitrinde / hesapta gösterilebilir mi: yayındaki yorum veya yorumun sahibi. */
    public static function canSeePhoto(StoreReviewPhoto $photo, ?StoreCustomer $viewer): bool
    {
        $review = $photo->review;

        return $review->status === 'approved' || ($viewer && $viewer->id === $review->customer_id);
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
        $paths = $review->photos()->pluck('path')->all();
        $review->delete();
        array_map(fn ($p) => $this->images->delete($p), $paths);
    }

    /** @return array{average: ?float, count: int, bars: array<int, int>, recommend: ?int} yıldıza göre dağılım (5→1), 4–5 yıldız oranı (%) */
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
            'recommend' => $total ? (int) round(($bars[5] + $bars[4]) / $total * 100) : null,
        ];
    }
}
