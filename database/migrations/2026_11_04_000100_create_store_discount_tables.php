<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mağaza kampanya ve kuponları (M8, docs/21-magaza-m8-raporu.md).
 *  - Tek tablo: kodu olan indirim kupondur (müşteri girer); kodu olmayan otomatik sepet kampanyasıdır.
 *  - Siparişte tek indirim uygulanır. İndirim ürün satırlarına paylaştırılır; KDV, iyzico sepeti ve iade tutarı bu paylarla hesaplanır.
 *  - Kullanım kayıtları sipariş iptal edilir veya ödeme alınamazsa geçersiz sayılır (kullanım hakkı geri döner).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 40)->nullable()->unique(); // boş: otomatik kampanya
            $table->string('type', 20); // percent | fixed | free_shipping
            $table->decimal('value', 12, 2)->nullable();
            $table->decimal('min_subtotal', 12, 2)->nullable();
            $table->decimal('max_discount', 12, 2)->nullable(); // yüzde indirimde üst sınır
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();        // toplam kullanım
            $table->unsignedInteger('per_customer_limit')->nullable(); // aynı e-posta veya telefon
            $table->boolean('first_order_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'code']);
        });

        Schema::create('store_discount_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained('store_discounts')->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained('store_orders')->cascadeOnDelete();
            $table->string('email');
            $table->string('phone', 20);
            $table->decimal('amount', 12, 2); // ürün indirimi + (ücretsiz kargoda) teslimat indirimi
            $table->string('status', 10)->default('active'); // active | void
            $table->timestamp('created_at')->useCurrent();
            $table->index(['discount_id', 'status']);
            $table->index('email');
            $table->index('phone');
        });

        Schema::table('store_orders', function (Blueprint $table) {
            $table->foreignId('discount_id')->nullable()->after('cart_id')->constrained('store_discounts')->nullOnDelete();
            $table->string('discount_code', 40)->nullable()->after('discount_id');
            $table->string('discount_name', 120)->nullable()->after('discount_code');
            $table->decimal('discount_total', 12, 2)->default(0)->after('subtotal'); // ürünlerden düşülen indirim (KDV dahil)
        });

        Schema::table('store_order_items', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->default(0)->after('line_total'); // satıra düşen indirim payı
        });

        Schema::table('store_carts', function (Blueprint $table) {
            $table->string('coupon_code', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_carts', fn (Blueprint $table) => $table->dropColumn('coupon_code'));
        Schema::table('store_order_items', fn (Blueprint $table) => $table->dropColumn('discount_amount'));
        Schema::table('store_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_id');
            $table->dropColumn(['discount_code', 'discount_name', 'discount_total']);
        });
        Schema::dropIfExists('store_discount_redemptions');
        Schema::dropIfExists('store_discounts');
    }
};
