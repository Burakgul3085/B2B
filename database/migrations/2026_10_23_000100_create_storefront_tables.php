<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bayi ana sayfası (vitrin): banner slaytları ve ürün vitrinleri. */
return new class extends Migration
{
    public function up(): void
    {
        // Ana sayfa slaytları: resim veya video. Bağlantı serbest adres değil, sistemdeki bir hedeftir.
        Schema::create('home_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('title', 120)->nullable();
            $table->string('subtitle', 240)->nullable();
            $table->string('button_label', 40)->nullable();
            $table->enum('link_type', ['none', 'catalog', 'category', 'brand', 'product', 'showcase'])->default('none');
            $table->unsignedBigInteger('link_id')->nullable();
            $table->enum('media_type', ['image', 'video']);
            $table->string('media_path');
            $table->string('media_mime', 60);
            $table->unsignedInteger('media_size');
            $table->string('mobile_media_path')->nullable(); // isteğe bağlı mobil görsel
            $table->string('poster_path')->nullable();       // video kapak görseli
            $table->enum('text_position', ['left', 'center', 'right'])->default('left');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedBigInteger('dealer_group_id')->nullable(); // boşsa tüm bayiler
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'id']);
            $table->index(['business_id', 'is_active', 'sort']);
            $table->foreign(['business_id', 'dealer_group_id'])->references(['business_id', 'id'])->on('dealer_groups');
        });

        // Ana sayfadaki sekmeli ürün grupları.
        Schema::create('showcases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('name', 60);
            $table->enum('type', ['manual', 'category', 'brand', 'new', 'best_sellers']);
            $table->unsignedBigInteger('source_id')->nullable(); // kategori veya marka
            $table->unsignedTinyInteger('product_limit')->default(8);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'id']);
            $table->index(['business_id', 'is_active', 'sort']);
        });

        Schema::create('showcase_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('showcase_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->unique(['showcase_id', 'product_id']);
            $table->foreign(['business_id', 'showcase_id'])->references(['business_id', 'id'])->on('showcases')->cascadeOnDelete();
            $table->foreign(['business_id', 'product_id'])->references(['business_id', 'id'])->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showcase_products');
        Schema::dropIfExists('showcases');
        Schema::dropIfExists('home_banners');
    }
};
