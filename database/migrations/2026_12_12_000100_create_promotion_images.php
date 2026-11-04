<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2B kupon / kampanya görselleri: bir kapak ve detay fotoğrafları (bayi Kampanyalar sayfası ve kampanya detayı).
 * Açıklama uzun metne (satır atlamalı) çıkar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('promotion_id');
            $table->string('path');
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['business_id', 'id']);
            $table->index(['promotion_id', 'is_cover', 'sort']);
            $table->foreign(['business_id', 'promotion_id'])->references(['business_id', 'id'])->on('promotions')->cascadeOnDelete();
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_images');
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('description', 255)->nullable()->change();
        });
    }
};
