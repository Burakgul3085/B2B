<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * Toptancının fatura ayarları (Toptancı → Ayarlar → Fatura Ayarları). Uyumsoft parolası şifreli tutulur ve modelden
 * düz metin olarak çıkmaz; çözme yalnızca App\Domain\Invoicing\InvoiceSettings içinde yapılır.
 */
class InvoiceSetting extends Model
{
    use BelongsToBusiness;

    public const DRIVERS = ['deneme' => 'Deneme (hiçbir yere gönderilmez)', 'uyumsoft' => 'Uyumsoft'];

    protected $guarded = ['id', 'business_id'];

    protected $hidden = ['uyumsoft_password'];

    protected function casts(): array
    {
        return [
            'delivery_vat' => 'decimal:2', 'auto_issue' => 'boolean', 'auto_since' => 'datetime', 'notify_dealer' => 'boolean',
        ];
    }
}
