<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class ConversationRead extends Model
{
    use BelongsToBusiness;

    public $timestamps = false;

    protected $fillable = ['conversation_id', 'side', 'last_read_message_id'];
}
