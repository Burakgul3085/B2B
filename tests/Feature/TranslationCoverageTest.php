<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** Eksik çeviri anahtarı kullanıcıya "validation.after" gibi ham metin olarak görünür; Türkçe dosyalar eksiksiz olmalı. */
class TranslationCoverageTest extends TestCase
{
    public function test_turkish_validation_and_pagination_cover_every_laravel_key(): void
    {
        foreach (['validation', 'pagination'] as $file) {
            $en = array_keys(Arr::dot(require base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$file}.php")));
            $tr = array_keys(Arr::dot(require lang_path("tr/{$file}.php")));
            $missing = array_values(array_filter(array_diff($en, $tr), fn ($k) => ! str_starts_with($k, 'custom') && ! str_starts_with($k, 'attributes')));

            $this->assertSame([], $missing, "lang/tr/{$file}.php eksik anahtarlar: ".implode(', ', $missing));
        }
    }

    public function test_messages_are_rendered_in_turkish(): void
    {
        $this->assertSame('tr', app()->getLocale());
        $errors = Validator::make(['bitis' => '2026-01-01', 'adres' => 'yok'], ['bitis' => 'after:2026-02-01', 'adres' => 'url'],
            [], ['bitis' => 'bitiş', 'adres' => 'adres'])->errors();

        $this->assertSame('bitiş, 2026-02-01 tarihinden sonra olmalıdır.', $errors->first('bitis'));
        $this->assertSame('adres geçerli bir adres olmalıdır.', $errors->first('adres'));
    }
}
