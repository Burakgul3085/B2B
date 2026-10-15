<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    protected $table = 'order_status_history';

    protected $fillable = ['order_id', 'from_status', 'to_status', 'actor_type', 'actor_id', 'note'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
