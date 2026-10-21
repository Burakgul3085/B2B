<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Türkiye il ve ilçeleri (sabit başvuru verisi). İl kimliği plaka kodudur (Eskişehir = 26).
 * Kaynak: database/data/turkiye-il-ilce.json (81 il, 973 ilçe; Vikipedi "Türkiye'nin ilçeleri", 2026).
 * Adreslerde il ve ilçe serbest metin değildir; kapıda ödeme bölge kontrolü bu kimliklere dayanır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_provinces', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary(); // plaka kodu
            $table->string('name', 40);
        });

        Schema::create('tr_districts', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedTinyInteger('province_id');
            $table->string('name', 60);
            $table->foreign('province_id')->references('id')->on('tr_provinces')->restrictOnDelete();
            $table->unique(['province_id', 'name']);
        });

        $data = json_decode(file_get_contents(database_path('data/turkiye-il-ilce.json')), true, flags: JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($data) {
            foreach ($data as $plate => $province) {
                DB::table('tr_provinces')->insert(['id' => (int) $plate, 'name' => $province['name']]);
                DB::table('tr_districts')->insert(array_map(fn ($name) => ['province_id' => (int) $plate, 'name' => $name], $province['districts']));
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_districts');
        Schema::dropIfExists('tr_provinces');
    }
};
