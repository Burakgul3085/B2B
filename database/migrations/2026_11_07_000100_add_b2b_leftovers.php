<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2B eksikleri (docs/24-b2b-eksikler-raporu.md):
 *  - Hata kayıtları: çözüldü işareti ve hatanın oluştuğu adres (yalnızca yol; sorgu dizesi ve kişisel veri yok).
 *  - Bayi başvurusunda ek bilgi yanıtı: süreli, tek kullanımlık bağlantı (özeti saklanır) ve başvuranın yanıtı.
 *  - Kayıtlı rapor filtreleri: kullanıcı başına (toptancı veya bayi kullanıcısı), işletmeye bağlı.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('error_events', function (Blueprint $table) {
            $table->string('url', 300)->nullable()->after('location');
            $table->timestamp('resolved_at')->nullable()->after('last_seen_at');
        });

        Schema::table('dealer_applications', function (Blueprint $table) {
            $table->char('reply_token_hash', 64)->nullable()->unique()->after('info_request_note');
            $table->timestamp('reply_expires_at')->nullable()->after('reply_token_hash');
            $table->text('info_reply')->nullable()->after('reply_expires_at');
            $table->timestamp('info_replied_at')->nullable()->after('info_reply');
        });

        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('owner_type', 10); // business | dealer
            $table->unsignedBigInteger('owner_id');
            $table->string('report_key', 60);
            $table->string('name', 80);
            $table->json('filters');
            $table->timestamps();
            $table->index(['business_id', 'owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
        Schema::table('dealer_applications', function (Blueprint $table) {
            $table->dropUnique(['reply_token_hash']);
            $table->dropColumn(['reply_token_hash', 'reply_expires_at', 'info_reply', 'info_replied_at']);
        });
        Schema::table('error_events', fn (Blueprint $table) => $table->dropColumn(['url', 'resolved_at']));
    }
};
