<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Üretimde sahte ticari veri oluşturulmaz. Geliştirme verisi yalnızca local ortamda yüklenir.
     */
    public function run(): void
    {
        if (app()->environment('local')) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
