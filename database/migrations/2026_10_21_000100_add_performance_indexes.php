<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aşama 9 — büyük veri ölçümünden çıkan indeksler.
 *  - orders(business_id, approved_at): dashboard satış göstergeleri ve grafikleri onay tarihine göre aralık tarar.
 *  - orders(business_id, updated_at): dashboard önbelleğinin sürümü (son sipariş değişikliği) indeksten okunur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['business_id', 'approved_at'], 'orders_business_approved_idx');
            $table->index(['business_id', 'updated_at'], 'orders_business_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_business_approved_idx');
            $table->dropIndex('orders_business_updated_idx');
        });
    }
};
