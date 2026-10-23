<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Vitrin yenilemesi Aşama 4: favoriler, ürün yorumları (satın alanlar, yönetici onaylı), stoğa gelince haber ver. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('store_customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['customer_id', 'product_id']);
        });

        Schema::create('store_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('store_customers')->cascadeOnDelete();
            $table->string('author_name', 60);           // "Ayşe K." — yazıldığı anda kısaltılarak saklanır
            $table->unsignedTinyInteger('rating');       // 1–5
            $table->string('title', 80)->nullable();
            $table->text('body');
            $table->string('status', 12)->default('pending'); // pending | approved | rejected
            $table->text('reply')->nullable();           // mağaza yanıtı
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'product_id']);
            $table->index(['product_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('store_stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('store_product_variants')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('store_customers')->nullOnDelete();
            $table->string('email', 190);
            $table->timestamps();
            $table->unique(['variant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_stock_alerts');
        Schema::dropIfExists('store_reviews');
        Schema::dropIfExists('store_favorites');
    }
};
