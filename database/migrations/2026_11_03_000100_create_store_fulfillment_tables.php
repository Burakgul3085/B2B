<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mağaza teslimat, iade ve fatura (M7, docs/20-magaza-m7-raporu.md).
 *  - Kargo firmaları: ad ve takip bağlantısı şablonu; gönderide firma ve takip bağlantısı siparişe kopyalanır.
 *  - İade talepleri (cayma hakkı) ve kalemleri; teslim alınan sağlam ürün stoğa döner.
 *  - Para iadeleri: kartla ödemede iyzico üzerinden (işlem bazında), kapıda ödemede havaleyle (elle işaretlenir).
 *  - Fatura: numara, tarih ve PDF (elle). e-Arşiv sağlayıcısı (Uyumsoft) bağlanınca aynı tablo kullanılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_carriers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('tracking_url', 300)->nullable(); // {takip} yerine takip numarası yazılır
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('store_orders', function (Blueprint $table) {
            $table->foreignId('carrier_id')->nullable()->after('cart_id')->constrained('store_carriers')->nullOnDelete();
            $table->string('tracking_url', 400)->nullable()->after('tracking_number');
            $table->decimal('refunded_total', 12, 2)->default(0)->after('vat_total');
        });

        // iyzico kalem bazında işlem kimlikleri (iade bunlar üzerinden yapılır) ve her birinden iade edilen tutar.
        Schema::table('store_payments', function (Blueprint $table) {
            $table->json('item_transactions')->nullable()->after('raw');
        });

        Schema::create('store_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 20)->unique();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->string('status', 20); // bkz. StoreReturn::STATUSES
            $table->string('reason', 30);
            $table->string('customer_note', 500)->nullable();
            $table->string('admin_note', 500)->nullable();
            $table->string('refund_iban', 34)->nullable();         // kapıda ödemede iade hesabı
            $table->string('refund_account_name', 120)->nullable();
            $table->decimal('refund_amount', 12, 2)->nullable();   // iade edilen tutar
            $table->string('created_by_type', 20);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('store_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('store_returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('store_order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('restocked_quantity')->default(0); // teslim alınırken sağlam bulunan adet
            $table->unique(['return_id', 'order_item_id']);
        });

        Schema::create('store_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('store_returns')->nullOnDelete();
            $table->string('method', 10);  // iyzico | manual
            $table->string('status', 12);  // succeeded | failed
            $table->decimal('amount', 12, 2);
            $table->string('note', 300)->nullable();
            $table->json('transactions')->nullable(); // iyzico: [{transaction_id, amount, status, error}]
            $table->string('error_message', 300)->nullable();
            $table->string('created_by_type', 20)->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'id']);
        });

        Schema::create('store_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('store_orders')->cascadeOnDelete();
            $table->string('invoice_no', 30)->unique();
            $table->date('issued_on');
            $table->string('provider', 20)->default('manual'); // manual | uyumsoft
            $table->string('file_path', 255)->nullable();
            $table->string('provider_uuid', 60)->nullable();
            $table->string('created_by_type', 20)->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();
        });

        $now = now();
        foreach (['Yurtiçi Kargo', 'Aras Kargo', 'MNG Kargo', 'PTT Kargo', 'Sürat Kargo'] as $i => $name) {
            DB::table('store_carriers')->insert(['name' => $name, 'is_active' => true, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_invoices');
        Schema::dropIfExists('store_refunds');
        Schema::dropIfExists('store_return_items');
        Schema::dropIfExists('store_returns');
        Schema::table('store_payments', fn (Blueprint $table) => $table->dropColumn('item_transactions'));
        Schema::table('store_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carrier_id');
            $table->dropColumn(['tracking_url', 'refunded_total']);
        });
        Schema::dropIfExists('store_carriers');
    }
};
