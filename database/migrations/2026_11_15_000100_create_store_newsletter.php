<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** E-bülten: aboneler (iki adımlı onay, onay kaydı), toplu e-posta kampanyaları ve gönderim satırları. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('status', 12)->default('pending');   // pending | active | unsubscribed
            $table->string('token', 64)->unique();               // onay ve abonelikten çıkma bağlantısı
            $table->foreignId('customer_id')->nullable()->constrained('store_customers')->nullOnDelete();
            $table->string('source', 16)->default('footer');     // footer | account | register | admin
            $table->string('consent_text', 255)->nullable();     // onaylanan metin (İYS kaydı için)
            $table->string('consent_ip', 45)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });

        Schema::create('store_newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 150);
            $table->string('preheader', 150)->nullable();
            $table->longText('body');                             // Markdown
            $table->string('image_path')->nullable();
            $table->string('button_label', 40)->nullable();
            $table->string('button_url', 255)->nullable();
            $table->string('status', 12)->default('draft');      // draft | sending | sent | cancelled
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('store_newsletter_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('store_newsletter_campaigns')->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained('store_newsletter_subscribers')->cascadeOnDelete();
            $table->string('status', 10)->default('pending');    // pending | sent | failed | skipped
            $table->timestamp('sent_at')->nullable();
            $table->unique(['campaign_id', 'subscriber_id']);
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_newsletter_deliveries');
        Schema::dropIfExists('store_newsletter_campaigns');
        Schema::dropIfExists('store_newsletter_subscribers');
    }
};
