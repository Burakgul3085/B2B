<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İndirimli bayi fiyatı: tarih aralığında tüm bayilere standart bayi fiyatı yerine uygulanır (PriceResolver).
 * Fiyat, ürünün "KDV dahil" ayarına göre yorumlanır; tarih boşsa hemen / süresiz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('dealer_sale_price', 15, 4)->nullable()->after('dealer_price');
            $table->date('dealer_sale_starts_on')->nullable()->after('dealer_sale_price');
            $table->date('dealer_sale_ends_on')->nullable()->after('dealer_sale_starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['dealer_sale_price', 'dealer_sale_starts_on', 'dealer_sale_ends_on']);
        });
    }
};
