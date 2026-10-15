<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('code', 10);
            $table->string('name', 40);
            $table->boolean('allows_fraction')->default(false);
            $table->timestamps();
            $table->unique(['business_id', 'code']);
            $table->unique(['business_id', 'id']);
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('name', 40);
            $table->decimal('rate', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'rate']);
            $table->unique(['business_id', 'id']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name', 120);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'parent_id', 'name']);
            $table->unique(['business_id', 'id']);
            $table->foreign(['business_id', 'parent_id'])->references(['business_id', 'id'])->on('categories');
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'name']);
            $table->unique(['business_id', 'id']);
        });

        Schema::create('variant_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('name', 160);
            $table->json('attribute_names');
            $table->timestamps();
            $table->unique(['business_id', 'id']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('sku', 64);
            $table->string('barcode', 64)->nullable();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->string('model', 120)->nullable();
            $table->unsignedBigInteger('unit_id');
            $table->decimal('pack_quantity', 14, 3)->default(1);
            $table->decimal('purchase_price', 15, 4)->nullable();
            $table->decimal('list_price', 15, 4)->default(0);
            $table->decimal('dealer_price', 15, 4)->nullable();
            $table->unsignedBigInteger('tax_rate_id');
            $table->boolean('price_includes_tax')->default(false);
            $table->decimal('min_order_qty', 14, 3)->default(1);
            $table->decimal('order_step', 14, 3)->default(1);
            $table->decimal('critical_stock_level', 14, 3)->default(0);
            $table->unsignedBigInteger('variant_group_id')->nullable();
            $table->json('variant_values')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('visible_to_dealers')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'sku']);
            $table->unique(['business_id', 'barcode']);
            $table->unique(['business_id', 'id']);
            $table->index(['business_id', 'is_active', 'visible_to_dealers', 'category_id'], 'products_catalog_idx');
            $table->index(['business_id', 'brand_id']);
            $table->index(['business_id', 'name']);

            // İlişkili kayıt aynı işletmeye ait olmak zorunda.
            $table->foreign(['business_id', 'category_id'])->references(['business_id', 'id'])->on('categories');
            $table->foreign(['business_id', 'brand_id'])->references(['business_id', 'id'])->on('brands');
            $table->foreign(['business_id', 'unit_id'])->references(['business_id', 'id'])->on('units');
            $table->foreign(['business_id', 'tax_rate_id'])->references(['business_id', 'id'])->on('tax_rates');
            $table->foreign(['business_id', 'variant_group_id'])->references(['business_id', 'id'])->on('variant_groups');
        });

        DB::statement('ALTER TABLE products ADD FULLTEXT products_search_ft (name, sku, barcode)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_prices_chk CHECK (list_price >= 0 AND (dealer_price IS NULL OR dealer_price >= 0) AND (purchase_price IS NULL OR purchase_price >= 0))');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_qty_chk CHECK (pack_quantity > 0 AND min_order_qty > 0 AND order_step > 0 AND critical_stock_level >= 0)');

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('path');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->foreign(['business_id', 'product_id'])->references(['business_id', 'id'])->on('products')->cascadeOnDelete();
            $table->index(['product_id', 'sort']);
        });
    }

    public function down(): void
    {
        foreach (['product_images', 'products', 'variant_groups', 'brands', 'categories', 'tax_rates', 'units'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
