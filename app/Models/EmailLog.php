<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use BelongsToBusiness;

    public const STATUSES = ['queued' => 'Kuyrukta', 'sent' => 'Gönderildi', 'failed' => 'Gönderilemedi', 'skipped' => 'Atlandı (e-posta ayarı yok)'];

    protected $fillable = ['template_key', 'to_email', 'subject', 'payload', 'related_type', 'related_id', 'status', 'attempts', 'last_error_code', 'sent_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'sent_at' => 'datetime'];
    }
}
