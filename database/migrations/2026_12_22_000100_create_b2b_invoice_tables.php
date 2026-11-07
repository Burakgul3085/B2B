<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B2B faturaları (e-Fatura / e-Arşiv, Uyumsoft). Mağaza faturalarından tamamen ayrıdır (store_e_invoices ile ilişkisi yok).
 *
 *  - invoice_settings: toptancının fatura ayarları (sağlayıcı, Uyumsoft web servis kullanıcısı, şifreli parola, seriler,
 *    otomatik kesim). Şifre şifreli saklanır.
 *  - invoices: satış ve iade faturaları. Tutarlar siparişin (iadede iade kaydının) kendi tutarlarından birebir gelir;
 *    fatura, bayinin carisine yazılan ve ödediği tutarla aynıdır. Elle (Uyumsoft portalında) kesilen fatura da
 *    source = manual olarak kaydedilir; böylece aynı siparişe ikinci fatura kesilmez.
 *  - invoice_lines: kalemler (KDV hariç birim fiyat, KDV hariç iskonto, KDV oranı ve hesaplanmış tutarlar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->restrictOnDelete();
            $table->string('driver', 12)->default('deneme');           // deneme | uyumsoft
            $table->string('uyumsoft_mode', 8)->default('test');       // test | live
            $table->string('uyumsoft_username', 100)->nullable();
            $table->text('uyumsoft_password')->nullable();               // şifreli
            $table->string('sender_id', 11)->nullable();                // gönderici VKN / TCKN (boşsa işletmenin vergi no)
            $table->string('seller_name', 150)->nullable();             // faturadaki satıcı adı (boşsa işletme unvanı)
            $table->string('tax_office', 100)->nullable();              // boşsa işletmenin vergi dairesi
            $table->char('series_efatura', 3)->nullable();              // bayi e-Fatura serisi (Uyumsoft'ta tanımlı)
            $table->char('series_earsiv', 3)->nullable();               // bayi e-Arşiv serisi
            $table->decimal('delivery_vat', 5, 2)->default(20);         // teslimat ücreti kaleminin KDV oranı
            $table->boolean('auto_issue')->default(false);              // ödeme alınınca kendiliğinden kes
            $table->timestamp('auto_since')->nullable();                // otomatik kesim bu andan sonraki ödemelere uygulanır
            $table->boolean('notify_dealer')->default(true);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_return_id')->nullable();
            $table->string('kind', 10);                  // sale | refund
            $table->string('status', 12);                // draft | sending | unknown | sent | cancelled | failed
            $table->string('source', 10)->default('system'); // system (buradan kesildi) | manual (Uyumsoft portalında elle kesildi)
            $table->string('provider', 20)->nullable();  // deneme | uyumsoft | manual
            $table->string('document_type', 10)->nullable(); // e_arsiv | e_fatura
            $table->string('local_id', 40)->unique();    // sağlayıcıya giden yerel belge kimliği
            // Alıcı: bayinin kayıtlı bilgilerinin fatura anındaki kopyası (taslakta düzeltilebilir)
            $table->string('buyer_name', 160);
            $table->string('buyer_tax_number', 11)->nullable();
            $table->string('buyer_tax_office', 80)->nullable();
            $table->string('buyer_address', 500);
            $table->string('buyer_district', 60)->nullable();
            $table->string('buyer_city', 60);
            $table->string('buyer_email', 160)->nullable();
            $table->string('buyer_phone', 30)->nullable();
            $table->string('note', 500)->nullable();
            $table->date('issue_date');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('expected_total', 15, 2);   // siparişin / iadenin tutarı (fatura bununla aynı olmalı)
            $table->string('invoice_no', 30)->nullable();
            $table->string('ettn', 60)->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->string('provider_status', 30)->nullable();
            $table->timestamp('provider_checked_at')->nullable();
            $table->boolean('notify_dealer')->default(true);
            $table->timestamp('dealer_notified_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 300)->nullable();
            $table->string('created_by_type', 20)->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('sent_by_id')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'id']);
            $table->unique(['business_id', 'invoice_no']);
            $table->index(['business_id', 'status', 'id']);
            $table->index(['order_id', 'kind']);
            $table->foreign(['business_id', 'order_id'])->references(['business_id', 'id'])->on('orders');
            $table->foreign(['business_id', 'order_return_id'])->references(['business_id', 'id'])->on('order_returns');
        });
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_kind_chk CHECK (kind IN ('sale', 'refund') AND (kind = 'refund' OR order_return_id IS NULL))");
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_amount_chk CHECK (total >= 0 AND expected_total >= 0)');
        // Çift fatura veritabanı düzeyinde de engellenir: bir siparişin (iadede bir iade kaydının / iptal edilen siparişin)
        // aynı anda yalnızca bir açık belgesi olabilir. İptal edilen / reddedilen belge yeni belgeye engel olmaz.
        DB::statement("ALTER TABLE invoices ADD COLUMN open_key VARCHAR(40) GENERATED ALWAYS AS (CASE WHEN status IN ('cancelled', 'failed') THEN NULL "
            ."WHEN kind = 'sale' THEN CONCAT('S', order_id) ELSE CONCAT('R', order_id, '-', COALESCE(order_return_id, 0)) END) STORED");
        DB::statement('ALTER TABLE invoices ADD UNIQUE invoices_open_unique (business_id, open_key)');

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedSmallInteger('position');
            $table->string('name', 255);
            $table->string('sku', 64)->nullable();
            $table->string('unit_code', 10)->nullable();   // ürünün birim kodu (ADET, KG ...)
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 15, 4);          // KDV hariç birim fiyat
            $table->decimal('discount', 15, 2)->default(0); // KDV hariç satır iskontosu (fiyat listesi + kampanya)
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('net_amount', 15, 2);          // KDV hariç satır tutarı (iskonto düşülmüş)
            $table->decimal('vat_amount', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->index(['invoice_id', 'position']);
            $table->foreign(['business_id', 'invoice_id'])->references(['business_id', 'id'])->on('invoices')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->nullOnDelete();
        });
        DB::statement('ALTER TABLE invoice_lines ADD CONSTRAINT invoice_lines_amount_chk CHECK (quantity > 0 AND net_amount >= 0 AND vat_amount >= 0 AND line_total = net_amount + vat_amount)');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_settings');
    }
};
