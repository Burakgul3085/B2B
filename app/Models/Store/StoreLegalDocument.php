<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

/** Sürümlü yasal metin. Düzenleme yeni sürüm oluşturur; siparişte onaylanan sürüm saklanır. */
class StoreLegalDocument extends Model
{
    public const UPDATED_AT = null;

    public const KEYS = [
        'on-bilgilendirme' => 'Ön Bilgilendirme Formu',
        'mesafeli-satis' => 'Mesafeli Satış Sözleşmesi',
        'iptal-iade' => 'İptal ve İade Koşulları',
        'kvkk' => 'KVKK Aydınlatma Metni',
        'gizlilik-cerez' => 'Gizlilik ve Çerez Politikası',
    ];

    /** Ödeme adımında onaylanan ve siparişe kopyalanan metinler. */
    public const CHECKOUT_KEYS = ['on-bilgilendirme', 'mesafeli-satis'];

    protected $guarded = ['id'];

    public static function current(string $key): self
    {
        return static::where('key', $key)->orderByDesc('version')->firstOrFail();
    }
}
