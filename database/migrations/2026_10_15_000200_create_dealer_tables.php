<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bayi işletmesi: toptancıdan bağımsız kimlik (birden fazla toptancıyla çalışabilir).
        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('tax_office')->nullable();
            $table->string('tax_number', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('city', 60)->nullable();
            $table->string('district', 60)->nullable();
            $table->string('address', 500)->nullable();
            $table->timestamps();
        });

        // K12: her bayide tek giriş hesabı.
        Schema::create('dealer_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->unique()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->enum('status', ['pending_activation', 'active', 'disabled'])->default('pending_activation');
            $table->timestamp('activated_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('dealer_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->timestamps();
            $table->unique(['business_id', 'name']);
            $table->unique(['business_id', 'id']);
        });

        Schema::create('dealer_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('dealer_id')->constrained()->restrictOnDelete();
            $table->string('dealer_code', 30);
            $table->unsignedBigInteger('dealer_group_id')->nullable();
            $table->enum('status', ['active', 'suspended', 'closed'])->default('active');
            $table->unsignedSmallInteger('payment_term_days')->default(30);
            $table->boolean('can_edit_addresses')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'dealer_id']);
            $table->unique(['business_id', 'dealer_code']);
            // Alt tabloların bileşik FK ile bağlanabilmesi için.
            $table->unique(['business_id', 'id']);
            $table->index(['business_id', 'status']);
            // Grup aynı işletmeye ait olmak zorunda (veritabanı seviyesinde tenant koruması).
            $table->foreign(['business_id', 'dealer_group_id'])
                ->references(['business_id', 'id'])->on('dealer_groups');
            $table->foreign('approved_by')->references('id')->on('business_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_relationships');
        Schema::dropIfExists('dealer_groups');
        Schema::dropIfExists('dealer_users');
        Schema::dropIfExists('dealers');
    }
};
