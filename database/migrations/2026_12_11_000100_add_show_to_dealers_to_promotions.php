<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bayi panelindeki Kampanyalar sayfası: kupon / kampanya bayiye gösterilsin mi. Kodu kişiye özel verilen kupon
 * sayfada görünmesin diye ayrı ayardır. Mevcut otomatik kampanyalar gösterilir, kuponlar gösterilmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->boolean('show_to_dealers')->default(false);
        });
        DB::table('promotions')->whereNull('code')->update(['show_to_dealers' => true]);
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('show_to_dealers');
        });
    }
};
