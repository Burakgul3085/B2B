<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Vitrin yenilemesi Aşama 5: yönetimden yazılan blog (kategoriler, yazılar, yazıda geçen ürünler). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 80)->unique();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('store_blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('store_blog_categories')->nullOnDelete();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->string('excerpt', 300)->nullable();
            $table->longText('body');                    // Markdown
            $table->string('cover_path')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable(); // ileri tarih: o an yayına girer
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 160)->nullable();
            $table->timestamps();
            $table->index(['is_published', 'published_at']);
        });

        Schema::create('store_blog_post_product', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('store_blog_posts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->primary(['post_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_blog_post_product');
        Schema::dropIfExists('store_blog_posts');
        Schema::dropIfExists('store_blog_categories');
    }
};
