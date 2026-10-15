<?php

namespace App\Domain\Auth;

use App\Models\AuthToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aktivasyon ve parola sıfırlama bağlantıları. Veritabanında yalnızca token'ın SHA-256 özeti
 * tutulur; düz token yalnızca bir kez, bağlantı oluşturulurken döner.
 */
class AuthTokenService
{
    public function issue(Area $area, int $userId, string $purpose): string
    {
        $plain = Str::random(64);

        DB::transaction(function () use ($area, $userId, $purpose, $plain) {
            // Aynı amaçla verilmiş eski ve kullanılmamış bağlantılar geçersiz olur.
            AuthToken::where(['guard' => $area->value, 'user_id' => $userId, 'purpose' => $purpose])
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            AuthToken::create([
                'guard' => $area->value,
                'user_id' => $userId,
                'purpose' => $purpose,
                'token_hash' => hash('sha256', $plain),
                'expires_at' => $purpose === 'activation'
                    ? now()->addHours(config('b2b.activation_ttl_hours'))
                    : now()->addMinutes(config('b2b.password_reset_ttl_minutes')),
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

    public function url(Area $area, string $purpose, string $plain): string
    {
        $route = $purpose === 'activation' ? 'activation.show' : 'password.reset';

        return route($area->value.'.'.$route, ['token' => $plain]);
    }
}
