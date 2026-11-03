<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B2B kupon ve kampanyaları (docs/03-is-kurallari.md §4.4): toptancı bayilerine sipariş indirimi tanımlar.
 *  - promotions: kodlu (kupon) veya kodsuz (otomatik kampanya) sipariş indirimi.
 *  - promotion_redemptions: kullanım kaydı; sipariş reddedilir/iptal edilirse released_at dolar (kullanım hakkı geri döner).
 *  - carts.promotion_code: bayinin sepette girdiği kod (indirim her görüntülemede yeniden hesaplanır).
 *  - orders.promotion_*: siparişe uygulanan indirimin anlık kopyası (kampanya sonradan değişse de sipariş değişmez).
 *  - order_items.order_discount: sipariş indiriminin bu satıra düşen payı (KDV hariç); line_net bu pay düşülmüş tutardır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('code', 40)->nullable();
            $table->enum('type', ['percent', 'fixed']);
            $table->decimal('value', 15, 2);
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->decimal('min_subtotal', 15, 2)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_dealer_limit')->nullable();
            $table->enum('target', ['all', 'group', 'dealers'])->default('all');
            $table->unsignedBigInteger('dealer_group_id')->nullable();
            $table->json('dealer_ids')->nullable();
            $table->json('category_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'id']);
            $table->unique(['business_id', 'code']);
            $table->index(['business_id', 'is_active']);
            $table->foreign(['business_id', 'dealer_group_id'])->references(['business_id', 'id'])->on('dealer_groups');
        });
        DB::statement('ALTER TABLE promotions ADD CONSTRAINT promotions_value_chk CHECK (value > 0 AND (type <> \'percent\' OR value < 100))');

        Schema::create('promotion_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('promotion_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('dealer_relationship_id');
            $table->decimal('amount', 15, 2);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique('order_id');
            $table->index(['promotion_id', 'released_at']);
            $table->foreign(['business_id', 'promotion_id'])->references(['business_id', 'id'])->on('promotions');
            $table->foreign(['business_id', 'order_id'])->references(['business_id', 'id'])->on('orders');
            $table->foreign(['business_id', 'dealer_relationship_id'])->references(['business_id', 'id'])->on('dealer_relationships');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->string('promotion_code', 40)->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->string('promotion_name', 120)->nullable();
            $table->string('promotion_code', 40)->nullable();
            $table->enum('promotion_type', ['percent', 'fixed'])->nullable();
            $table->decimal('promotion_value', 15, 2)->nullable();
            $table->decimal('promotion_max', 15, 2)->nullable();
            $table->decimal('promotion_discount', 15, 2)->default(0);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('promotion_eligible')->default(false);
            $table->decimal('order_discount', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['promotion_eligible', 'order_discount']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['promotion_id', 'promotion_name', 'promotion_code', 'promotion_type', 'promotion_value', 'promotion_max', 'promotion_discount']);
        });
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('promotion_code');
        });
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('promotions');
    }
};
