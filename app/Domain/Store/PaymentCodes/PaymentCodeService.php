<?php

namespace App\Domain\Store\PaymentCodes;

use App\Domain\Audit\AuditLogger;
use App\Domain\Store\Cart\PaymentCodeLine;
use App\Domain\Support\Decimal;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreCartItem;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreOrderItem;
use App\Models\Store\StorePaymentCode;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Özel ödeme kodları. Güvenlik:
 *  - Tutarı yalnızca yönetici belirler; müşteri yalnızca kodu yazar, tutar her zaman kodun kaydından gelir.
 *  - Kod 8 karakterlik rastgele dizidir (31 harf/rakam, karışan 0/O, 1/I/L yok); yalnızca tam eşleşmede tanınır.
 *  - Tek kullanımlıktır: sipariş oluşurken kod satırı kilitlenip yeniden denetlenir ve siparişe bağlanır (eşzamanlı iki
 *    siparişten yalnızca biri geçer). Sipariş iptal edilir veya ödemesi başarısız olursa kod yeniden kullanılabilir.
 */
class PaymentCodeService
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public const LENGTH = 8;

    public const VAT_RATES = ['20' => '%20', '10' => '%10', '1' => '%1', '0' => '%0'];

    public const MAX_AMOUNT = '1000000.00';

    public function __construct(private PaymentCodeSettings $settings, private AuditLogger $audit) {}

    /** Arama kutusundaki yazıyı koda çevirir ("abcd-efgh" → "ABCDEFGH"); kod biçiminde değilse null. */
    public static function normalize(?string $input): ?string
    {
        $code = strtoupper((string) preg_replace('/[\s\-_.]+/', '', trim((string) $input)));

        return strlen($code) === self::LENGTH && strspn($code, self::ALPHABET) === self::LENGTH ? $code : null;
    }

    /** Yazılan kodun kaydı (kullanılabilir olsun olmasın); kodla ödeme kapalıysa veya kod biçiminde değilse null. */
    public function find(?string $input): ?StorePaymentCode
    {
        $code = self::normalize($input);

        return $code !== null && $this->settings->enabled() ? StorePaymentCode::with('order')->where('code', $code)->first() : null;
    }

    /** @throws ValidationException */
    public function create(array $input, ?int $adminId): StorePaymentCode
    {
        $errors = [];
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 150) {
            $errors['title'] = 'Müşterinin göreceği açıklamayı yazın (en fazla 150 karakter). Örn. 500 sayfa fotokopi, özel fiyat.';
        }
        $amount = Decimal::parse($input['amount'] ?? null, 2);
        if ($amount === null || bccomp($amount, '0', 2) <= 0 || bccomp($amount, self::MAX_AMOUNT, 2) > 0) {
            $errors['amount'] = 'Geçerli bir tutar girin (ör. 450,00).';
        }
        $vat = (string) ($input['vat_rate'] ?? '20');
        if (! isset(self::VAT_RATES[$vat])) {
            $errors['vat_rate'] = 'KDV oranı seçin.';
        }
        $days = $input['days'] ?? null;
        if (filled($days) && filter_var($days, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]) === false) {
            $errors['days'] = 'Geçerlilik 1 ile 365 gün arasında olmalıdır; süresiz için boş bırakın.';
        }
        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > 500) {
            $errors['note'] = 'Not en fazla 500 karakter olabilir.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $code = StorePaymentCode::create([
            'code' => $this->uniqueCode(),
            'title' => $title,
            'amount' => $amount,
            'vat_rate' => $vat,
            'note' => $note ?: null,
            'expires_at' => filled($days) ? now()->addDays((int) $days)->endOfDay() : null,
            'created_by' => $adminId,
        ]);
        $this->audit->log('store.payment_code_created', $code, [], ['code' => $code->code, 'title' => $title, 'amount' => $amount]);

        return $code;
    }

    /** @throws DomainException */
    public function cancel(StorePaymentCode $code): void
    {
        DB::transaction(function () use ($code) {
            $code = StorePaymentCode::whereKey($code->id)->lockForUpdate()->firstOrFail();
            if ($code->liveOrder()) {
                throw new DomainException('Bu kod bir siparişte kullanıldı; iptal etmek için siparişi iptal edin.');
            }
            if ($code->is_cancelled) {
                return;
            }
            $code->forceFill(['is_cancelled' => true])->save();
            $this->audit->log('store.payment_code_cancelled', $code, [], ['code' => $code->code]);
        });
    }

    /** Pasif kodu yeniden kullanıma açar (süresi dolmuşsa "süresi doldu" olarak kalır). */
    public function reactivate(StorePaymentCode $code): void
    {
        DB::transaction(function () use ($code) {
            $code = StorePaymentCode::whereKey($code->id)->lockForUpdate()->firstOrFail();
            if (! $code->is_cancelled) {
                return;
            }
            $code->forceFill(['is_cancelled' => false])->save();
            $this->audit->log('store.payment_code_reactivated', $code, [], ['code' => $code->code]);
        });
    }

    /** Hiçbir siparişe girmemiş kod silinebilir; siparişte (iptal edilmiş olsa da) geçen kod kayıt için kalır. */
    public static function deletable(StorePaymentCode $code): bool
    {
        return $code->order_id === null && ! StoreOrderItem::where('payment_code_id', $code->id)->exists();
    }

    /**
     * Kodu tamamen siler; müşterilerin sepetlerindeki satırı da kalkar.
     *
     * @throws DomainException
     */
    public function delete(StorePaymentCode $code): void
    {
        DB::transaction(function () use ($code) {
            $code = StorePaymentCode::whereKey($code->id)->lockForUpdate()->firstOrFail();
            if (! self::deletable($code)) {
                throw new DomainException('Bu kod bir siparişte kullanıldığı için silinemez; sipariş ve fatura kaydında görünür. Kullanılmasını engellemek için pasif edin.');
            }
            StoreCartItem::where('payment_code_id', $code->id)->delete();
            $this->audit->log('store.payment_code_deleted', null, ['code' => $code->code, 'title' => $code->title, 'amount' => (string) $code->amount], []);
            $code->delete();
        });
    }

    /**
     * Kodu sepete ekler (aynı kod sepette varsa yeniden eklenmez).
     *
     * @throws DomainException
     */
    public function addToCart(StoreCart $cart, ?string $input): StorePaymentCode
    {
        $code = $this->find($input);
        if (! $code) {
            throw new DomainException('Ödeme kodu bulunamadı. Kodu kontrol edin veya mağazamızla iletişime geçin.');
        }
        $this->assertUsable($code);
        StoreCartItem::firstOrCreate(['cart_id' => $cart->id, 'payment_code_id' => $code->id], ['variant_id' => null, 'quantity' => 1]);
        $cart->touch();

        return $code;
    }

    /**
     * Sepetteki kod satırları; kullanılamayan kod işaretlenir ve toplama girmez.
     *
     * @param  Collection<int, StoreCartItem>  $items
     * @return list<PaymentCodeLine>
     */
    public function lines(Collection $items): array
    {
        $enabled = $this->settings->enabled();
        $name = $this->settings->get('item_name');

        return $items->map(fn (StoreCartItem $item) => new PaymentCodeLine($item, $item->paymentCode, $name,
            $enabled && $item->paymentCode->isUsable() ? null : 'unavailable'))->values()->all();
    }

    /**
     * Sipariş oluşurken (işlem içinde) kodları kilitler, yeniden denetler ve siparişe bağlar.
     *
     * @param  list<int>  $ids
     *
     * @throws DomainException
     */
    public function claim(array $ids, StoreOrder $order, array $amounts): void
    {
        if ($ids === []) {
            return;
        }
        $codes = StorePaymentCode::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($ids as $id) {
            $code = $codes[$id] ?? null;
            if (! $code || ! $this->settings->enabled() || ! $code->isUsable() || bccomp((string) $code->amount, (string) ($amounts[$id] ?? ''), 2) !== 0) {
                throw new DomainException('Sepetinizdeki ödeme kodu artık geçerli değil. Sepetinizi kontrol edin.');
            }
            $code->forceFill(['order_id' => $order->id])->save();
        }
    }

    /** @throws DomainException */
    private function assertUsable(StorePaymentCode $code): void
    {
        $message = match ($code->state()) {
            'used' => 'Bu ödeme kodu kullanıldı.',
            'cancelled' => 'Bu ödeme kodu şu anda kullanıma kapalı.',
            'expired' => 'Bu ödeme kodunun süresi doldu.',
            default => null,
        };
        if ($message) {
            throw new DomainException($message.' Yeni bir kod için mağazamızla iletişime geçin.');
        }
    }

    private function uniqueCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (StorePaymentCode::where('code', $code)->exists());

        return $code;
    }
}
