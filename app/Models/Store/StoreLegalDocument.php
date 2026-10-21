<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

/** Sürümlü yasal metin (Ön Bilgilendirme Formu, Mesafeli Satış Sözleşmesi). Düzenleme yeni sürüm oluşturur. */
class StoreLegalDocument extends Model
{
    public const UPDATED_AT = null;

    public const KEYS = ['on-bilgilendirme' => 'Ön Bilgilendirme Formu', 'mesafeli-satis' => 'Mesafeli Satış Sözleşmesi'];

    protected $guarded = ['id'];

    public static function current(string $key): self
    {
        return static::where('key', $key)->orderByDesc('version')->firstOrFail();
    }
}
