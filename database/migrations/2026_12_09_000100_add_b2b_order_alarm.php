<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Toptancı panelinde sesli yeni sipariş alarmı (mağaza yönetimindeki alarmın B2B karşılığı).
 * business_users.orders_seen_at: Siparişler sayfasının son açılışı; ondan sonra gelen bayi siparişi alarm çaldırır.
 * Mevcut kullanıcılar için şimdiki an yazılır (eski siparişler alarm çaldırmaz).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('order_alarm')->default(true);
        });
        Schema::table('business_users', function (Blueprint $table) {
            $table->timestamp('orders_seen_at')->nullable();
        });
        DB::table('business_users')->update(['orders_seen_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('business_users', function (Blueprint $table) {
            $table->dropColumn('orders_seen_at');
        });
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn('order_alarm');
        });
    }
};
