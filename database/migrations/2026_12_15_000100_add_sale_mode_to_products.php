<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satış şekli (docs/03-is-kurallari.md §2.1): tek tek, koli halinde veya özel kural (en az X, Y'er).
 * Miktar, fiyat ve stok her zaman ürünün biriminden (yeni ürünlerde adet); koli yalnızca sipariş kuralıdır
 * (pack_quantity = koli içi, order_step = koli içi, min_order_qty = koli içi × en az koli).
 * Mevcut ürünlerin şekli kurallarından çıkarılır; birimleri değişmez (stok hareketleri değiştirilmez).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('sale_mode', ['single', 'case', 'custom'])->default('single')->after('unit_id');
        });

        DB::table('products')->where('pack_quantity', '>', 1)->whereColumn('order_step', 'pack_quantity')->update(['sale_mode' => 'case']);
        DB::table('products')->where('sale_mode', 'single')->where('order_step', '>', 1)->update(['sale_mode' => 'custom']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sale_mode');
        });
    }
};
