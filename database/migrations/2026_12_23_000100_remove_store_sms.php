<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SMS kaldırıldı: teslimde ödeme (yalnızca Eskişehir) SMS koduyla doğrulanmaz. Doğrulama kodları ve SMS kayıtları
 * tabloları ile Netgsm ayarları (şifreli şifre dahil) silinir. Geri alındığında tablolar boş olarak yeniden kurulur.
 */
return new class extends Migration
{
    private const SETTING_KEYS = ['sms_driver', 'netgsm_usercode', 'netgsm_password', 'netgsm_header'];

    public function up(): void
    {
        Schema::dropIfExists('store_otp_codes');
        Schema::dropIfExists('store_sms_messages');
        DB::table('store_settings')->whereIn('key', self::SETTING_KEYS)->delete();
        Cache::forget('store.settings.saved');
    }

    public function down(): void
    {
        Schema::create('store_otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('purpose', 20);
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['phone', 'purpose', 'created_at']);
        });

        Schema::create('store_sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 20);
            $table->string('phone', 20);
            $table->string('purpose', 30);
            $table->string('body', 500);
            $table->string('status', 20);
            $table->string('provider_ref', 60)->nullable();
            $table->string('error', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
};
