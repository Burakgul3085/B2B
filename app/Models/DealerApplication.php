<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DealerApplication extends Model
{
    use BelongsToBusiness;

    public const STATUSES = [
        'new' => 'Yeni başvuru',
        'in_review' => 'İnceleniyor',
        'waiting_info' => 'Ek bilgi bekleniyor',
        'approved' => 'Onaylandı',
        'rejected' => 'Reddedildi',
        'cancelled' => 'İptal edildi',
    ];

    /** Açık (karar verilmemiş) durumlar. */
    public const OPEN = ['new', 'in_review', 'waiting_info'];

    public const BUSINESS_TYPES = [
        'retail' => 'Perakende kırtasiye',
        'bookstore' => 'Kitabevi',
        'office_supply' => 'Ofis malzemeleri',
        'wholesale' => 'Alt toptancı',
        'institution' => 'Kurum / okul',
        'online' => 'İnternet satışı',
        'other' => 'Diğer',
    ];

    protected $fillable = [
        'legal_name', 'contact_name', 'phone', 'email', 'city', 'district', 'address', 'business_type',
        'activity_area', 'tax_office', 'tax_number', 'note', 'status', 'info_request_note', 'decision_note',
        'consent_text_version', 'consent_at', 'ip', 'user_agent', 'reviewed_by', 'reviewed_at', 'dealer_relationship_id',
    ];

    protected function casts(): array
    {
        return ['consent_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(DealerRelationship::class, 'dealer_relationship_id');
    }

    public function statusTone(): string
    {
        return ['new' => 'info', 'in_review' => 'primary', 'waiting_info' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'neutral'][$this->status];
    }
}
