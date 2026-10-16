<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Giriş e-postası değişikliği: yeni adres doğrulanana kadar belirteçle birlikte bekler.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE auth_tokens MODIFY purpose ENUM('activation','password_reset','email_change') NOT NULL");
        Schema::table('auth_tokens', function (Blueprint $table) {
            $table->string('new_email')->nullable()->after('purpose');
        });
    }

    public function down(): void
    {
        DB::table('auth_tokens')->where('purpose', 'email_change')->delete();
        Schema::table('auth_tokens', function (Blueprint $table) {
            $table->dropColumn('new_email');
        });
        DB::statement("ALTER TABLE auth_tokens MODIFY purpose ENUM('activation','password_reset') NOT NULL");
    }
};
