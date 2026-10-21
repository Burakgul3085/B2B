<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Parola sıfırlama bağlantıları mağaza yöneticisi ve mağaza müşterisi hesapları için de üretilebilsin. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE auth_tokens MODIFY guard ENUM('platform','business','dealer','store_admin','customer') NOT NULL");
    }

    public function down(): void
    {
        DB::table('auth_tokens')->whereIn('guard', ['store_admin', 'customer'])->delete();
        DB::statement("ALTER TABLE auth_tokens MODIFY guard ENUM('platform','business','dealer') NOT NULL");
    }
};
