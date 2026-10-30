<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fotoğraflı ürün yorumları: yorum başına en fazla 5 fotoğraf (özel diskte; yorum yayımlanınca vitrinde görünür). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_review_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('store_reviews')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('size');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_review_photos');
    }
};
