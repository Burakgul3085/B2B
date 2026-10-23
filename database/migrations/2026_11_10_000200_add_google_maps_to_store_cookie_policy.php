<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * İletişim sayfasındaki Google Haritalar: Gizlilik ve Çerez Politikası'na üçüncü taraf çerez notu eklenir.
 * Yasal metinler sürümlüdür; son sürüm hâlâ şablonun önceki hâliyse (yönetimde değiştirilmemişse) yeni sürüm eklenir.
 * Değiştirilmişse dokunulmaz; paragraf yönetimde Ayarlar → Yasal Metinler'den eklenir.
 */
return new class extends Migration
{
    public function up(): void
    {
        [, $body] = (require database_path('data/store-legal-templates-m9.php'))['gizlilik-cerez'];
        $latest = DB::table('store_legal_documents')->where('key', 'gizlilik-cerez')->orderByDesc('version')->first();

        $normalize = fn (string $text) => str_replace("\r\n", "\n", $text);
        $body = $normalize($body);
        $previous = preg_replace("/\n\*\*Google Haritalar:\*\*[^\n]*\n/", '', $body);

        if ($latest && $normalize($latest->body) === $previous) {
            DB::table('store_legal_documents')->insert([
                'key' => 'gizlilik-cerez', 'version' => $latest->version + 1, 'title' => $latest->title, 'body' => $body, 'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Yasal metin sürümleri yalnızca eklenir; geri alınmaz.
    }
};
