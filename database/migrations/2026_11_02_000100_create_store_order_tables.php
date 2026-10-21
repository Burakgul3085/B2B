<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mağaza siparişleri ve ödeme (M6, docs/13-magaza-mimarisi.md §8.3–8.5, §9–10).
 *  - Sipariş, adres, fatura ve fiyat bilgilerini anlık kopya olarak saklar; ürün veya adres sonradan değişse de sipariş değişmez.
 *  - Ödeme denemeleri (başarısızlar dahil) store_payments'ta tutulur. Kart verisi hiçbir zaman sistemimize gelmez.
 *  - Yasal metinler sürümlüdür; siparişte onaylanan metnin o anki hâli ve onay kaydı saklanır.
 *  - Kapıda ödeme için SMS doğrulama kodları özetlenmiş (hash) saklanır; teslim almayanlar engel listesine girer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 20)->unique();
            $table->string('access_token', 40)->unique(); // misafir ve e-posta bağlantısı için
            $table->foreignId('customer_id')->nullable()->constrained('store_customers')->nullOnDelete();
            $table->string('email');
            $table->string('phone', 20);
            $table->string('status', 30);          // bkz. StoreOrder::STATUSES
            $table->string('payment_method', 10);  // card | cod
            $table->string('payment_status', 20);  // bkz. StoreOrder::PAYMENT_STATUSES
            $table->string('delivery_method', 10); // cargo | local

            // Teslimat adresi (anlık kopya)
            $table->string('shipping_name', 120);
            $table->string('shipping_phone', 20);
            $table->unsignedTinyInteger('shipping_province_id');
            $table->unsignedSmallInteger('shipping_district_id');
            $table->string('shipping_province', 40);
            $table->string('shipping_district', 60);
            $table->string('shipping_neighborhood', 100)->nullable();
            $table->string('shipping_address', 300);
            $table->string('shipping_postal_code', 10)->nullable();

            // Fatura bilgisi (anlık kopya)
            $table->string('billing_type', 12); // individual | corporate
            $table->string('billing_name', 160);
            $table->string('billing_tax_office', 80)->nullable();
            $table->string('billing_tax_number', 11)->nullable(); // TCKN (11) veya VKN (10)
            $table->string('billing_address', 500);

            // Tutarlar (KDV dahil)
            $table->decimal('subtotal', 12, 2);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('cod_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('vat_total', 12, 2);

            $table->string('customer_note', 500)->nullable();
            $table->string('stock_holder', 120)->nullable(); // ödeme sırasında stok ayırma anahtarı
            $table->unsignedBigInteger('cart_id')->nullable(); // kart ödemesi başarılı olunca boşaltılacak sepet
            $table->string('carrier', 60)->nullable();
            $table->string('tracking_number', 60)->nullable();
            $table->string('collected_method', 10)->nullable(); // kapıda: cash | card
            $table->string('cancel_reason', 300)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index('phone');
        });

        Schema::create('store_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('store_product_variants')->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name', 200);
            $table->string('option_label', 160)->nullable(); // "Renk: Mavi"
            $table->string('sku', 64);
            $table->decimal('unit_price', 12, 2); // KDV dahil
            $table->decimal('vat_rate', 5, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->decimal('vat_amount', 12, 2);
        });

        Schema::create('store_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->string('status', 30);
            $table->string('note', 500)->nullable();
            $table->string('actor_type', 20)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'id']);
        });

        Schema::create('store_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->string('driver', 20); // iyzico | cod
            $table->string('status', 20); // initialized | succeeded | failed
            $table->decimal('amount', 12, 2);
            $table->string('token', 120)->nullable()->unique();   // iyzico ödeme formu belirteci
            $table->string('provider_payment_id', 60)->nullable();
            $table->unsignedTinyInteger('installment')->nullable();
            $table->string('card_family', 40)->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('error_code', 40)->nullable();
            $table->string('error_message', 300)->nullable();
            $table->json('raw')->nullable(); // sağlayıcı yanıtı (kart verisi içermez)
            $table->timestamps();
            $table->index(['order_id', 'id']);
        });

        Schema::create('store_legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40); // on-bilgilendirme | mesafeli-satis
            $table->unsignedInteger('version');
            $table->string('title', 160);
            $table->longText('body');
            $table->string('created_by_type', 20)->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['key', 'version']);
        });

        Schema::create('store_order_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->string('document_key', 40);
            $table->unsignedInteger('version');
            $table->longText('body'); // siparişe özel doldurulmuş metin
            $table->string('ip', 45)->nullable();
            $table->timestamp('accepted_at');
        });

        Schema::create('store_otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('purpose', 20); // cod
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['phone', 'purpose', 'created_at']);
        });

        Schema::create('store_sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 20);
            $table->string('phone', 20);
            $table->string('purpose', 30);
            $table->string('body', 500); // doğrulama kodları maskelenir
            $table->string('status', 20); // sent | failed
            $table->string('provider_ref', 60)->nullable();
            $table->string('error', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('store_cod_blocklist', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('reason', 300)->nullable();
            $table->foreignId('order_id')->nullable()->constrained('store_orders')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        foreach (require database_path('data/store-legal-templates.php') as $key => [$title, $body]) {
            DB::table('store_legal_documents')->insert(['key' => $key, 'version' => 1, 'title' => $title, 'body' => $body, 'created_at' => $now]);
        }
    }

    public function down(): void
    {
        foreach (['store_cod_blocklist', 'store_sms_messages', 'store_otp_codes', 'store_order_consents', 'store_legal_documents',
            'store_payments', 'store_order_events', 'store_order_items', 'store_orders'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
