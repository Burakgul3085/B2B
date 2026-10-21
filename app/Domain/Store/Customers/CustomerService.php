<?php

namespace App\Domain\Store\Customers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Area;
use App\Domain\Auth\AuthTokenService;
use App\Models\Store\StoreCustomer;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Mağaza müşteri hesapları: üyelik, profil, e-posta ve parola. B2B hesap modeliyle aynı sade kurgu:
 * hesap hemen etkindir (doğrulama bağlantısı yok), e-posta değişikliği mevcut parolayla onaylanır.
 * Üyelikte KVKK aydınlatma metninin okunduğu onaylanır; ticari ileti izni isteğe bağlıdır.
 */
class CustomerService
{
    public function __construct(private AuditLogger $audit, private AuthTokenService $tokens) {}

    /** @throws ValidationException */
    public function register(array $input): StoreCustomer
    {
        $input['email'] = Str::lower(trim((string) ($input['email'] ?? '')));
        $input['phone'] = PhoneNumber::normalize($input['phone'] ?? null);

        $data = Validator::make($input, [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:255', Rule::unique('store_customers', 'email')],
            'phone' => ['nullable', 'string', 'regex:'.PhoneNumber::PATTERN],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'kvkk' => ['accepted'],
        ], [
            'email.unique' => 'Bu e-posta adresiyle zaten bir üyelik var. Giriş yapın veya parolanızı sıfırlayın.',
            'kvkk.accepted' => 'Üye olmak için aydınlatma metnini okuduğunuzu onaylayın.',
            'phone.regex' => 'Telefon numarasını 05xx xxx xx xx biçiminde girin.',
        ], ['first_name' => 'ad', 'last_name' => 'soyad', 'email' => 'e-posta', 'phone' => 'telefon', 'password' => 'parola'])->validate();

        return DB::transaction(function () use ($data, $input) {
            $customer = StoreCustomer::create([
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
            ]);
            $customer->forceFill([
                'kvkk_accepted_at' => now(),
                'marketing_consent_at' => filter_var($input['marketing'] ?? false, FILTER_VALIDATE_BOOL) ? now() : null,
            ])->save();
            $this->audit->log('store.customer_registered', $customer, [], [], actorArea: Area::Customer, actorId: $customer->id);

            return $customer;
        });
    }

    /** @throws ValidationException */
    public function updateProfile(StoreCustomer $customer, array $input): void
    {
        $input['phone'] = PhoneNumber::normalize($input['phone'] ?? null);
        $data = Validator::make($input, [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'regex:'.PhoneNumber::PATTERN],
        ], ['phone.regex' => 'Telefon numarasını 05xx xxx xx xx biçiminde girin.'], ['first_name' => 'ad', 'last_name' => 'soyad', 'phone' => 'telefon'])->validate();

        $customer->forceFill([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'phone' => $data['phone'] ?? null,
            'marketing_consent_at' => filter_var($input['marketing'] ?? false, FILTER_VALIDATE_BOOL) ? ($customer->marketing_consent_at ?? now()) : null,
        ])->save();
    }

    /** @throws ValidationException */
    public function changePassword(StoreCustomer $customer, string $current, string $new, string $confirmation): void
    {
        if (! Hash::check($current, $customer->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mevcut parola hatalı.']);
        }
        Validator::make(['password' => $new, 'password_confirmation' => $confirmation, 'current' => $current], [
            'password' => ['required', 'string', 'confirmed', Password::defaults(), 'different:current'],
        ], [], ['password' => 'yeni parola'])->validate();

        $customer->forceFill(['password' => $new, 'remember_token' => Str::random(60)])->save();
        $this->tokens->revoke(Area::Customer, $customer->id, 'password_reset');
        $this->audit->log('auth.password_changed', $customer, actorArea: Area::Customer, actorId: $customer->id);
    }

    /** @throws ValidationException|DomainException */
    public function changeEmail(StoreCustomer $customer, string $password, string $email): string
    {
        if (! Hash::check($password, $customer->password)) {
            throw ValidationException::withMessages(['email_password' => 'Parola hatalı.']);
        }
        $email = Str::lower(trim($email));
        Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'max:255', Rule::unique('store_customers', 'email')->ignore($customer->id)],
        ], ['email.unique' => 'Bu e-posta adresi başka bir üyelikte kullanılıyor.'], ['email' => 'e-posta'])->validate();

        $old = $customer->email;
        $customer->forceFill(['email' => $email, 'remember_token' => Str::random(60)])->save();
        $this->tokens->revoke(Area::Customer, $customer->id, 'password_reset');
        $this->audit->log('auth.email_changed', $customer, ['email' => $old], ['email' => $email], actorArea: Area::Customer, actorId: $customer->id);

        return $email;
    }
}
