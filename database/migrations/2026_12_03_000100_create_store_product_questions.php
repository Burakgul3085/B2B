<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün Soru & Cevap: üye olmayan ziyaretçi de sorabilir (ad, soyad, e-posta). Soru, mağaza cevapladığında cevabıyla
 * birlikte yayımlanır ve cevap soranın e-postasına gider; cevapsız soru vitrinde görünmez. E-posta hiçbir yerde gösterilmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_product_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('store_customers')->nullOnDelete();
            $table->string('first_name', 60);
            $table->string('last_name', 60);
            $table->string('email', 190);
            $table->text('question');
            $table->text('answer')->nullable();
            $table->string('status', 20)->default('pending'); // pending | answered | hidden
            $table->timestamp('answered_at')->nullable();
            $table->unsignedBigInteger('answered_by')->nullable();
            $table->timestamp('answer_mailed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['product_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_product_questions');
    }
};
