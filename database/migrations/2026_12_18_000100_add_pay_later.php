<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Sonra öde" (docs/03-is-kurallari.md §6.1): açık hesap ve teslimde ödeme tek seçenekte birleşir.
 * Bayi izni + ödeme süresi (payment_term_days, teslimden itibaren) + isteğe bağlı borç limiti.
 * Siparişte süre anlık kopyalanır; son ödeme tarihi teslimde (tamamlandığında) yazılır.
 * Eski allow_open_account / allow_on_delivery sütunları geçmiş için kalır, artık okunmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealer_relationships', function (Blueprint $table) {
            $table->boolean('allow_pay_later')->default(false)->after('allow_on_delivery');
            $table->decimal('credit_limit', 15, 2)->nullable()->after('allow_pay_later');
        });
        DB::table('dealer_relationships')->where(fn ($q) => $q->where('allow_open_account', true)->orWhere('allow_on_delivery', true))
            ->update(['allow_pay_later' => true]);

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_term_days')->nullable()->after('payment_status');
            $table->date('payment_due_on')->nullable()->after('payment_term_days');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['payment_term_days', 'payment_due_on']));
        Schema::table('dealer_relationships', fn (Blueprint $table) => $table->dropColumn(['allow_pay_later', 'credit_limit']));
    }
};
