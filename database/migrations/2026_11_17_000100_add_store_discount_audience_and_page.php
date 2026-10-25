<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kampanya hedef kitlesi ve vitrindeki Kampanyalar sayfası.
 *  - audience: all | members | new_members. Yeni üye: hesabı new_member_days gün içinde açılmış (boş: süre yok) ve
 *    daha önce siparişi olmayan üye; indirimi bir kez kullanır.
 *  - show_on_page: Kampanyalar sayfasında kart olarak gösterilir (açıklama ve görselle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_discounts', function (Blueprint $table) {
            $table->string('audience', 20)->default('all')->after('first_order_only');
            $table->unsignedSmallInteger('new_member_days')->nullable()->after('audience');
            $table->boolean('show_on_page')->default(false)->after('show_on_home');
            $table->string('description', 500)->nullable()->after('name');
            $table->string('image_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('store_discounts', fn (Blueprint $table) => $table->dropColumn(['audience', 'new_member_days', 'show_on_page', 'description', 'image_path']));
    }
};
