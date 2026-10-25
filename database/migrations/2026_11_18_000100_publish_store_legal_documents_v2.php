<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Beş yasal metnin ayrıntılı ikinci sürümü (database/data/store-legal-templates-v2.php) yeni sürüm olarak eklenir.
 * Son sürümü yönetimde düzenlenmiş (created_by_type dolu) metinlere dokunulmaz; eski sürümler ve siparişlerdeki
 * onaylar korunur.
 */
return new class extends Migration
{
    public function up(): void
    {
        $normalize = fn (string $text) => str_replace("\r\n", "\n", $text);

        foreach (require database_path('data/store-legal-templates-v2.php') as $key => [$title, $body]) {
            $latest = DB::table('store_legal_documents')->where('key', $key)->orderByDesc('version')->first();

            if ($latest && ($latest->created_by_type !== null || $normalize($latest->body) === $normalize($body))) {
                continue;
            }

            DB::table('store_legal_documents')->insert([
                'key' => $key, 'version' => ($latest->version ?? 0) + 1, 'title' => $title, 'body' => $normalize($body), 'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Yasal metin sürümleri yalnızca eklenir; geri alınmaz.
    }
};
