<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessModule extends Model
{
    /** Platform yöneticisinin işletme bazında açıp kapatabildiği modüller. */
    public const AVAILABLE = [
        'messaging' => 'Mesajlaşma',
        'email' => 'E-posta bildirimleri',
        'product_requests' => 'Stokta olmayan ürün talepleri',
        'reports' => 'Raporlama merkezi',
        'import' => 'Excel/CSV içe aktarma',
    ];

    protected $fillable = ['business_id', 'module_key', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
