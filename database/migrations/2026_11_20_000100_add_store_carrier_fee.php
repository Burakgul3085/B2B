<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kargo firması ücreti (KDV dahil, anlaşmalı fiyat). Ücreti girilmiş etkin firmalar ödeme sayfasında müşteriye
 * seçenek olarak sunulur; ücretsiz kargo sınırını geçen siparişte firma seçilmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_carriers', function (Blueprint $table) {
            $table->decimal('fee', 10, 2)->nullable()->after('tracking_url');
        });
    }

    public function down(): void
    {
        Schema::table('store_carriers', function (Blueprint $table) {
            $table->dropColumn('fee');
        });
    }
};
