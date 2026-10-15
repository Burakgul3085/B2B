<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mesajlaşma, bildirim merkezi, işletme bazlı e-posta ve duyurular (Bölüm 14–15). */
return new class extends Migration
{
    public function up(): void
    {
        // Konuşma her zaman bir toptancı ile bir bayi (ilişki) arasındadır; katılımcılar sabittir.
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('dealer_relationship_id');
            $table->string('subject', 160);
            $table->string('context_type', 30)->nullable(); // order | product_request
            $table->unsignedBigInteger('context_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'id']);
            $table->index(['business_id', 'last_message_at']);
            $table->index(['dealer_relationship_id', 'last_message_at']);
            $table->unique(['dealer_relationship_id', 'context_type', 'context_id'], 'conversations_context_unique');
            $table->foreign(['business_id', 'dealer_relationship_id'])->references(['business_id', 'id'])->on('dealer_relationships');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('conversation_id');
            $table->enum('sender_side', ['business', 'dealer']);
            $table->unsignedBigInteger('sender_id');
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['conversation_id', 'id']);
            $table->foreign(['business_id', 'conversation_id'])->references(['business_id', 'id'])->on('conversations')->cascadeOnDelete();
        });
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE messages ADD FULLTEXT messages_body_ft (body)');

        Schema::create('conversation_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('conversation_id');
            $table->enum('side', ['business', 'dealer']);
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->unique(['conversation_id', 'side']);
            $table->foreign(['business_id', 'conversation_id'])->references(['business_id', 'id'])->on('conversations')->cascadeOnDelete();
        });

        // Uygulama içi bildirimler. Bayi bildirimleri ilişki bazındadır (hangi toptancıdan geldiği bellidir).
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->enum('recipient_side', ['business', 'dealer']);
            $table->unsignedBigInteger('dealer_relationship_id')->nullable();
            $table->string('type', 40);
            $table->string('title', 200);
            $table->string('body', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_id', 'recipient_side', 'read_at']);
            $table->index(['dealer_relationship_id', 'read_at']);
            $table->foreign(['business_id', 'dealer_relationship_id'])->references(['business_id', 'id'])->on('dealer_relationships');
        });

        // Yalnızca e-posta + uygulama şifresi + görünen ad. Sunucu/port/şifreleme sistemde sabittir (Gmail SMTP).
        Schema::create('email_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('from_email');
            $table->string('from_name', 120)->nullable();
            $table->text('app_password_encrypted');
            $table->enum('status', ['unverified', 'verified', 'failed'])->default('unverified');
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_error_code', 40)->nullable();
            $table->boolean('payment_reminders')->default(false);
            $table->timestamps();
        });

        // İşletmenin şablon metni değişiklikleri (konu + giriş metni). Görsel düzen kodda sabittir.
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('subject', 200);
            $table->text('intro');
            $table->timestamps();
            $table->unique(['business_id', 'key']);
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('template_key', 40);
            $table->string('to_email');
            $table->string('subject', 255);
            $table->json('payload');
            $table->string('related_type', 40)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->enum('status', ['queued', 'sent', 'failed', 'skipped'])->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error_code', 40)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'id']);
            $table->index(['business_id', 'status', 'created_at']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->unsignedBigInteger('dealer_group_id')->nullable();
            $table->boolean('send_email')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'published_at']);
            $table->foreign(['business_id', 'dealer_group_id'])->references(['business_id', 'id'])->on('dealer_groups');
        });
    }

    public function down(): void
    {
        foreach (['announcements', 'email_logs', 'email_templates', 'email_settings', 'app_notifications', 'conversation_reads', 'messages', 'conversations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
