<?php

namespace App\Domain\Auth;

use App\Models\AuthToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Parola sıfırlama bağlantıları ("Parolamı unuttum"). Hesaplar açılırken şifre belirlendiği için aktivasyon
 * bağlantısı yoktur. Veritabanında yalnızca token'ın SHA-256 özeti
 * tutulur; düz token yalnızca bir kez, bağlantı oluşturulurken döner.
 */
class AuthTokenService
{
    public function issue(Area $area, int $userId, string $purpose, ?string $newEmail = null): string
    {
        $plain = Str::random(64);

        DB::transaction(function () use ($area, $userId, $purpose, $plain, $newEmail) {
            // Aynı amaçla verilmiş eski ve kullanılmamış bağlantılar geçersiz olur.
            AuthToken::where(['guard' => $area->value, 'user_id' => $userId, 'purpose' => $purpose])
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            AuthToken::create([
                'guard' => $area->value,
                'user_id' => $userId,
                'purpose' => $purpose,
                'new_email' => $newEmail,
                'token_hash' => hash('sha256', $plain),
                'expires_at' => now()->addMinutes(config('b2b.password_reset_ttl_minutes')),
            ]);
        });

        return $plain;
    }

    /** Token geçerliyse kaydı döndürür (tüketmez). */
    public function find(Area $area, string $purpose, string $plain): ?AuthToken
    {
        return AuthToken::where('token_hash', hash('sha256', $plain))
            ->where('guard', $area->value)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /** Token'ı tek seferlik kullanır; aynı anda iki kullanım denemesinden yalnızca biri başarılı olur. */
    public function consume(AuthToken $token): bool
    {
        return AuthToken::whereKey($token->id)->whereNull('used_at')->update(['used_at' => now()]) === 1;
    }

    /** Kullanılmamış belirteçleri geçersiz kılar (ör. yönetici e-postayı doğrudan değiştirdiğinde). */
    public function revoke(Area $area, int $userId, string $purpose): void
    {
        AuthToken::where(['guard' => $area->value, 'user_id' => $userId, 'purpose' => $purpose])->whereNull('used_at')->update(['used_at' => now()]);
    }

    public function url(Area $area, string $purpose, string $plain): string
    {
        return route($area->value.'.password.reset', ['token' => $plain]);
    }
}
