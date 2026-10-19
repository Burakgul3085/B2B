<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Slayt konumu: ana slayt (sayfanın üstü) veya ara bant (vitrinlerin arasında ince şerit). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_banners', function (Blueprint $table) {
            $table->enum('placement', ['hero', 'strip'])->default('hero')->after('business_id');
            $table->index(['business_id', 'placement', 'is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::table('home_banners', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'placement', 'is_active', 'sort']);
            $table->dropColumn('placement');
        });
    }
};
