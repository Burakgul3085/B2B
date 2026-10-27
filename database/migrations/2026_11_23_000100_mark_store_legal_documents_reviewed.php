<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Yasal metinlerin güncel sürümleri (2026-11-18'de sistemle gelen ayrıntılı ikinci sürüm) mağaza sahibi tarafından
 * gözden geçirilip onaylandı (27.10.2026). Bu sürümler yönetimden kaydedilmediği için Yayın hazırlığı listesinde
 * "eksik" görünüyordu; "gözden geçirildi" olarak işaretlenir (store_settings.legal_reviewed). Metinlere dokunulmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        $marked = json_decode((string) DB::table('store_settings')->where('key', 'legal_reviewed')->value('value'), true) ?: [];
        $latest = DB::table('store_legal_documents')->selectRaw('`key`, MAX(version) as version')->groupBy('key')->pluck('version', 'key');
        foreach ($latest as $key => $version) {
            $marked[$key] ??= (int) $version;
        }
        if ($marked === []) {
            return;
        }

        DB::table('store_settings')->updateOrInsert(['key' => 'legal_reviewed'], ['value' => json_encode($marked), 'updated_at' => now(), 'created_at' => now()]);
    }

    public function down(): void
    {
        DB::table('store_settings')->where('key', 'legal_reviewed')->delete();
    }
};
