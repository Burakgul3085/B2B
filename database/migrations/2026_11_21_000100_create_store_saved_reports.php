<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mağaza yöneticisinin adıyla kaydettiği rapor filtreleri (rapor merkezinden tek tıkla açılır). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('store_admins')->cascadeOnDelete();
            $table->string('report_key', 60);
            $table->string('name', 80);
            $table->json('filters');
            $table->timestamps();
            $table->index(['admin_id', 'report_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_saved_reports');
    }
};
