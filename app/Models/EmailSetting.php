<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/** İşletmenin Gmail gönderici ayarı. Uygulama şifresi şifreli saklanır ve hiçbir yanıtta yer almaz. */
class EmailSetting extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['from_email', 'from_name', 'app_password_encrypted', 'status', 'last_tested_at', 'last_error_code', 'payment_reminders'];

    protected $hidden = ['app_password_encrypted'];

    protected function casts(): array
    {
        return ['last_tested_at' => 'datetime', 'payment_reminders' => 'boolean'];
    }
}
