<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** İletişim mesajlarına yönetimden e-postayla gönderilen yanıtlar. Mesaj silinince (KVKK) yanıtları da silinir. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_contact_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_message_id')->constrained('store_contact_messages')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('store_admins')->nullOnDelete();
            $table->string('admin_name', 120);
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_contact_replies');
    }
};
