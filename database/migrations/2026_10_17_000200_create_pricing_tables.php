<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fiyatlandırma (docs/03-is-kurallari.md §4). Öncelik:
 *   1 bayiye özel ürün fiyatı → 2 grup fiyat listesi → 3 standart bayi fiyatı → 4 liste fiyatı,
 *   2–4 için tek indirim: ürün indirimi, yoksa kategori indirimi (yığılmaz).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'id']);
            $table->unique(['business_id', 'name']);
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('price_list_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('price', 15, 4);
            $table->timestamps();
            $table->unique(['price_list_id', 'product_id']);
            $table->foreign(['business_id', 'price_list_id'])->references(['business_id', 'id'])->on('price_lists')->cascadeOnDelete();
            $table->foreign(['business_id', 'product_id'])->references(['business_id', 'id'])->on('products');
        });
        DB::statement('ALTER TABLE price_list_items ADD CONSTRAINT price_list_items_price_chk CHECK (price >= 0)');

        Schema::table('dealer_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('price_list_id')->nullable()->after('name');
            $table->foreign(['business_id', 'price_list_id'], 'dealer_groups_price_list_fk')->references(['business_id', 'id'])->on('price_lists');
        });

        Schema::create('dealer_product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('dealer_relationship_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('price', 15, 4);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->index(['dealer_relationship_id', 'product_id']);
            $table->foreign(['business_id', 'dealer_relationship_id'])->references(['business_id', 'id'])->on('dealer_relationships');
            $table->foreign(['business_id', 'product_id'])->references(['business_id', 'id'])->on('products');
        });
        DB::statement('ALTER TABLE dealer_product_prices ADD CONSTRAINT dealer_product_prices_price_chk CHECK (price >= 0)');

        Schema::create('discount_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->enum('scope', ['product', 'category']);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->enum('target', ['all', 'group', 'dealer']);
            $table->unsignedBigInteger('dealer_group_id')->nullable();
            $table->unsignedBigInteger('dealer_relationship_id')->nullable();
            $table->decimal('percent', 5, 2);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['business_id', 'is_active', 'scope']);
            $table->foreign(['business_id', 'product_id'])->references(['business_id', 'id'])->on('products');
            $table->foreign(['business_id', 'category_id'])->references(['business_id', 'id'])->on('categories');
            $table->foreign(['business_id', 'dealer_group_id'])->references(['business_id', 'id'])->on('dealer_groups');
            $table->foreign(['business_id', 'dealer_relationship_id'])->references(['business_id', 'id'])->on('dealer_relationships');
        });
        DB::statement('ALTER TABLE discount_rules ADD CONSTRAINT discount_rules_percent_chk CHECK (percent > 0 AND percent < 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_rules');
        Schema::dropIfExists('dealer_product_prices');
        Schema::table('dealer_groups', function (Blueprint $table) {
            $table->dropForeign('dealer_groups_price_list_fk');
            $table->dropColumn('price_list_id');
        });
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
    }
};
