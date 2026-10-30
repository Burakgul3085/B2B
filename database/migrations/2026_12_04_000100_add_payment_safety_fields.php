<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ödeme güvenliği (yalnızca yeni alanlar; mevcut veri değişmez):
 *  - store_orders.submit_token: ödeme formunun tek kullanımlık anahtarı; çift tıklama / yeniden gönderim yeni sipariş açmaz.
 *  - store_payments.mode, order_payments.mode: ödeme iyzico'nun test (sandbox) mı canlı (live) ortamında alındı.
 * Yeni durum değerleri mevcut metin sütunlarına yazılır: ödeme expired / auto_refunded / refund_failed, iade uncertain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_orders', function (Blueprint $table) {
            $table->string('submit_token', 36)->nullable()->unique()->after('access_token');
        });
        Schema::table('store_payments', function (Blueprint $table) {
            $table->string('mode', 10)->nullable()->after('driver');
        });
        Schema::table('order_payments', function (Blueprint $table) {
            $table->string('mode', 10)->nullable()->after('driver');
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', fn (Blueprint $table) => $table->dropColumn('mode'));
        Schema::table('store_payments', fn (Blueprint $table) => $table->dropColumn('mode'));
        Schema::table('store_orders', function (Blueprint $table) {
            $table->dropUnique(['submit_token']);
            $table->dropColumn('submit_token');
        });
    }
};
