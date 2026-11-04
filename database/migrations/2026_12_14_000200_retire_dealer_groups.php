<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bayi grupları, fiyat listeleri, indirim kuralları ve bayiye özel fiyatlar kullanımdan kalkar (fiyat: ürünün bayi
 * fiyatı / indirimli bayi fiyatı; farklı indirim: kupon ve kampanyalar). Tablolar silinmez, yalnızca kullanılmaz.
 * Gruba bağlı kayıtlar hiçbir bayinin göreceği şey sessizce genişlemeden çevrilir:
 *  - Gruba yönelik kupon / kampanya → "seçili bayiler" (grubun o anki bayileri).
 *  - Gruba gösterilen vitrin slaytı → pasif (tüm bayilere açılmaz; toptancı isterse yeniden yayınlar).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            foreach (DB::table('promotions')->where('target', 'group')->get(['id', 'business_id', 'dealer_group_id']) as $promotion) {
                $dealerIds = DB::table('dealer_relationships')
                    ->where('business_id', $promotion->business_id)
                    ->where('dealer_group_id', $promotion->dealer_group_id)
                    ->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

                DB::table('promotions')->where('id', $promotion->id)->update([
                    'target' => 'dealers', 'dealer_group_id' => null, 'dealer_ids' => json_encode($dealerIds),
                ]);
            }

            DB::table('home_banners')->whereNotNull('dealer_group_id')->update(['is_active' => false, 'dealer_group_id' => null]);
        });
    }

    public function down(): void
    {
        // Veri dönüşümü geri alınmaz (gruplar tablo olarak duruyor).
    }
};
