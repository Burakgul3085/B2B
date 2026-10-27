<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Yönetim panelindeki sesli sipariş alarmı: siparişin "yeni sipariş" olarak duyurulduğu an (store_orders.alerted_at)
 * ve her yöneticinin Siparişler sayfasını en son açtığı an (store_admins.orders_seen_at). Alarm, yöneticinin son
 * görmesinden sonra duyurulan sipariş varken çalar. Eski siparişler duyurulmamış sayılır (alarm çalmaz).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_orders', function (Blueprint $table) {
            $table->timestamp('alerted_at')->nullable()->index();
        });
        Schema::table('store_admins', function (Blueprint $table) {
            $table->timestamp('orders_seen_at')->nullable();
        });
        DB::table('store_admins')->update(['orders_seen_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('store_orders', function (Blueprint $table) {
            $table->dropIndex(['alerted_at']);
            $table->dropColumn('alerted_at');
        });
        Schema::table('store_admins', function (Blueprint $table) {
            $table->dropColumn('orders_seen_at');
        });
    }
};
