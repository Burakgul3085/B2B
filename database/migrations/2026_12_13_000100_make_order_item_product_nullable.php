<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün kalıcı silinebilir (stok hareketleriyle birlikte): sipariş kalemi ürünün adını, kodunu, birimini, fiyatını ve
 * KDV'sini zaten kendi üzerinde saklar; ürün silinince yalnızca bağlantı boşalır (ProductService::delete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['business_id', 'product_id']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreign(['business_id', 'product_id'])->references(['business_id', 'id'])->on('products');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['business_id', 'product_id']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign(['business_id', 'product_id'])->references(['business_id', 'id'])->on('products');
        });
    }
};
