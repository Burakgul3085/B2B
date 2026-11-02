<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** B2B siparişte "Yola çıktı" aşaması (hazırlanıyor → yola çıktı → tamamlandı); isteğe bağlı sevk notu. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY status ENUM('submitted', 'in_review', 'approved', 'preparing', 'shipped', 'completed', 'rejected', 'cancel_requested', 'cancelled') NOT NULL");
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('shipped_at')->nullable()->after('approved_by');
            $table->string('shipping_note', 300)->nullable()->after('shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipped_at', 'shipping_note']);
        });
        DB::statement("ALTER TABLE orders MODIFY status ENUM('submitted', 'in_review', 'approved', 'preparing', 'completed', 'rejected', 'cancel_requested', 'cancelled') NOT NULL");
    }
};
