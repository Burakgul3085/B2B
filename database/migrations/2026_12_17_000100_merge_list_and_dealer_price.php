<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tek satış fiyatı (docs/03-is-kurallari.md §4.1): "Liste fiyatı" ile "Standart bayi fiyatı" birleşti.
 * Bayinin bugün ödediği fiyat (bayi fiyatı varsa o, yoksa liste fiyatı) products.list_price'a yazılır ve
 * dealer_price boşaltılır; hiçbir bayinin fiyatı değişmez. Geçmiş siparişler kendi anlık görüntüsünü taşır.
 * Geri alınamaz (eski liste fiyatı bayiye hiç gösterilmediği için tutulmaz).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->whereNotNull('dealer_price')->update([
            'list_price' => DB::raw('dealer_price'),
            'dealer_price' => null,
        ]);
    }

    public function down(): void
    {
        // Veri birleştirmesi; geri dönüşte yapılacak bir şey yok.
    }
};
