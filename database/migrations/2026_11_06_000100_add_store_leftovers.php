<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mağaza küçük eksikleri (docs/23-magaza-eksikler-raporu.md):
 *  - İade / iptal faturası (elle; e-Arşiv bağlanınca otomatik). Bir siparişte birden fazla olabilir.
 *  - Kupon kapsamı: belirli kategoriler (alt kategorileriyle) ve/veya belirli ürünler.
 *  - Toplu kupon kodları: aynı kurallara bağlı, her biri tek kullanımlık kodlar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('store_returns')->nullOnDelete();
            $table->string('number', 30)->unique();
            $table->date('issued_on');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('file_path', 255)->nullable();
            $table->string('created_by_type', 20)->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'id']);
        });

        Schema::table('store_discounts', function (Blueprint $table) {
            $table->json('category_ids')->nullable()->after('max_discount'); // boş: tüm ürünler
            $table->json('product_ids')->nullable()->after('category_ids');
            $table->boolean('codes_only')->default(false)->after('product_ids'); // ana kod kabul edilmez, yalnızca toplu kodlar
        });

        Schema::create('store_discount_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained('store_discounts')->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('store_discount_redemptions', function (Blueprint $table) {
            $table->string('code', 40)->nullable()->after('discount_id'); // kullanılan kod (toplu kodlarda tek kullanım denetimi)
            $table->index(['code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('store_discount_redemptions', function (Blueprint $table) {
            $table->dropIndex(['code', 'status']);
            $table->dropColumn('code');
        });
        Schema::dropIfExists('store_discount_codes');
        Schema::table('store_discounts', fn (Blueprint $table) => $table->dropColumn(['category_ids', 'product_ids', 'codes_only']));
        Schema::dropIfExists('store_credit_notes');
    }
};
