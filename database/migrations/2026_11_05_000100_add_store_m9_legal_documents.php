<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mağaza M9: İptal ve İade Koşulları, KVKK Aydınlatma Metni, Gizlilik ve Çerez Politikası (ilk sürümler)
 * ve müşteri hesabının kapatılma zamanı (KVKK: hesap bilgileri anonimleştirilir, siparişler saklanır).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (require database_path('data/store-legal-templates-m9.php') as $key => [$title, $body]) {
            if (! DB::table('store_legal_documents')->where('key', $key)->exists()) {
                DB::table('store_legal_documents')->insert(['key' => $key, 'version' => 1, 'title' => $title, 'body' => $body, 'created_at' => $now]);
            }
        }

        Schema::table('store_customers', function ($table) {
            $table->timestamp('closed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_customers', fn ($table) => $table->dropColumn('closed_at'));
        DB::table('store_legal_documents')->whereIn('key', ['iptal-iade', 'kvkk', 'gizlilik-cerez'])->delete();
    }
};
