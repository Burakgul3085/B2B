<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Özel ödeme kodları: yönetici tutarı belirler (ör. özel fiyatlı fotokopi, sitede olmayan ürün), müşteri kodu arama
 * kutusuna yazıp sepete ekler ve öder. Tutar sunucuda kodun kaydından gelir; müşteri tutar giremez. Kod tek kullanımlıktır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_payment_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('title', 150);
            $table->decimal('amount', 12, 2);
            $table->decimal('vat_rate', 5, 2)->default(20);
            $table->string('note', 500)->nullable(); // yalnızca yönetimde görünür
            $table->boolean('is_cancelled')->default(false);
            $table->timestamp('expires_at')->nullable();
            // Kodu kullanan son sipariş; sipariş iptal edilir veya ödemesi başarısız olursa kod yeniden kullanılabilir.
            $table->foreignId('order_id')->nullable()->constrained('store_orders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('store_admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('store_cart_items', function (Blueprint $table) {
            $table->foreignId('payment_code_id')->nullable()->after('print_job_id')->constrained('store_payment_codes')->cascadeOnDelete();
        });
        Schema::table('store_order_items', function (Blueprint $table) {
            $table->foreignId('payment_code_id')->nullable()->after('print_job_id')->constrained('store_payment_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('store_order_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('payment_code_id'));
        Schema::table('store_cart_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('payment_code_id'));
        Schema::dropIfExists('store_payment_codes');
    }
};
