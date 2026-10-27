<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Toptancı açılırken "Kısa ad" alanına adres yazılmıştı (perilikaya-mah-98140-no-cd-26c); başvuru formu adresi
 * /basvuru/yapboz-kirtasiye olur. Eski adresler config b2b.renamed_slugs ile yenisine yönlenir.
 */
return new class extends Migration
{
    private const OLD = 'perilikaya-mah-98140-no-cd-26c';

    private const NEW = 'yapboz-kirtasiye';

    public function up(): void
    {
        if (DB::table('businesses')->where('slug', self::NEW)->exists()) {
            return;
        }
        DB::table('businesses')->where('slug', self::OLD)->update(['slug' => self::NEW, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('businesses')->where('slug', self::NEW)->update(['slug' => self::OLD]);
    }
};
