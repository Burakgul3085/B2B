<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mağaza yönetim paneli kullanıcıları (docs/13-magaza-mimarisi.md §5). B2B kullanıcılarından tamamen ayrıdır.
 * Süper admini platform açar; süper admin yönetici açar. Hesaplar silinmez, pasife alınır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_super')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('created_by_type', 20)->nullable(); // platform | store_admin
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_admins');
    }
};
