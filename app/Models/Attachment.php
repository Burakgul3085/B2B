<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    protected $fillable = ['attachable_type', 'attachable_id', 'stored_path', 'original_name', 'mime', 'size', 'sha256', 'uploaded_by_type', 'uploaded_by_id'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
