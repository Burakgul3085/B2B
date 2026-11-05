<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kargo firmasının vergi numarası: e-Arşiv internet satışı faturasında taşıyıcı VKN'si olarak gönderilir.
 * Boşsa Uyumsoft taşıyıcı yerine satıcının kimliğini yazar; bu yüzden kargolu siparişin faturası için zorunludur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_carriers', function (Blueprint $table) {
            $table->string('tax_number', 11)->nullable()->after('fee');
        });
    }

    public function down(): void
    {
        Schema::table('store_carriers', function (Blueprint $table) {
            $table->dropColumn('tax_number');
        });
    }
};
