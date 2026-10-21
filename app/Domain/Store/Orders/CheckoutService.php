<?php

namespace App\Domain\Store\Orders;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Area;
use App\Domain\Store\Cart\CartLine;
use App\Domain\Store\Cart\CartSummary;
use App\Domain\Store\Customers\AddressService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Payments\IyzicoClient;
use App\Domain\Support\Decimal;
use App\Models\Store\District;
use App\Models\Store\Province;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreCustomerAddress;
use App\Models\Store\StoreLegalDocument;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ödeme sayfası: girdiyi doğrular, tutarları sunucuda yeniden hesaplar ve siparişi oluşturur.
 * Tarayıcıdan gelen fiyat veya tutar kullanılmaz (CLAUDE.md). Adres, fatura ve fiyatlar siparişe kopyalanır.
 *  - Kart: stok 20 dakika ayrılır, sipariş "ödeme bekleniyor" olur, ödeme iyzico'da alınır.
 *  - Kapıda: SMS doğrulamasından sonra stok hemen satışa düşer; sipariş "onay bekliyor" veya "hazırlanıyor" olur.
 */
class CheckoutService
{
    public const HOLD_MINUTES = 20;

    /** Teslimat ve kapıda ödeme bedeline uygulanan KDV oranı. */
    public const SERVICE_VAT_RATE = '20';

    public function __construct(
        private DeliveryRules $delivery,
        private CodPolicy $cod,
        private LegalDocuments $legal,
        private StoreStockService $stock,
        private IyzicoClient $iyzico,
        private OrderService $orders,
        private OrderMailer $mailer,
        private AuditLogger $audit,
    ) {}

    public function cardAvailable(): bool
    {
        return StoreSetting::flag('card_enabled') && $this->iyzico->isConfigured();
    }

    /**
     * @return array<string, mixed> doğrulanmış sipariş taslağı
     *
     * @throws ValidationException|DomainException
     */
    public function validate(array $input, ?StoreCustomer $customer, CartSummary $summary): array
    {
        if ($summary->isEmpty()) {
            throw new DomainException('Sepetiniz boş.');
        }
        if ($summary->hasIssues()) {
            throw new DomainException('Sepetinizdeki bazı ürünlerin stok durumu değişti. Sepetinizi kontrol edin.');
        }

        $errors = [];

        // İletişim
        $email = $customer?->email;
        if (! $customer) {
            $email = Str::lower(trim((string) ($input['email'] ?? '')));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
                $errors['email'] = 'Geçerli bir e-posta adresi girin; sipariş bilgileri bu adrese gönderilir.';
            }
        }

        // Teslimat adresi: kayıtlı adres veya yeni adres
        $shipping = null;
        $saveAddress = null;
        if ($customer && filled($input['address_id'] ?? null)) {
            $address = StoreCustomerAddress::where('customer_id', $customer->id)->find((int) $input['address_id']);
            if (! $address) {
                $errors['address_id'] = 'Teslimat adresi seçin.';
            } else {
                $shipping = $address->only(['first_name', 'last_name', 'phone', 'province_id', 'district_id', 'neighborhood', 'address_line', 'postal_code']);
            }
        } else {
            try {
                $shipping = AddressService::validate(['title' => 'Teslimat'] + (array) ($input['address'] ?? []));
                if ($customer && filter_var($input['save_address'] ?? false, FILTER_VALIDATE_BOOL)) {
                    $saveAddress = ['title' => trim((string) ($input['address']['title'] ?? '')) ?: 'Adresim'] + $shipping;
                }
            } catch (ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    $errors["address.{$key}"] = $messages[0];
                }
            }
        }

        // Fatura
        $billing = $this->billing($input, $errors);

        if (! filter_var($input['consent'] ?? false, FILTER_VALIDATE_BOOL)) {
            $errors['consent'] = 'Siparişi tamamlamak için Ön Bilgilendirme Formu ve Mesafeli Satış Sözleşmesi\'ni onaylayın.';
        }
        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > 500) {
            $errors['note'] = 'Sipariş notu en fazla 500 karakter olabilir.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $province = Province::findOrFail($shipping['province_id']);
        $district = District::findOrFail($shipping['district_id']);
        $subtotal = $summary->subtotal();

        // Teslimat yöntemi
        $options = $this->delivery->options($province->id, $district->id, $subtotal);
        $deliveryMethod = (string) ($input['delivery_method'] ?? '');
        if (! array_key_exists($deliveryMethod, $options)) {
            throw ValidationException::withMessages(['delivery_method' => 'Bu adres için geçerli bir teslimat yöntemi seçin.']);
        }
        $shippingFee = $options[$deliveryMethod];

        // Ödeme yöntemi
        $paymentMethod = (string) ($input['payment_method'] ?? '');
        $codFee = '0.00';
        if ($paymentMethod === 'card') {
            if (! $this->cardAvailable()) {
                throw ValidationException::withMessages(['payment_method' => 'Kartla ödeme şu anda kullanılamıyor.']);
            }
        } elseif ($paymentMethod === 'cod') {
            $codFee = $this->cod->fee();
            $total = bcadd(bcadd($subtotal, $shippingFee, 2), $codFee, 2);
            if ($refusal = $this->cod->refusal($deliveryMethod, $province->id, $district->id, $total, $shipping['phone'])) {
                throw ValidationException::withMessages(['payment_method' => $refusal]);
            }
        } else {
            throw ValidationException::withMessages(['payment_method' => 'Ödeme yöntemi seçin.']);
        }

        $lines = array_map(fn (CartLine $l) => [
            'variant_id' => $l->variant->id,
            'product_id' => $l->product->id,
            'product_name' => $l->product->name,
            'option_label' => $l->variant->option_value ? ($l->product->variant_name.': '.$l->variant->option_value) : null,
            'sku' => $l->variant->sku,
            'unit_price' => $l->unitPrice(),
            'vat_rate' => (string) $l->variant->vat_rate,
            'quantity' => $l->quantity(),
            'line_total' => $l->total(),
            'vat_amount' => $l->vat(),
        ], $summary->orderable());

        $total = bcadd(bcadd($subtotal, $shippingFee, 2), $codFee, 2);
        $vatTotal = array_reduce($lines, fn ($s, $l) => bcadd($s, $l['vat_amount'], 2), '0.00');
        $vatTotal = bcadd($vatTotal, $this->includedVat(bcadd($shippingFee, $codFee, 2)), 2);

        $draft = [
            'customer_id' => $customer?->id,
            'email' => $email,
            'phone' => $shipping['phone'],
            'shipping' => [
                'name' => trim($shipping['first_name'].' '.$shipping['last_name']),
                'phone' => $shipping['phone'],
                'province_id' => $province->id, 'district_id' => $district->id,
                'province' => $province->name, 'district' => $district->name,
                'neighborhood' => $shipping['neighborhood'] ?? null,
                'address' => $shipping['address_line'],
                'postal_code' => $shipping['postal_code'] ?? null,
            ],
            'save_address' => $saveAddress,
            'billing' => $billing + ['name_fallback' => trim($shipping['first_name'].' '.$shipping['last_name'])],
            'delivery_method' => $deliveryMethod,
            'payment_method' => $paymentMethod,
            'note' => $note ?: null,
            'lines' => $lines,
            'subtotal' => $subtotal,
            'shipping_fee' => $shippingFee,
            'cod_fee' => $codFee,
            'total' => $total,
            'vat_total' => $vatTotal,
        ];
        $draft['fingerprint'] = hash('sha256', json_encode([$draft['lines'], $draft['total'], $draft['shipping'], $paymentMethod, $deliveryMethod]));

        return $draft;
    }

    /**
     * Siparişi oluşturur. Kartta stok ayrılır; kapıda ödemede stok satışa düşer ve sepet boşaltılır.
     *
     * @throws DomainException (stok yetersizse InsufficientStoreStock)
     */
    public function place(array $draft, ?StoreCustomer $customer, StoreCart $cart, ?string $ip, ?string $userAgent): StoreOrder
    {
        $order = DB::transaction(function () use ($draft, $customer, $cart, $ip, $userAgent) {
            $shippingAddressText = implode(', ', array_filter([$draft['shipping']['neighborhood'], $draft['shipping']['address']]))
                .' — '.$draft['shipping']['district'].' / '.$draft['shipping']['province'];
            $billingName = $draft['billing']['type'] === 'corporate' ? $draft['billing']['name'] : $draft['billing']['name_fallback'];

            $isCod = $draft['payment_method'] === 'cod';
            $needsApproval = $isCod && $this->cod->needsApproval($draft['phone']);

            $order = StoreOrder::create([
                'order_no' => 'TMP-'.Str::random(12),
                'access_token' => Str::random(40),
                'customer_id' => $customer?->id,
                'email' => $draft['email'],
                'phone' => $draft['phone'],
                'status' => $isCod ? ($needsApproval ? 'awaiting_approval' : 'preparing') : 'pending_payment',
                'payment_method' => $draft['payment_method'],
                'payment_status' => $isCod ? 'cod_pending' : 'pending',
                'delivery_method' => $draft['delivery_method'],
                'shipping_name' => $draft['shipping']['name'],
                'shipping_phone' => $draft['shipping']['phone'],
                'shipping_province_id' => $draft['shipping']['province_id'],
                'shipping_district_id' => $draft['shipping']['district_id'],
                'shipping_province' => $draft['shipping']['province'],
                'shipping_district' => $draft['shipping']['district'],
                'shipping_neighborhood' => $draft['shipping']['neighborhood'],
                'shipping_address' => $draft['shipping']['address'],
                'shipping_postal_code' => $draft['shipping']['postal_code'],
                'billing_type' => $draft['billing']['type'],
                'billing_name' => $billingName,
                'billing_tax_office' => $draft['billing']['tax_office'],
                'billing_tax_number' => $draft['billing']['tax_number'],
                'billing_address' => $draft['billing']['address'] ?? $shippingAddressText,
                'subtotal' => $draft['subtotal'],
                'shipping_fee' => $draft['shipping_fee'],
                'cod_fee' => $draft['cod_fee'],
                'total' => $draft['total'],
                'vat_total' => $draft['vat_total'],
                'customer_note' => $draft['note'],
                'approved_at' => $isCod && ! $needsApproval ? now() : null,
                'ip' => $ip,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
            ]);
            $order->forceFill(['order_no' => 'YB'.(100000 + $order->id)])->save();
            $order->items()->createMany($draft['lines']);

            $quantities = [];
            foreach ($draft['lines'] as $line) {
                $quantities[$line['variant_id']] = ($quantities[$line['variant_id']] ?? 0) + $line['quantity'];
            }

            if ($isCod) {
                $this->stock->sell($quantities, "order:{$order->id}", 'store_order', $order->id);
                $order->payments()->create(['driver' => 'cod', 'status' => 'initialized', 'amount' => $order->total]);
                $cart->items()->delete();
            } else {
                $holder = "order:{$order->id}";
                $this->stock->hold($holder, $quantities, self::HOLD_MINUTES);
                $order->forceFill(['stock_holder' => $holder, 'cart_id' => $cart->id])->save();
            }

            $this->recordConsents($order, $draft, $shippingAddressText, $ip);
            $this->orders->event($order, $order->status, $isCod ? 'Kapıda ödemeli sipariş oluşturuldu (SMS ile doğrulandı).' : 'Sipariş oluşturuldu; ödeme bekleniyor.');

            if ($draft['save_address'] && $customer) {
                try {
                    app(AddressService::class)->create($customer, $draft['save_address']);
                } catch (DomainException) {
                    // Adres defteri doluysa adres kaydedilmez; sipariş etkilenmez.
                }
            }
            $this->audit->log('store.order_placed', $order, [], ['order_no' => $order->order_no, 'total' => $order->total, 'payment' => $order->payment_method],
                actorArea: $customer ? Area::Customer : null, actorId: $customer?->id);

            return $order;
        });

        if ($order->payment_method === 'cod') {
            $this->mailer->placed($order);
        }

        return $order;
    }

    /** Fiyata dahil KDV (hizmet bedelleri için %20). */
    public function includedVat(string $amount): string
    {
        $rate = self::SERVICE_VAT_RATE;

        return Decimal::round(bcdiv(bcmul($amount, $rate, 6), bcadd('100', $rate, 6), 6), 2);
    }

    /**
     * Ödeme sayfasında gösterilecek doldurulmuş yasal metinler (taslak üzerinden).
     *
     * @return array<string, array{document: StoreLegalDocument, body: string}>
     */
    public function legalPreview(array $draft): array
    {
        $out = [];
        $address = implode(', ', array_filter([$draft['shipping']['neighborhood'], $draft['shipping']['address']])).' — '.$draft['shipping']['district'].' / '.$draft['shipping']['province'];
        foreach (array_keys(StoreLegalDocument::KEYS) as $key) {
            $doc = StoreLegalDocument::current($key);
            $out[$key] = ['document' => $doc, 'body' => $this->legal->fill($doc, $this->legalData($draft, $address))];
        }

        return $out;
    }

    private function recordConsents(StoreOrder $order, array $draft, string $address, ?string $ip): void
    {
        foreach ($this->legalPreview($draft) as $key => ['document' => $doc, 'body' => $body]) {
            $order->consents()->create(['document_key' => $key, 'version' => $doc->version, 'body' => $body, 'ip' => $ip, 'accepted_at' => now()]);
        }
    }

    private function legalData(array $draft, string $address): array
    {
        return [
            'name' => $draft['shipping']['name'],
            'address' => $address,
            'phone' => $draft['shipping']['phone'],
            'email' => $draft['email'],
            'lines' => array_map(fn ($l) => ['name' => $l['product_name'].($l['option_label'] ? " ({$l['option_label']})" : ''), 'quantity' => $l['quantity'], 'total' => $l['line_total']], $draft['lines']),
            'shipping_fee' => $draft['shipping_fee'],
            'cod_fee' => $draft['cod_fee'],
            'total' => $draft['total'],
            'payment' => StoreOrder::PAYMENT_METHODS[$draft['payment_method']],
            'delivery' => StoreOrder::DELIVERY_METHODS[$draft['delivery_method']],
        ];
    }

    /** @return array{type: string, name: ?string, tax_office: ?string, tax_number: ?string, address: ?string} */
    private function billing(array $input, array &$errors): array
    {
        $type = ($input['billing_type'] ?? 'individual') === 'corporate' ? 'corporate' : 'individual';
        $address = filter_var($input['billing_same'] ?? true, FILTER_VALIDATE_BOOL) ? null : trim((string) ($input['billing_address'] ?? ''));
        if ($address !== null && (mb_strlen($address) < 10 || mb_strlen($address) > 500)) {
            $errors['billing_address'] = 'Fatura adresini açık olarak yazın.';
        }

        if ($type === 'individual') {
            $tckn = preg_replace('/\D/', '', (string) ($input['billing_tckn'] ?? ''));
            if ($tckn !== '' && ! self::validTckn($tckn)) {
                $errors['billing_tckn'] = 'Geçerli bir T.C. kimlik numarası girin veya boş bırakın.';
            }

            return ['type' => 'individual', 'name' => null, 'tax_office' => null, 'tax_number' => $tckn ?: null, 'address' => $address];
        }

        $data = Validator::make($input, [
            'billing_company' => ['required', 'string', 'max:160'],
            'billing_tax_office' => ['required', 'string', 'max:80'],
            'billing_tax_number' => ['required', 'regex:/^\d{10}$/'],
        ], ['billing_tax_number.regex' => 'Vergi numarası 10 haneli olmalıdır.'], [
            'billing_company' => 'firma unvanı', 'billing_tax_office' => 'vergi dairesi', 'billing_tax_number' => 'vergi numarası',
        ]);
        foreach ($data->errors()->messages() as $key => $messages) {
            $errors[$key] = $messages[0];
        }

        return ['type' => 'corporate', 'name' => trim((string) ($input['billing_company'] ?? '')), 'tax_office' => trim((string) ($input['billing_tax_office'] ?? '')),
            'tax_number' => preg_replace('/\D/', '', (string) ($input['billing_tax_number'] ?? '')), 'address' => $address];
    }

    /** T.C. kimlik numarası algoritması. */
    public static function validTckn(string $n): bool
    {
        if (! preg_match('/^[1-9]\d{10}$/', $n)) {
            return false;
        }
        $d = array_map('intval', str_split($n));
        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];

        return (($odd * 7 - $even) % 10 + 10) % 10 === $d[9] && array_sum(array_slice($d, 0, 10)) % 10 === $d[10];
    }
}
