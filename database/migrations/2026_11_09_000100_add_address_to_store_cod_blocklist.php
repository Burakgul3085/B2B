<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kapıda ödeme engel listesi adrese göre de çalışır (docs/13-magaza-mimarisi.md §8.4): teslim almayan kişi başka bir
 * telefonla aynı adrese kapıda ödemeli sipariş veremez. Adres normalleştirilip özetlenir (CodPolicy::addressKey).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_cod_blocklist', function (Blueprint $table) {
            $table->char('address_key', 64)->nullable()->after('phone');
            $table->string('address_label', 300)->nullable()->after('address_key'); // yönetimde gösterim
            $table->index('address_key');
        });
    }

    public function down(): void
    {
        Schema::table('store_cod_blocklist', function (Blueprint $table) {
            $table->dropIndex(['address_key']);
            $table->dropColumn(['address_key', 'address_label']);
        });
    }
};
