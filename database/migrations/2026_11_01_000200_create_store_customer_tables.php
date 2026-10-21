<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mağaza müşterileri, adres defteri ve sepet (M5). B2B bayi hesaplarından tamamen ayrıdır.
 *  - Sepet: misafir sepeti tarayıcıdaki belirteçle (token), üye sepeti müşteriye bağlıdır; girişte birleştirilir.
 *    Sepette fiyat tutulmaz: fiyat ve stok her gösterimde sunucuda yeniden hesaplanır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 60);
            $table->string('last_name', 60);
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('kvkk_accepted_at')->nullable();
            $table->timestamp('marketing_consent_at')->nullable(); // ticari ileti izni (İYS'ye bildirim M9+)
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('store_customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('store_customers')->cascadeOnDelete();
            $table->string('title', 40); // Ev, İş…
            $table->string('first_name', 60);
            $table->string('last_name', 60);
            $table->string('phone', 20);
            $table->unsignedTinyInteger('province_id');
            $table->unsignedSmallInteger('district_id');
            $table->string('neighborhood', 100)->nullable();
            $table->string('address_line', 300);
            $table->string('postal_code', 10)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->foreign('province_id')->references('id')->on('tr_provinces')->restrictOnDelete();
            $table->foreign('district_id')->references('id')->on('tr_districts')->restrictOnDelete();
            $table->index(['customer_id', 'is_default']);
        });

        Schema::create('store_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->unique()->constrained('store_customers')->cascadeOnDelete();
            $table->string('token', 64)->nullable()->unique(); // misafir sepeti
            $table->timestamps();
            $table->index('updated_at');
        });

        Schema::create('store_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('store_carts')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('store_product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['cart_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        foreach (['store_cart_items', 'store_carts', 'store_customer_addresses', 'store_customers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
