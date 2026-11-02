<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kısmi kart iadesi: bayinin iadesi teslim alınınca kartla ödenen tutarın iade edilen kısmı iyzico'dan geri ödenir.
 * refunded_amount, ödemeden bugüne kadar geri ödenen toplamı tutar; tamamı geri ödenince durum "refunded" olur.
 * order_returns.refunded_to_card: iadenin karta geri ödenen kısmı (kalanı bayinin carisinde alacaktır).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->decimal('refunded_amount', 15, 2)->default(0)->after('amount');
        });
        DB::table('order_payments')->whereIn('status', ['refunded', 'auto_refunded'])->update(['refunded_amount' => DB::raw('amount')]);
        DB::statement('ALTER TABLE order_payments ADD CONSTRAINT order_payments_refund_chk CHECK (refunded_amount >= 0 AND refunded_amount <= amount)');

        Schema::table('order_returns', function (Blueprint $table) {
            $table->decimal('refunded_to_card', 15, 2)->default(0)->after('grand_total');
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropColumn('refunded_to_card');
        });
        DB::statement('ALTER TABLE order_payments DROP CHECK order_payments_refund_chk');
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropColumn('refunded_amount');
        });
    }
};
