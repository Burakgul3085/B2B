<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealer_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('legal_name');
            $table->string('contact_name', 120);
            $table->string('phone', 30);
            $table->string('email');
            $table->string('city', 60);
            $table->string('district', 60);
            $table->string('address', 500);
            $table->string('business_type', 40)->nullable();
            $table->string('activity_area', 160)->nullable();
            $table->string('tax_office', 120)->nullable();
            $table->string('tax_number', 20)->nullable();
            $table->text('note')->nullable();
            $table->enum('status', ['new', 'in_review', 'waiting_info', 'approved', 'rejected', 'cancelled'])->default('new');
            $table->text('info_request_note')->nullable();
            $table->text('decision_note')->nullable();
            $table->string('consent_text_version', 20);
            $table->timestamp('consent_at');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('dealer_relationship_id')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'id']);
            $table->index(['business_id', 'status', 'created_at']);
            $table->index(['business_id', 'email']);
            $table->foreign('reviewed_by')->references('id')->on('business_users')->nullOnDelete();
            $table->foreign(['business_id', 'dealer_relationship_id'])->references(['business_id', 'id'])->on('dealer_relationships');
        });

        // Başvuru belgeleri ve (ileride) mesaj ekleri. Dosyalar private diskte, yetki kontrollü sunulur.
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('attachable_type', 40);
            $table->unsignedBigInteger('attachable_id');
            $table->string('stored_path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->char('sha256', 64);
            $table->string('uploaded_by_type', 20)->nullable();
            $table->unsignedBigInteger('uploaded_by_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_id', 'attachable_type', 'attachable_id']);
        });

        // Sevk adresleri ilişki (toptancı–bayi) bazındadır: her toptancı yalnızca kendisiyle paylaşılan adresleri görür.
        Schema::create('dealer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('dealer_relationship_id');
            $table->string('title', 60);
            $table->string('recipient_name', 120);
            $table->string('phone', 30);
            $table->string('city', 60);
            $table->string('district', 60);
            $table->string('address_line', 500);
            $table->string('note', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'id']);
            $table->index(['dealer_relationship_id', 'archived_at']);
            $table->foreign(['business_id', 'dealer_relationship_id'])->references(['business_id', 'id'])->on('dealer_relationships');
        });

        // Cari hesap ilişki başına tek. Hareketler Aşama 6'da eklenir; bakiye onların özetidir.
        Schema::create('current_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('dealer_relationship_id')->unique();
            $table->string('code', 30);
            $table->decimal('balance', 15, 2)->default(0);
            $table->char('currency', 3)->default('TRY');
            $table->timestamps();
            $table->unique(['business_id', 'code']);
            $table->unique(['business_id', 'id']);
            $table->foreign(['business_id', 'dealer_relationship_id'])->references(['business_id', 'id'])->on('dealer_relationships');
        });
    }

    public function down(): void
    {
        foreach (['current_accounts', 'dealer_addresses', 'attachments', 'dealer_applications'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
