<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bireysel siparişte T.C. kimlik numarası artık zorunludur (e-Arşiv faturası alıcının kimlik numarasıyla kesilir).
 * KVKK aydınlatma metnindeki "talep edilmesi hâlinde" ifadesi güncel sürüme yeni sürüm olarak işlenir; eski sürümler
 * ve siparişlerdeki onaylar korunur. Metin yönetimde değiştirilmişse dokunulmaz, Yasal Metinler ekranından elle düzeltilir.
 */
return new class extends Migration
{
    private const SEARCH = 'Ad, soyad; fatura için talep edilmesi hâlinde T.C. kimlik numarası';

    private const REPLACE = 'Ad, soyad; bireysel siparişlerde e-Arşiv faturası düzenlenmesi için T.C. kimlik numarası (vergi mevzuatı gereği zorunludur)';

    public function up(): void
    {
        $latest = DB::table('store_legal_documents')->where('key', 'kvkk')->orderByDesc('version')->first();
        if (! $latest) {
            return;
        }

        $body = str_replace("\r\n", "\n", $latest->body);
        if (str_contains($body, 'e-Arşiv faturası düzenlenmesi için T.C. kimlik numarası') || substr_count($body, self::SEARCH) !== 1) {
            return; // daha önce eklenmiş veya metin değiştirilmiş
        }

        DB::table('store_legal_documents')->insert([
            'key' => 'kvkk', 'version' => $latest->version + 1, 'title' => $latest->title,
            'body' => str_replace(self::SEARCH, self::REPLACE, $body), 'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Yasal metin sürümleri yalnızca eklenir; geri alınmaz.
    }
};
