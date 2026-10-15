<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('address', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'code']);
            $table->unique(['business_id', 'id']);
        });

        Schema::table('business_settings', function (Blueprint $table) {
            // Varsayılan sevk deposu aynı işletmenin deposu olmak zorunda.
            $table->foreign(['business_id', 'default_warehouse_id'], 'business_settings_default_wh_fk')
                ->references(['business_id', 'id'])->on('warehouses');
        });

        // Anlık stok. Değeri yalnızca StockService değiştirir; hareket toplamına eşit olmak zorundadır.
        Schema::create('warehouse_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id']);
            $table->index(['business_id', 'product_id']);
            $table->foreign(['business_id', 'warehouse_id'])->references(['business_id', 'id'])->on('warehouses');
            $table->foreign(['business_id', 'product_id'])->references(['business_id', 'id'])->on('products');
        });
        DB::statement('ALTER TABLE warehouse_stock ADD CONSTRAINT warehouse_stock_non_negative CHECK (quantity >= 0)');

        // Yalnızca eklenir; güncellenmez, silinmez. Rezervasyon türü yoktur.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');
            $table->enum('type', [
                'opening', 'increase', 'decrease', 'sale', 'sale_reversal', 'return_in',
                'transfer_out', 'transfer_in', 'count_adjust', 'manual_adjust',
            ]);
            $table->decimal('quantity', 14, 3);
            $table->decimal('balance_after', 14, 3);
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->uuid('transfer_uuid')->nullable();
            $table->string('note', 500)->nullable();
            $table->string('actor_type', 20)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('source_key', 120)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'product_id', 'created_at']);
            $table->index(['business_id', 'warehouse_id', 'created_at']);
            $table->index(['business_id', 'type', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->foreign(['business_id', 'warehouse_id'])->references(['business_id', 'id'])->on('warehouses');
            $table->foreign(['business_id', 'product_id'])->references(['business_id', 'id'])->on('products');
        });
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_nonzero CHECK (quantity <> 0)');

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('warehouse_id');
            $table->enum('status', ['draft', 'applied', 'cancelled'])->default('draft');
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'id']);
            $table->index(['business_id', 'status']);
            $table->foreign(['business_id', 'warehouse_id'])->references(['business_id', 'id'])->on('warehouses');
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('stock_count_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('counted_qty', 14, 3);
            // Sayım uygulandığı andaki sistem stoku ve fark (taslakta boştur).
            $table->decimal('system_qty', 14, 3)->nullable();
            $table->decimal('difference', 14, 3)->nullable();
            $table->timestamps();
            $table->unique(['stock_count_id', 'product_id']);
            $table->foreign(['business_id', 'stock_count_id'])->references(['business_id', 'id'])->on('stock_counts')->cascadeOnDelete();
            $table->foreign(['business_id', 'product_id'])->references(['business_id', 'id'])->on('products');
        });

        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('original_name');
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'id']);
        });

        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('import_job_id');
            $table->unsignedInteger('row_no');
            $table->string('column', 60)->nullable();
            $table->string('message', 500);
            $table->foreign(['business_id', 'import_job_id'])->references(['business_id', 'id'])->on('import_jobs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', fn (Blueprint $t) => $t->dropForeign('business_settings_default_wh_fk'));
        foreach (['import_errors', 'import_jobs', 'stock_count_lines', 'stock_counts', 'stock_movements', 'warehouse_stock', 'warehouses'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
