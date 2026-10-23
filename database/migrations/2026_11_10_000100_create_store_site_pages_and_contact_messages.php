<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mağazanın sabit sayfaları (Hakkımızda, İletişim) ve iletişim formu mesajları.
 * Serbest sayfa oluşturma kaldırıldı: her sayfa kodda tanımlıdır (App\Domain\Store\Content\SitePages), içeriği
 * yönetimdeki kendi formuyla düzenlenir ve JSON olarak saklanır. Eski store_pages (serbest Markdown sayfaları) kalkar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('store_pages');

        Schema::create('store_pages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();          // about | contact
            $table->boolean('is_published')->default(false);
            $table->json('content')->nullable();
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamps();
        });

        Schema::create('store_contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('subject', 120);
            $table->text('message');
            $table->string('status', 12)->default('new')->index(); // new | read | replied
            $table->timestamp('read_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_contact_messages');
        Schema::dropIfExists('store_pages');

        Schema::create('store_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->longText('body')->nullable();
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_footer')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }
};
