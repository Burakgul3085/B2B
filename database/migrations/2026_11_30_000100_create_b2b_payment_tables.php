<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B2B ödemeleri: bayi siparişi onaylandıktan sonra onaylanan tutarı kartla (iyzico) veya havaleyle öder; izin verilen
 * bayiler açık hesapla (cari) ya da teslimde ödemeyle sipariş verebilir. Ödeme, carisine otomatik tahsilat yazar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('payments_enabled')->default(false)->after('new_products_since');
            $table->boolean('card_enabled')->default(false)->after('payments_enabled');
            $table->string('iyzico_mode', 10)->default('sandbox')->after('card_enabled');
            $table->text('iyzico_api_key')->nullable()->after('iyzico_mode'); // şifreli
            $table->text('iyzico_secret_key')->nullable()->after('iyzico_api_key'); // şifreli
            $table->string('installments', 40)->default('1')->after('iyzico_secret_key');
            $table->boolean('transfer_enabled')->default(false)->after('installments');
            $table->json('transfer_accounts')->nullable()->after('transfer_enabled');
            $table->decimal('delivery_fee', 12, 2)->default(0)->after('transfer_accounts');
            $table->decimal('delivery_free_over', 15, 2)->nullable()->after('delivery_fee');
        });

        Schema::table('dealer_relationships', function (Blueprint $table) {
            $table->boolean('allow_open_account')->default(false)->after('can_edit_addresses');
            $table->boolean('allow_on_delivery')->default(false)->after('allow_open_account');
        });

        Schema::table('orders', function (Blueprint $table) {
            // null: ödeme sistemi kapalıyken verilen sipariş (eski davranış, cari).
            $table->string('payment_method', 20)->nullable()->after('grand_total');
            // null: ödeme beklenmiyor (açık hesap) | awaiting | paid | refunded
            $table->string('payment_status', 20)->nullable()->after('payment_method');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
            $table->decimal('delivery_fee', 12, 2)->default(0)->after('vat_total');
            // Onay öncesi (istenen miktarlarla) genel toplam; onayda tutar değiştiyse bayiye gösterilir.
            $table->decimal('requested_total', 15, 2)->nullable()->after('grand_total');
            $table->index(['business_id', 'payment_status']);
        });

        // Genel toplam artık teslimat ücretini de içerir.
        DB::statement('ALTER TABLE orders DROP CHECK orders_totals_chk');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_totals_chk CHECK (grand_total >= 0 AND delivery_fee >= 0 AND net_total = subtotal - discount_total AND grand_total = net_total + vat_total + delivery_fee)');

        DB::statement("ALTER TABLE collections MODIFY method ENUM('cash', 'transfer', 'check', 'promissory_note', 'card_pos', 'card_online', 'other') NOT NULL");

        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->string('driver', 20); // iyzico | manual
            $table->string('status', 20); // initialized | succeeded | failed | refunded
            $table->decimal('amount', 15, 2);
            $table->string('method', 20)->nullable(); // manual: transfer | cash | card_pos
            $table->string('token', 120)->nullable()->unique();
            $table->string('provider_payment_id', 40)->nullable();
            $table->string('transaction_id', 40)->nullable(); // iade için sepet kaleminin işlem kimliği
            $table->unsignedTinyInteger('installment')->nullable();
            $table->string('card_family', 40)->nullable();
            $table->char('card_last_four', 4)->nullable();
            $table->string('error_code', 40)->nullable();
            $table->string('error_message', 300)->nullable();
            $table->json('raw')->nullable();
            $table->unsignedBigInteger('collection_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'order_id']);
            $table->index(['status', 'created_at']);
            $table->foreign(['business_id', 'order_id'])->references(['business_id', 'id'])->on('orders');
            $table->foreign('created_by')->references('id')->on('business_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
        DB::statement("ALTER TABLE collections MODIFY method ENUM('cash', 'transfer', 'check', 'promissory_note', 'card_pos', 'other') NOT NULL");
        DB::statement('ALTER TABLE orders DROP CHECK orders_totals_chk');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_totals_chk CHECK (grand_total >= 0 AND net_total = subtotal - discount_total AND grand_total = net_total + vat_total)');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'payment_status']);
            $table->dropColumn(['payment_method', 'payment_status', 'paid_at', 'delivery_fee', 'requested_total']);
        });
        Schema::table('dealer_relationships', fn (Blueprint $table) => $table->dropColumn(['allow_open_account', 'allow_on_delivery']));
        Schema::table('business_settings', fn (Blueprint $table) => $table->dropColumn([
            'payments_enabled', 'card_enabled', 'iyzico_mode', 'iyzico_api_key', 'iyzico_secret_key', 'installments',
            'transfer_enabled', 'transfer_accounts', 'delivery_fee', 'delivery_free_over',
        ]));
    }
};
