<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bayi "Yeni Ürünler": kaç günlük ürünler yeni sayılır ve hangi tarihten öncekiler hiç sayılmaz. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('new_product_days')->default(30)->after('price_display');
            $table->date('new_products_since')->nullable()->after('new_product_days');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['new_product_days', 'new_products_since']);
        });
    }
};
