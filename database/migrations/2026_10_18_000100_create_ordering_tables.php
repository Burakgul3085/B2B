<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sepet, sipariş, ürün talebi ve cari hareketleri (docs/03-is-kurallari.md §3, §5, §6).
 * Rezervasyon yoktur: sepet ve sipariş stok ayırmaz, stok yalnızca onayda düşer.
 */
return new class extends Migration
{
    public function up(): void
    {
        // İşletme bazında sıra numaraları (sipariş no). Satır kilidiyle artırılır.
        Schema::create('business_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('key', 40);
            $table->unsignedBigInteger('value')->default(0);
            $table->unique(['business_id', 'key']);
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('dealer_relationship_id')->unique();
            $table->timestamps();
            $table->unique(['business_id', 'id']);
            $table->foreign(['business_id', 'dealer_relationship_id'])->references(['business_id', 'id'])->on('dealer_relationships');
        });

        // Fiyat saklanmaz; her görüntülemede güncel kurallarla hesaplanır.
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('cart_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 14, 3);
            $table->timestamps();
            $table->unique(['cart_id', 'product_id']);
            $table->foreign(['business_id', 'cart_id'])->references(['business_id', 'id'])->on('carts')->cascadeOnDelete();
            $table->foreign(['business_id', 'product_id'])->references(['business_id', 'id'])->on('products');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('order_no', 30);
            $table->unsignedBigInteger('dealer_relationship_id');
            $table->enum('status', ['submitted', 'in_review', 'approved', 'preparing', 'completed', 'rejected', 'cancel_requested', 'cancelled']);
            $table->boolean('is_partial')->default(false);
            $table->uuid('submit_token')->unique();
            $table->unsignedBigInteger('warehouse_id');

            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount_total', 15, 2);
            $table->decimal('net_total', 15, 2);
            $table->decimal('vat_total', 15, 2);
            $table->decimal('grand_total', 15, 2);
            $table->char('currency', 3)->default('TRY');

            $table->text('dealer_note')->nullable();

            // Sevk adresinin sipariş anındaki kopyası: bayi adresini değiştirse de sipariş değişmez.
            $table->unsignedBigInteger('ship_address_id')->nullable();
            $table->string('ship_title', 60);
            $table->string('ship_recipient', 120);
            $table->string('ship_phone', 30);
            $table->string('ship_city', 60);
            $table->string('ship_district', 60);
            $table->string('ship_address', 500);
            $table->string('ship_note', 255)->nullable();

            // Toptancının onayda girdiği teslimat bilgileri (kargo entegrasyonu yoktur).
            $table->string('delivery_recipient', 120)->nullable();
            $table->string('delivery_phone', 30)->nullable();
            $table->string('delivery_note', 500)->nullable();
            $table->date('delivery_planned_on')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->string('cancel_requested_from', 20)->nullable();
            $table->timestamp('stock_applied_at')->nullable();
            $table->timestamp('stock_reversed_at')->nullable();
            $table->timestamp('ledger_applied_at')->nullable();
            $table->timestamp('ledger_reversed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'order_no']);
            $table->unique(['business_id', 'id']);
            $table->index(['business_id', 'status', 'created_at']);
            $table->index(['business_id', 'dealer_relationship_id', 'created_at']);
            $table->foreign(['business_id', 'dealer_relationship_id'])->references(['business_id', 'id'])->on('dealer_relationships');
            $table->foreign(['business_id', 'warehouse_id'])->references(['business_id', 'id'])->on('warehouses');
            $table->foreign('approved_by')->references('id')->on('business_users')->nullOnDelete();
        });
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_totals_chk CHECK (grand_total >= 0 AND net_total = subtotal - discount_total AND grand_total = net_total + vat_total)');

        // Sipariş kalemi: ürün adı, kodu, birim, KDV ve fiyat sipariş anındaki haliyle saklanır.
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->string('sku', 64);
            $table->string('barcode', 64)->nullable();
            $table->string('name');
            $table->string('unit_name', 40);
            $table->decimal('vat_rate', 5, 2);
            $table->string('price_source', 20);
            $table->string('discount_name', 120)->nullable();
            $table->decimal('quantity_requested', 14, 3);
            $table->decimal('quantity_approved', 14, 3)->nullable();
            $table->decimal('list_unit_price', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('line_subtotal', 15, 2);
            $table->decimal('line_discount', 15, 2);
            $table->decimal('line_net', 15, 2);
            $table->decimal('line_vat', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
            $table->unique(['order_id', 'product_id']);
            $table->index(['business_id', 'product_id']);
            $table->foreign(['business_id', 'order_id'])->references(['business_id', 'id'])->on('orders')->cascadeOnDelete();
            $table->foreign(['business_id', 'product_id'])->references(['business_id', 'id'])->on('products');
        });
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_qty_chk CHECK (quantity_requested > 0 AND (quantity_approved IS NULL OR (quantity_approved >= 0 AND quantity_approved <= quantity_requested)))');

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('actor_type', 20)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'id']);
            $table->foreign(['business_id', 'order_id'])->references(['business_id', 'id'])->on('orders')->cascadeOnDelete();
        });

        Schema::create('product_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('dealer_relationship_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 14, 3);
            $table->string('note', 1000)->nullable();
            $table->enum('status', ['new', 'in_review', 'available', 'unavailable', 'stock_updated', 'closed'])->default('new');
            $table->boolean('notify_when_in_stock')->default(true);
            $table->timestamp('notified_at')->nullable();
            $table->string('response_note', 1000)->nullable();
            $table->unsignedBigInteger('responded_by')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'id']);
            $table->index(['business_id', 'status', 'created_at']);
            $table->index(['business_id', 'product_id', 'status']);
            $table->foreign(['business_id', 'dealer_relationship_id'])->references(['business_id', 'id'])->on('dealer_relationships');
            $table->foreign(['business_id', 'product_id'])->references(['business_id', 'id'])->on('products');
        });
        DB::statement('ALTER TABLE product_requests ADD CONSTRAINT product_requests_qty_chk CHECK (quantity > 0)');

        // Cari hareketleri yalnızca eklenir. Bakiye (pozitif = bayi borçlu) = SUM(debit) - SUM(credit).
        Schema::create('current_account_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('current_account_id');
            $table->enum('type', ['opening', 'order_debit', 'collection', 'return_credit', 'adjustment_debit', 'adjustment_credit', 'reversal']);
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->date('movement_date');
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('collection_id')->nullable();
            $table->unsignedBigInteger('reverses_movement_id')->nullable()->unique();
            $table->string('description', 500)->nullable();
            $table->string('actor_type', 20)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('source_key', 120)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['current_account_id', 'movement_date']);
            $table->index(['business_id', 'due_date']);
            $table->foreign(['business_id', 'current_account_id'])->references(['business_id', 'id'])->on('current_accounts');
            $table->foreign(['business_id', 'order_id'])->references(['business_id', 'id'])->on('orders');
            $table->foreign('reverses_movement_id')->references('id')->on('current_account_movements');
        });
        DB::statement('ALTER TABLE current_account_movements ADD CONSTRAINT cam_amount_chk CHECK (debit >= 0 AND credit >= 0 AND (debit > 0) <> (credit > 0))');
    }

    public function down(): void
    {
        foreach (['current_account_movements', 'product_requests', 'order_status_history', 'order_items', 'orders', 'cart_items', 'carts', 'business_counters'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
