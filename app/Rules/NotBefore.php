<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * Tarih, başka bir alandaki tarihten önce olamaz (bitiş ≥ başlangıç).
 *
 * Laravel'in `after_or_equal:alan` kuralı karşı alan dizi olarak gelirse TypeError (500) üretir;
 * bu kural yalnızca iki değer de geçerli tarih metniyse karşılaştırır. Biçim hataları ilgili
 * alanların kendi `date` kuralıyla raporlanır.
 */
class NotBefore implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function __construct(private string $otherField, private string $otherLabel = 'başlangıç tarihi') {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $other = data_get($this->data, $this->otherField);
        if (! is_string($value) || ! is_string($other) || strtotime($value) === false || strtotime($other) === false) {
            return;
        }

        if (Carbon::parse($value)->lt(Carbon::parse($other))) {
            $fail(":attribute, {$this->otherLabel} tarihinden önce olamaz.");
        }
    }
}
