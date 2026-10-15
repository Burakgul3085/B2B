<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cari: manuel tahsilat ve sipariş iadesi (docs/03-is-kurallari.md §3.6, §6).
 * Sistem ödeme almaz, bankayı kontrol etmez; tahsilat toptancının elle girdiği kayıttır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('current_account_id');
            $table->date('collected_on');
            $table->decimal('amount', 15, 2);
            $table->enum('method', ['cash', 'transfer', 'check', 'promissory_note', 'card_pos', 'other']);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'id']);
            $table->index(['business_id', 'collected_on']);
            $table->index(['current_account_id', 'collected_on']);
            $table->foreign(['business_id', 'current_account_id'])->references(['business_id', 'id'])->on('current_accounts');
            $table->foreign(['business_id', 'order_id'])->references(['business_id', 'id'])->on('orders');
            $table->foreign('created_by')->references('id')->on('business_users')->nullOnDelete();
        });
        DB::statement('ALTER TABLE collections ADD CONSTRAINT collections_amount_chk CHECK (amount > 0)');

        Schema::table('current_account_movements', function (Blueprint $table) {
            $table->foreign(['business_id', 'collection_id'], 'cam_collection_fk')->references(['business_id', 'id'])->on('collections');
        });

        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->string('note', 500)->nullable();
            $table->decimal('net_total', 15, 2);
            $table->decimal('vat_total', 15, 2);
            $table->decimal('grand_total', 15, 2);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['business_id', 'id']);
            $table->foreign(['business_id', 'order_id'])->references(['business_id', 'id'])->on('orders');
            $table->foreign('created_by')->references('id')->on('business_users')->nullOnDelete();
        });

        Schema::create('order_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_return_id');
            $table->unsignedBigInteger('order_item_id');
            $table->decimal('quantity', 14, 3);
            $table->decimal('line_net', 15, 2);
            $table->decimal('line_vat', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->index('order_item_id');
            $table->foreign(['business_id', 'order_return_id'])->references(['business_id', 'id'])->on('order_returns')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items');
        });
        DB::statement('ALTER TABLE order_return_items ADD CONSTRAINT order_return_items_qty_chk CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
        Schema::dropIfExists('order_returns');
        Schema::table('current_account_movements', fn (Blueprint $t) => $t->dropForeign('cam_collection_fk'));
        Schema::dropIfExists('collections');
    }
};
