<?php

namespace App\Domain\Platform;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Raporlanan hataları error_events tablosuna toplar (Platform → Hata Kayıtları).
 *  - Aynı hata sınıfı + dosya + satır tek kayıttır; tekrar ettikçe sayaç artar. Çözüldü işaretli hata tekrar ederse yeniden açılır.
 *  - Kişisel veri yazılmaz: veritabanı hatalarında SQL (değerleriyle) kesilir, adres yalnızca yol olarak tutulur.
 *  - Kayıt sırasında oluşan hata (ör. veritabanı kapalı) yutulur; asıl hata her durumda günlüğe yazılır.
 */
class ErrorRecorder
{
    public function record(Throwable $e): void
    {
        try {
            // Proje köküne göreli yol (sunucudaki tam yol kayda girmez).
            $base = str_replace('\\', '/', base_path()).'/';
            $file = str_replace('\\', '/', $e->getFile());
            $file = str_starts_with($file, $base) ? substr($file, strlen($base)) : basename($file);
            $location = mb_substr($file.':'.$e->getLine(), 0, 500);
            $fingerprint = hash('sha256', get_class($e).'|'.$location);
            $now = now();

            $updated = DB::table('error_events')->where('fingerprint', $fingerprint)->update([
                'occurrences' => DB::raw('occurrences + 1'),
                'last_seen_at' => $now,
                'resolved_at' => null,
                'message' => $this->message($e),
                'url' => $this->url(),
            ]);
            if ($updated === 0) {
                DB::table('error_events')->insertOrIgnore([
                    'fingerprint' => $fingerprint, 'level' => 'error', 'exception_class' => mb_substr(get_class($e), 0, 255),
                    'message' => $this->message($e), 'location' => $location, 'url' => $this->url(),
                    'occurrences' => 1, 'first_seen_at' => $now, 'last_seen_at' => $now,
                ]);
            }
        } catch (Throwable) {
            // Kayıt yapılamazsa yalnızca Laravel'in günlüğü kalır.
        }
    }

    private function message(Throwable $e): string
    {
        $message = $e->getMessage();
        if ($e instanceof QueryException) {
            $message = strstr($message, ' (Connection:', true) ?: $message;
        }

        return mb_substr(trim($message) ?: '(mesaj yok)', 0, 1000);
    }

    private function url(): ?string
    {
        if (app()->runningInConsole() || ! app()->bound('request')) {
            return app()->runningInConsole() ? 'konsol: '.mb_substr(implode(' ', array_slice($_SERVER['argv'] ?? [], 1, 2)), 0, 280) : null;
        }
        $request = request();

        return mb_substr($request->method().' '.$request->getHost().'/'.ltrim($request->path(), '/'), 0, 300);
    }
}
