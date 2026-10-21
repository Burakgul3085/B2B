<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mağaza kataloğu (docs/13-magaza-mimarisi.md §7, M2). B2B ürünlerinden tamamen ayrıdır; hiçbir B2B tablosuna bağlanmaz.
 *  - Ürün (store_products) bir ürün grubudur: ad, açıklama, kategori, marka, SEO, yayın durumu.
 *  - Varyant (store_product_variants) satılabilir birimdir: SKU, barkod, KDV dahil fiyat, KDV oranı, desi/ağırlık.
 *    Tek seçenekli ürünün tek varyantı vardır. Stok M3'te varyant üzerinden tutulur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('store_categories')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['parent_id', 'sort']);
        });

        Schema::create('store_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('slug', 160)->unique();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('store_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('slug', 220)->unique();
            $table->foreignId('category_id')->nullable()->constrained('store_categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('store_brands')->restrictOnDelete();
            $table->string('variant_name', 60)->nullable(); // seçenek adı: "Renk", "Boyut"…
            $table->text('description')->nullable();
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['is_published', 'category_id']);
            $table->index(['is_published', 'brand_id']);
        });

        Schema::create('store_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('option_value', 80)->nullable(); // "Mavi", "A4"…
            $table->decimal('price', 12, 2);                // KDV dahil satış fiyatı
            $table->decimal('compare_at_price', 12, 2)->nullable(); // üstü çizili fiyat
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('desi', 8, 2)->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'sort']);
        });

        Schema::create('store_product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'sort']);
        });
    }

    public function down(): void
    {
        foreach (['store_product_images', 'store_product_variants', 'store_products', 'store_brands', 'store_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
