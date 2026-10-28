<?php

namespace App\Domain\Store\Invoicing;

use App\Domain\Audit\AuditLogger;
use App\Models\Store\StoreSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Fatura ayarları (Ayarlar → Fatura, yalnızca süper admin): sağlayıcı (deneme / Uyumsoft), ortam, kullanıcı bilgileri,
 * gönderici kimliği ve varsayılanlar. Şifre şifreli saklanır, ekrana geri yazılmaz; boş bırakılırsa korunur.
 */
class EInvoiceSettings
{
    public function __construct(private AuditLogger $audit) {}

    /** @throws ValidationException */
    public function save(array $input): void
    {
        $data = Validator::make($input, [
            'invoice_driver' => ['required', 'in:deneme,uyumsoft'],
            'uyumsoft_mode' => ['required', 'in:test,live'],
            'uyumsoft_username' => ['nullable', 'string', 'max:100'],
            'uyumsoft_password' => ['nullable', 'string', 'max:200'],
            'invoice_sender_id' => ['nullable', 'regex:/^(\d{10}|\d{11})$/'],
            'invoice_shipping_vat' => ['required', 'in:'.implode(',', InvoiceMath::VAT_RATES)],
        ], ['invoice_sender_id.regex' => 'Gönderici kimliği 10 haneli VKN veya 11 haneli TCKN olmalı.'], [
            'invoice_driver' => 'sağlayıcı', 'uyumsoft_mode' => 'ortam', 'uyumsoft_username' => 'kullanıcı adı', 'uyumsoft_password' => 'şifre',
            'invoice_shipping_vat' => 'kargo KDV oranı',
        ])->validate();

        $username = trim((string) ($data['uyumsoft_username'] ?? '')) ?: null;
        $hasPassword = filled($data['uyumsoft_password'] ?? null) || filled(StoreSetting::secret('uyumsoft_password'));
        if ($data['invoice_driver'] === 'uyumsoft' && (! $username || ! $hasPassword)) {
            throw ValidationException::withMessages(['uyumsoft_username' => 'Uyumsoft için kullanıcı adı ve şifre gerekir.']);
        }

        $old = StoreSetting::values();
        StoreSetting::put([
            'invoice_driver' => $data['invoice_driver'],
            'uyumsoft_mode' => $data['uyumsoft_mode'],
            'uyumsoft_username' => $username,
            'invoice_sender_id' => $data['invoice_sender_id'] ?? null,
            'invoice_shipping_vat' => $data['invoice_shipping_vat'],
            'invoice_notify' => filter_var($input['invoice_notify'] ?? false, FILTER_VALIDATE_BOOL) ? '1' : '0',
        ]);
        if (filled($data['uyumsoft_password'] ?? null)) {
            StoreSetting::putSecret('uyumsoft_password', trim($data['uyumsoft_password']));
        }

        $new = StoreSetting::values();
        $keys = ['invoice_driver', 'uyumsoft_mode', 'uyumsoft_username', 'invoice_sender_id', 'invoice_shipping_vat', 'invoice_notify'];
        $changed = array_values(array_filter($keys, fn ($k) => (string) $new[$k] !== (string) ($old[$k] ?? '')));
        $this->audit->log('store.invoice_settings_updated', null, [], ['changed' => $changed, 'password_renewed' => filled($data['uyumsoft_password'] ?? null)]);
    }
}
