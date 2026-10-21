<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mağaza vitrini (M4): mağaza ayarları, ana sayfa banner'ları ve sabit sayfalar (Hakkımızda, İletişim, yasal metinler…).
 * B2B vitrininden (home_banners, showcases) bağımsızdır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('store_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120)->nullable();
            $table->string('subtitle', 240)->nullable();
            $table->string('button_text', 40)->nullable();
            $table->string('link_url', 500)->nullable();
            $table->string('image_path');
            $table->string('mobile_image_path')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'sort']);
        });

        Schema::create('store_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->longText('body')->nullable();
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_footer')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_pages');
        Schema::dropIfExists('store_banners');
        Schema::dropIfExists('store_settings');
    }
};
