<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vitrin yenilemesi — Aşama 2 (ana sayfa):
 *  - banner yerleşimi: büyük alan (hero) veya yanındaki küçük alan (side, en fazla 2),
 *  - kuponların ana sayfada kart olarak gösterilmesi (show_on_home),
 *  - seçkiler: yönetimin elle seçtiği ürün listeleri ("Okula dönüş", "Ofis" …), kendi sayfaları /secki/{slug}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_banners', function (Blueprint $table) {
            $table->string('placement', 10)->default('hero')->after('link_url'); // hero | side
        });

        Schema::table('store_discounts', function (Blueprint $table) {
            $table->boolean('show_on_home')->default(false)->after('is_active');
        });

        Schema::create('store_collections', function (Blueprint $table) {
            $table->id();
            $table->string('title', 80);
            $table->string('slug', 100)->unique();
            $table->string('subtitle', 160)->nullable();
            $table->string('color', 12)->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('store_collection_product', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained('store_collections')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->primary(['collection_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_collection_product');
        Schema::dropIfExists('store_collections');
        Schema::table('store_discounts', fn (Blueprint $table) => $table->dropColumn('show_on_home'));
        Schema::table('store_banners', fn (Blueprint $table) => $table->dropColumn('placement'));
    }
};
