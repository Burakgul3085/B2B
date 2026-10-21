<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mağaza stoğu (M3, docs/13-magaza-mimarisi.md §8.2). B2B stoğundan bağımsızdır.
 *  - store_stock_movements: yalnızca eklenir; düzeltme ters kayıtla yapılır. Varyant satırındaki stock_quantity
 *    her hareketle aynı transaction'da güncellenen önbellektir (hareket toplamına eşit).
 *  - store_stock_holds: ödeme sırasında geçici ayırma (yalnızca mağazada vardır). Süresi dolan ayırma kendiliğinden
 *    geçersiz sayılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_product_variants', function (Blueprint $table) {
            $table->unsignedInteger('stock_quantity')->default(0)->after('weight_grams');
            $table->unsignedInteger('critical_stock')->nullable()->after('stock_quantity');
            $table->index('stock_quantity');
        });
        DB::statement('ALTER TABLE store_product_variants ADD CONSTRAINT store_variants_stock_not_negative CHECK (stock_quantity >= 0)');

        Schema::create('store_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('store_product_variants')->restrictOnDelete();
            $table->string('type', 20); // receipt | count | adjustment | sale | return | reversal
            $table->integer('quantity'); // işaretli
            $table->unsignedInteger('balance_after');
            $table->string('note', 500)->nullable();
            $table->string('source_key', 120)->nullable()->unique(); // aynı işlem ikinci kez uygulanamaz
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('reverses_id')->nullable()->unique()->constrained('store_stock_movements')->restrictOnDelete();
            $table->string('actor_type', 20)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['variant_id', 'id']);
            $table->index(['type', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('store_stock_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('store_product_variants')->cascadeOnDelete();
            $table->string('holder', 120); // ör. "checkout:abc123"
            $table->unsignedInteger('quantity');
            $table->timestamp('expires_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->unique(['holder', 'variant_id']);
            $table->index(['variant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_stock_holds');
        Schema::dropIfExists('store_stock_movements');
        DB::statement('ALTER TABLE store_product_variants DROP CHECK store_variants_stock_not_negative');
        Schema::table('store_product_variants', function (Blueprint $table) {
            $table->dropIndex(['stock_quantity']);
            $table->dropColumn(['stock_quantity', 'critical_stock']);
        });
    }
};
