<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vitrin yenilemesi: kategorilere görünüm alanları. Renk, logodaki yapboz renklerinden biridir (boşsa ana kategoriler
 * sırayla turuncu, yeşil, mavi, sarı alır); görsel ve kısa tanıtım geniş menüde ve ana sayfa kutularında kullanılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_categories', function (Blueprint $table) {
            $table->string('color', 12)->nullable()->after('description');
            $table->string('tagline', 120)->nullable()->after('color');
            $table->string('image_path')->nullable()->after('tagline');
        });
    }

    public function down(): void
    {
        Schema::table('store_categories', function (Blueprint $table) {
            $table->dropColumn(['color', 'tagline', 'image_path']);
        });
    }
};
