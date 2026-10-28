<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * e-Fatura / e-Arşiv (Uyumsoft): yönetimde elle hazırlanıp gönderilen faturalar. Ödeme alınınca fatura kesilmez;
 * yönetici siparişten taslak oluşturur, kalemleri (miktar, fiyat, KDV) düzenleyip gönderir. Gönderilen fatura
 * store_invoices (satış) veya store_credit_notes (iade) kaydına da yazılır; müşteri tarafı aynı kalır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_e_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('store_returns')->nullOnDelete();
            $table->string('kind', 10);              // sale | refund
            $table->string('status', 12);            // draft | sending | unknown | sent | cancelled (bkz. StoreEInvoice::STATUSES)
            $table->string('provider', 20)->nullable(); // deneme | uyumsoft (gönderimde)
            $table->string('document_type', 10)->nullable(); // e_arsiv | e_fatura (gönderimde belirlenir)
            $table->string('local_id', 40)->unique(); // sağlayıcıya giden yerel belge kimliği (çift gönderimi önler)
            // Alıcı (siparişten dolu gelir, düzenlenebilir)
            $table->string('buyer_type', 12);        // individual | corporate
            $table->string('buyer_name', 160);
            $table->string('buyer_tax_office', 80)->nullable();
            $table->string('buyer_tax_number', 11)->nullable(); // TCKN (11) / VKN (10)
            $table->string('buyer_address', 500);
            $table->string('buyer_district', 60)->nullable();
            $table->string('buyer_city', 40);
            $table->string('buyer_email', 160)->nullable();
            $table->string('buyer_phone', 20)->nullable();
            $table->string('note', 500)->nullable(); // faturada görünen açıklama
            $table->date('issue_date');
            // Tutarlar sunucuda kalemlerden hesaplanır (KDV hariç, KDV, KDV dahil)
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('expected_total', 12, 2); // siparişte ödenen / iade edilen tutar (fark uyarısı)
            $table->timestamp('difference_confirmed_at')->nullable();
            // Gönderim sonucu
            $table->string('invoice_no', 30)->nullable()->unique();
            $table->string('ettn', 60)->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->boolean('notify_customer')->default(true);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 300)->nullable();
            $table->string('created_by_type', 20)->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('sent_by_id')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
            $table->index(['order_id', 'kind']);
        });

        Schema::create('store_e_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('store_e_invoices')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('store_order_items')->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name', 200);
            $table->string('sku', 64)->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2); // KDV dahil birim fiyat (mağazadaki gibi)
            $table->decimal('discount', 12, 2)->default(0); // satır indirimi (KDV dahil)
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('net_amount', 12, 2); // KDV hariç satır tutarı (indirim düşülmüş)
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('line_total', 12, 2); // KDV dahil satır tutarı (indirim düşülmüş)
            $table->index(['invoice_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_e_invoice_lines');
        Schema::dropIfExists('store_e_invoices');
    }
};
