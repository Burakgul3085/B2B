<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sağlayıcıdaki (Uyumsoft) son durum: fatura numarası alındıktan sonra GİB denetimi hata verebilir ya da fatura
 * portaldan elle iptal edilebilir; bu durum düzenli sorgulanır. Müşteriye "Faturanız hazır" e-postası fatura
 * sağlayıcıda onaylanınca gider (customer_notified_at). Mevcut gönderilmiş faturalar bildirilmiş sayılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_e_invoices', function (Blueprint $table) {
            $table->string('provider_status', 30)->nullable()->after('error_message');
            $table->timestamp('provider_checked_at')->nullable()->after('provider_status');
            $table->timestamp('customer_notified_at')->nullable()->after('notify_customer');
        });
        DB::table('store_e_invoices')->where('status', 'sent')->update(['customer_notified_at' => DB::raw('sent_at')]);
    }

    public function down(): void
    {
        Schema::table('store_e_invoices', function (Blueprint $table) {
            $table->dropColumn(['provider_status', 'provider_checked_at', 'customer_notified_at']);
        });
    }
};
