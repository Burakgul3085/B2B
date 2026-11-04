<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün seçenekleri (docs/03-is-kurallari.md §2.2): varyant grupları sayfası kalktı; seçenek adı (ör. Renk) ve
 * değeri (ör. Kırmızı) ürünün kendisinde durur. Aynı ürünün seçenekleri `variant_group_id` ile bağlı kalır
 * (grup kaydını sistem kendisi oluşturur; toptancı görmez). Her seçenek kendi kodu, stoğu ve fiyatı olan ayrı üründür.
 * Mevcut grup özellikleri ve değerleri yeni alanlara taşınır (birden çok özellik " / " ile birleşir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('option_name', 40)->nullable()->after('variant_values');
            $table->string('option_value', 60)->nullable()->after('option_name');
        });

        $groups = DB::table('variant_groups')->get(['id', 'attribute_names'])->keyBy('id');

        DB::table('products')->whereNotNull('variant_group_id')->orderBy('id')
            ->each(function ($p) use ($groups) {
                $names = json_decode((string) ($groups[$p->variant_group_id]->attribute_names ?? '[]'), true) ?: [];
                $values = json_decode((string) $p->variant_values, true) ?: [];
                $parts = array_values(array_filter(array_map(fn ($n) => isset($values[$n]) ? trim((string) $values[$n]) : '', $names), 'strlen'));

                DB::table('products')->where('id', $p->id)->update([
                    'option_name' => mb_substr(implode(' / ', $names), 0, 40) ?: null,
                    'option_value' => mb_substr(implode(' / ', $parts), 0, 60) ?: null,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['option_name', 'option_value']);
        });
    }
};
