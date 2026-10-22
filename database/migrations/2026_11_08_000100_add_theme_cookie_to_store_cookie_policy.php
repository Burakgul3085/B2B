<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mağaza vitrini koyu tema: Gizlilik ve Çerez Politikası'na "tema" tercih çerezi eklenir.
 * Yasal metinler sürümlüdür; son sürüm hâlâ ilk şablonsa (yönetimde değiştirilmemişse) yeni sürüm eklenir.
 * Değiştirilmişse dokunulmaz; satır yönetimde Ayarlar → Yasal Metinler'den eklenir.
 */
return new class extends Migration
{
    public function up(): void
    {
        [, $body] = (require database_path('data/store-legal-templates-m9.php'))['gizlilik-cerez'];
        $latest = DB::table('store_legal_documents')->where('key', 'gizlilik-cerez')->orderByDesc('version')->first();

        $normalize = fn (string $text) => str_replace("\r\n", "\n", $text);
        $themeRow = "| `tema` | Tercih | Seçtiğiniz görünümün (açık/koyu) hatırlanması; yalnızca tema seçtiğinizde yazılır | 1 yıl |\n";

        if ($latest && $normalize($latest->body) === str_replace($themeRow, '', $normalize($body))) {
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
