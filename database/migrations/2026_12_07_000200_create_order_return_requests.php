<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B2B iade talebi: bayi tamamlanmış siparişten talep açar, toptancı onaylar / reddeder, ürünler gelince teslim alır.
 * Teslim alınca mevcut iade kaydı (order_returns) oluşur: cari alacak + yalnızca tekrar satılabilir miktar için stok girişi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('dealer_relationship_id');
            $table->string('request_no', 30);
            $table->string('status', 20)->default('requested');
            $table->string('reason', 20);
            $table->string('dealer_note', 1000)->nullable();
            $table->string('business_note', 1000)->nullable();
            $table->string('reject_reason', 1000)->nullable();
            $table->unsignedBigInteger('order_return_id')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'id']);
            $table->unique(['business_id', 'request_no']);
            $table->unique('order_return_id');
            $table->index(['business_id', 'status']);
            $table->index(['dealer_relationship_id', 'id']);
            $table->index('order_id');
            $table->foreign(['business_id', 'order_id'], 'orr_order_fk')->references(['business_id', 'id'])->on('orders');
            $table->foreign(['business_id', 'dealer_relationship_id'], 'orr_relationship_fk')->references(['business_id', 'id'])->on('dealer_relationships');
            $table->foreign(['business_id', 'order_return_id'], 'orr_order_return_fk')->references(['business_id', 'id'])->on('order_returns');
            $table->foreign('decided_by', 'orr_decided_by_fk')->references('id')->on('business_users')->nullOnDelete();
        });

        Schema::create('order_return_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_return_request_id');
            $table->unsignedBigInteger('order_item_id');
            $table->decimal('quantity', 14, 3);
            $table->index('order_item_id');
            $table->foreign(['business_id', 'order_return_request_id'], 'orri_request_fk')->references(['business_id', 'id'])->on('order_return_requests')->cascadeOnDelete();
            $table->foreign('order_item_id', 'orri_order_item_fk')->references('id')->on('order_items');
        });
        DB::statement('ALTER TABLE order_return_request_items ADD CONSTRAINT order_return_request_items_qty_chk CHECK (quantity > 0)');

        // Teslim alınan iadede depoya geri giren (tekrar satılabilir) miktar; null: eski kayıtlar, tamamı girdi.
        Schema::table('order_return_items', function (Blueprint $table) {
            $table->decimal('restocked_quantity', 14, 3)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_return_items', function (Blueprint $table) {
            $table->dropColumn('restocked_quantity');
        });
        Schema::dropIfExists('order_return_request_items');
        Schema::dropIfExists('order_return_requests');
    }
};
