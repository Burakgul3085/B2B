<?php

namespace App\Domain\Invoicing;

use App\Domain\Audit\AuditLogger;
use App\Domain\Invoicing\Providers\DenemeProvider;
use App\Domain\Invoicing\Providers\InvoiceProvider;
use App\Domain\Invoicing\Providers\UyumsoftProvider;
use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use App\Models\InvoiceSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Toptancının fatura ayarları (Toptancı → Ayarlar → Fatura Ayarları): sağlayıcı (deneme / Uyumsoft), ortam, web servis
 * kullanıcısı, şifreli parola, gönderici kimliği, bayi faturası serileri, teslimat kaleminin KDV oranı, otomatik kesim.
 *
 * Güvenlik: parola şifreli saklanır, ekrana ve işlem geçmişine yazılmaz; boş bırakılırsa korunur. Otomatik kesim
 * açıldığı andan SONRA alınan ödemelere uygulanır (auto_since); geçmiş siparişler kendiliğinden faturalanmaz.
 */
class InvoiceSettings
{
    public const VAT_RATES = ['20', '10', '1', '0'];

    public function __construct(private TenantContext $tenant, private AuditLogger $audit) {}

    public function record(): InvoiceSetting
    {
        return InvoiceSetting::firstOrCreate([], ['driver' => 'deneme', 'uyumsoft_mode' => 'test', 'delivery_vat' => '20.00', 'auto_issue' => false, 'notify_dealer' => true]);
    }

    public function provider(): InvoiceProvider
    {
        return $this->record()->driver === 'uyumsoft' ? app(UyumsoftProvider::class) : app(DenemeProvider::class);
    }

    /** Uyumsoft parolası (yalnızca sağlayıcı isteğinde kullanılır). */
    public function password(): ?string
    {
        $value = $this->record()->getRawOriginal('uyumsoft_password');
        if (blank($value)) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    public function hasPassword(): bool
    {
        return filled($this->password());
    }

    /**
     * Faturadaki satıcı: işletme kaydındaki unvan, adres, vergi bilgileri; ayarda verilmişse satıcı adı, vergi dairesi
     * ve gönderici kimliği bunların yerine geçer.
     *
     * @return array{id: string, name: string, tax_office: string, address: string, district: ?string, city: string, phone: ?string, email: ?string, web: string}
     */
    public function seller(): array
    {
        $s = $this->record();
        $b = Business::findOrFail($this->tenant->businessId());
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        return [
            'id' => (string) preg_replace('/\D/', '', (string) ($s->sender_id ?: $b->tax_number)),
            'name' => trim((string) ($s->seller_name ?: $b->legal_name)),
            'tax_office' => trim((string) ($s->tax_office ?: $b->tax_office)),
            'address' => trim((string) $b->address),
            'district' => $b->district ?: null,
            'city' => trim((string) $b->city) ?: 'Eskişehir',
            'phone' => $b->phone ?: null,
            'email' => $b->email ?: null,
            'web' => 'https://'.((function_exists('idn_to_utf8') ? idn_to_utf8($host) : false) ?: $host),
        ];
    }

    /** Satıcı bilgisinde eksik varsa nedeni (fatura gönderilmez). */
    public function sellerProblem(): ?string
    {
        $s = $this->seller();
        $missing = array_keys(array_filter([
            'vergi numarası' => ! in_array(strlen($s['id']), [10, 11], true),
            'unvan' => $s['name'] === '',
            'vergi dairesi' => $s['tax_office'] === '',
            'adres' => $s['address'] === '',
        ]));

        return $missing ? 'Faturadaki satıcı bilgisi eksik ('.implode(', ', $missing).'). İşletme Ayarları veya Fatura Ayarları\'ndan tamamlayın.' : null;
    }

    /** @throws ValidationException */
    public function save(array $input): void
    {
        // Yalnızca düz metin alan normalize edilir; dizi gibi bozuk girdi doğrulamada reddedilir.
        foreach (['series_efatura', 'series_earsiv'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $input[$key] = trim($input[$key]) !== '' ? mb_strtoupper(trim($input[$key]), 'UTF-8') : null;
            }
        }
        if (is_string($input['sender_id'] ?? null)) {
            $input['sender_id'] = trim($input['sender_id']) !== '' ? preg_replace('/\s+/', '', $input['sender_id']) : null;
        }
        $data = Validator::make($input, [
            'driver' => ['required', 'in:deneme,uyumsoft'],
            'uyumsoft_mode' => ['required', 'in:test,live'],
            'uyumsoft_username' => ['nullable', 'string', 'max:100'],
            'uyumsoft_password' => ['nullable', 'string', 'max:200'],
            'sender_id' => ['nullable', 'regex:/^(\d{10}|\d{11})$/'],
            'seller_name' => ['nullable', 'string', 'max:150'],
            'tax_office' => ['nullable', 'string', 'max:100'],
            'series_efatura' => ['nullable', 'regex:/^[A-Z0-9]{3}$/'],
            'series_earsiv' => ['nullable', 'regex:/^[A-Z0-9]{3}$/', 'different:series_efatura'],
            'delivery_vat' => ['required', 'in:'.implode(',', self::VAT_RATES)],
        ], [
            'sender_id.regex' => 'Gönderici kimliği 10 haneli VKN veya 11 haneli TCKN olmalı.',
            'series_efatura.regex' => 'Seri, Uyumsoft\'ta tanımladığınız 3 karakterlik ön ektir (büyük harf / rakam, ör. BYF).',
            'series_earsiv.regex' => 'Seri, Uyumsoft\'ta tanımladığınız 3 karakterlik ön ektir (büyük harf / rakam, ör. BYA).',
            'series_earsiv.different' => 'e-Arşiv ve e-Fatura için farklı seri kullanın.',
        ], [
            'driver' => 'sağlayıcı', 'uyumsoft_mode' => 'ortam', 'uyumsoft_username' => 'kullanıcı adı', 'uyumsoft_password' => 'şifre',
            'seller_name' => 'satıcı adı', 'tax_office' => 'vergi dairesi', 'delivery_vat' => 'teslimat KDV oranı',
        ])->validate();

        $record = $this->record();
        $username = trim((string) ($data['uyumsoft_username'] ?? '')) ?: null;
        $password = trim((string) ($data['uyumsoft_password'] ?? ''));
        if ($data['driver'] === 'uyumsoft' && (! $username || ($password === '' && ! $this->hasPassword()))) {
            throw ValidationException::withMessages(['uyumsoft_username' => 'Uyumsoft için web servis kullanıcı adı ve şifresi gerekir.']);
        }
        if ($data['driver'] === 'uyumsoft' && (blank($data['series_earsiv'] ?? null) || blank($data['series_efatura'] ?? null))) {
            // Mali müşavir kararı: bayi faturaları mağazadan ayrı seriden kesilir; seri boşsa Uyumsoft varsayılan seriyi kullanırdı.
            throw ValidationException::withMessages(['series_earsiv' => 'Uyumsoft ile kesimde bayi faturaları için e-Arşiv ve e-Fatura serisini yazın (mağazadan ayrı seri).']);
        }

        $auto = filter_var($input['auto_issue'] ?? false, FILTER_VALIDATE_BOOL);
        $before = $record->only(['driver', 'uyumsoft_mode', 'uyumsoft_username', 'sender_id', 'seller_name', 'tax_office', 'series_efatura', 'series_earsiv', 'delivery_vat', 'auto_issue', 'notify_dealer']);
        $record->fill([
            'driver' => $data['driver'],
            'uyumsoft_mode' => $data['uyumsoft_mode'],
            'uyumsoft_username' => $username,
            'sender_id' => $data['sender_id'] ?? null,
            'seller_name' => trim((string) ($data['seller_name'] ?? '')) ?: null,
            'tax_office' => trim((string) ($data['tax_office'] ?? '')) ?: null,
            'series_efatura' => $data['series_efatura'] ?? null,
            'series_earsiv' => $data['series_earsiv'] ?? null,
            'delivery_vat' => $data['delivery_vat'],
            'auto_issue' => $auto,
            // Otomatik kesim yeni açıldıysa yalnızca bundan sonraki ödemelere uygulanır.
            'auto_since' => $auto ? ($record->auto_issue ? $record->auto_since : now()) : null,
            'notify_dealer' => filter_var($input['notify_dealer'] ?? false, FILTER_VALIDATE_BOOL),
        ]);
        if ($password !== '') {
            $record->uyumsoft_password = Crypt::encryptString($password);
        }
        $record->save();

        $after = $record->only(array_keys($before));
        $changed = array_values(array_filter(array_keys($before), fn ($k) => (string) $before[$k] !== (string) $after[$k]));
        $this->audit->log('invoice.settings_updated', $record, [], ['changed' => $changed, 'password_renewed' => $password !== '']);
    }
}
