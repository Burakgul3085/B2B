<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mağaza iade ve iptal iyileştirmeleri:
 *  - İade talebine fotoğraflar (store_return_photos), müşterinin girdiği iade kargosu (firma, takip no, ödediği ücret),
 *    hatırlatma zamanı.
 *  - Havalesi alınmış siparişte müşterinin iptal talebi (para elle iade edilir): neden ve IBAN siparişte tutulur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_returns', function (Blueprint $table) {
            $table->string('cargo_company', 40)->nullable()->after('refund_account_name');
            $table->string('cargo_tracking', 60)->nullable()->after('cargo_company');
            $table->decimal('cargo_fee', 10, 2)->nullable()->after('cargo_tracking'); // hasarlı / yanlış üründe müşterinin ödediği iade kargosu
            $table->timestamp('shipped_at')->nullable()->after('approved_at');
            $table->timestamp('reminded_at')->nullable()->after('shipped_at');
        });

        Schema::create('store_return_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('store_returns')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('size');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('store_orders', function (Blueprint $table) {
            $table->timestamp('cancel_requested_at')->nullable()->after('cancel_reason');
            $table->string('cancel_request_reason', 300)->nullable()->after('cancel_requested_at');
            $table->string('cancel_refund_iban', 34)->nullable()->after('cancel_request_reason');
            $table->string('cancel_refund_name', 120)->nullable()->after('cancel_refund_iban');
        });
    }

    public function down(): void
    {
        Schema::table('store_orders', fn (Blueprint $table) => $table->dropColumn(['cancel_requested_at', 'cancel_request_reason', 'cancel_refund_iban', 'cancel_refund_name']));
        Schema::dropIfExists('store_return_photos');
        Schema::table('store_returns', fn (Blueprint $table) => $table->dropColumn(['cargo_company', 'cargo_tracking', 'cargo_fee', 'shipped_at', 'reminded_at']));
    }
};
