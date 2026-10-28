<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fotokopi / baskı (docs/13-magaza-mimarisi.md): müşterinin yüklediği belge (store_print_files) ve seçenekleriyle
 * fiyatlanmış baskı işi (store_print_jobs). Baskı işi sepete ve siparişe ürün gibi bir satırla girer: sepet ve
 * sipariş kaleminde ürün seçeneği yerine baskı işi bağlıdır (variant_id boş olabilir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_print_files', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();
            $table->foreignId('cart_id')->nullable()->constrained('store_carts')->nullOnDelete();
            $table->string('original_name', 200);
            $table->string('extension', 10);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size');
            $table->unsignedBigInteger('received')->default(0);
            $table->string('path', 255);
            $table->string('pdf_path', 255)->nullable();
            $table->string('preview_path', 255)->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->string('status', 20)->default('uploading'); // uploading | processing | ready | failed | deleted
            $table->string('error', 300)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('store_print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('store_print_files')->restrictOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained('store_carts')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('store_orders')->nullOnDelete();
            $table->json('options');
            $table->unsignedInteger('pages');
            $table->unsignedInteger('sides');
            $table->unsignedInteger('sheets');
            $table->unsignedInteger('copies');
            $table->decimal('price', 12, 2); // KDV dahil, tüm kopyalar
            $table->json('breakdown');
            $table->timestamps();
        });

        Schema::table('store_cart_items', function (Blueprint $table) {
            $table->foreignId('variant_id')->nullable()->change();
            $table->foreignId('print_job_id')->nullable()->after('variant_id')->constrained('store_print_jobs')->cascadeOnDelete();
        });

        Schema::table('store_order_items', function (Blueprint $table) {
            $table->foreignId('print_job_id')->nullable()->after('variant_id')->constrained('store_print_jobs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('store_order_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('print_job_id'));
        Schema::table('store_cart_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('print_job_id');
        });
        Schema::dropIfExists('store_print_jobs');
        Schema::dropIfExists('store_print_files');
    }
};
