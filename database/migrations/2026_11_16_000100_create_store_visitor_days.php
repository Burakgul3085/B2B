<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Vitrin ziyaret sayacı: her gün her ziyaretçi için bir satır (IP saklanmaz; ziyaretçi her gün değişen bir özetle tanınır). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_visitor_days', function (Blueprint $table) {
            $table->id();
            $table->date('day');                                  // Türkiye saatine göre gün
            $table->char('visitor', 64);                          // HMAC(gün | IP | tarayıcı)
            $table->unsignedInteger('page_views')->default(1);
            $table->timestamp('last_seen_at');
            $table->unique(['day', 'visitor']);
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_visitor_days');
    }
};
