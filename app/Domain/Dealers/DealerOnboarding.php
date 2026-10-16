<?php

namespace App\Domain\Dealers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Area;
use App\Domain\Auth\AuthTokenService;
use App\Domain\Files\DocumentUploader;
use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use App\Models\CurrentAccount;
use App\Models\Dealer;
use App\Models\DealerApplication;
use App\Models\DealerRelationship;
use App\Models\DealerUser;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bayilik başvurusu ve bayi hesabı açma (docs/03-is-kurallari.md §1).
 * Parola hiçbir zaman e-postayla gönderilmez; bayi tek kullanımlık bağlantıyla kendisi belirler.
 */
class DealerOnboarding
{
    public const CONSENT_VERSION = '2026-10';

    public function __construct(
        private TenantContext $tenant,
        private AuditLogger $audit,
        private AuthTokenService $tokens,
        private DocumentUploader $documents,
    ) {}

    /**
     * Herkese açık formdan gelen başvuru. Çağıran, işletme bağlamını kurmuş olmalıdır.
     *
     * @param  UploadedFile[]  $files
     */
    public function submit(array $data, array $files, ?string $ip, ?string $userAgent): DealerApplication
    {
        $email = Str::lower(trim($data['email']));

        if (DealerApplication::where('email', $email)->whereIn('status', DealerApplication::OPEN)->exists()) {
            throw new DomainException('Bu e-posta adresiyle değerlendirmede olan bir başvurunuz zaten var. Sonuç size bildirilecek.');
        }

        return DB::transaction(function () use ($data, $files, $ip, $userAgent, $email) {
            $application = DealerApplication::create([
                ...collect($data)->only(['legal_name', 'contact_name', 'phone', 'city', 'district', 'address', 'business_type', 'activity_area', 'tax_office', 'tax_number', 'note'])->all(),
                'email' => $email,
                'status' => 'new',
                'consent_text_version' => self::CONSENT_VERSION,
                'consent_at' => now(),
                'ip' => $ip,
                'user_agent' => Str::limit((string) $userAgent, 250, ''),
            ]);

            foreach ($files as $file) {
                $this->documents->store($file, 'businesses/'.$this->tenant->businessId().'/applications/'.$application->id,
                    'dealer_application', $application->id);
            }

            $this->audit->log('dealer_application.submitted', $application, [], ['email' => $email]);

            return $application;
        });
    }

    public function markInReview(DealerApplication $application, int $reviewerId): void
    {
        $this->transition($application, ['new', 'waiting_info'], 'in_review', $reviewerId);
    }

    public function requestInfo(DealerApplication $application, string $note, int $reviewerId): void
    {
        $this->transition($application, ['new', 'in_review'], 'waiting_info', $reviewerId, ['info_request_note' => $note]);
    }

    public function reject(DealerApplication $application, string $reason, int $reviewerId): void
    {
        $this->transition($application, DealerApplication::OPEN, 'rejected', $reviewerId, ['decision_note' => $reason]);
    }

    public function cancel(DealerApplication $application, int $reviewerId): void
    {
        $this->transition($application, DealerApplication::OPEN, 'cancelled', $reviewerId);
    }

    /**
     * Başvuruyu onaylar: bayi (yoksa) + giriş hesabı (yoksa) + ilişki + cari hesap tek transaction'da.
     *
     * @return array{relationship: DealerRelationship, activation_url: ?string, existing_account: bool}
     */
    public function approve(DealerApplication $application, int $reviewerId, array $terms = []): array
    {
        return DB::transaction(function () use ($application, $reviewerId, $terms) {
            $application = DealerApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if (! $application->isOpen()) {
                throw new DomainException('Bu başvuru zaten sonuçlanmış.');
            }

            $result = $this->onboard([
                'legal_name' => $application->legal_name, 'tax_office' => $application->tax_office, 'tax_number' => $application->tax_number,
                'phone' => $application->phone, 'email' => $application->email, 'city' => $application->city,
                'district' => $application->district, 'address' => $application->address,
            ], $application->contact_name, $reviewerId, $terms);

            $application->update([
                'status' => 'approved', 'reviewed_by' => $reviewerId, 'reviewed_at' => now(),
                'dealer_relationship_id' => $result['relationship']->id,
            ]);
            $this->audit->log('dealer_application.approved', $application, [], ['dealer_relationship_id' => $result['relationship']->id]);

            return $result;
        });
    }

    /**
     * Toptancının başvuru olmadan doğrudan bayi hesabı açması.
     *
     * @return array{relationship: DealerRelationship, activation_url: ?string, existing_account: bool}
     */
    public function createDirect(array $dealer, string $contactName, int $creatorId, array $terms = []): array
    {
        return DB::transaction(fn () => $this->onboard($dealer, $contactName, $creatorId, $terms));
    }

    /** Henüz etkinleştirilmemiş bayi hesabı için yeni aktivasyon bağlantısı. */
    public function newActivationLink(DealerRelationship $relationship): string
    {
        $user = DealerUser::where('dealer_id', $relationship->dealer_id)->firstOrFail();
        if ($user->status !== 'pending_activation') {
            throw new DomainException('Bayi hesabı zaten etkinleştirilmiş.');
        }

        $plain = $this->tokens->issue(Area::Dealer, $user->id, 'activation');
        $this->audit->log('dealer.activation_link_created', $relationship);

        return $this->tokens->url(Area::Dealer, 'activation', $plain);
    }

    /**
     * Henüz etkinleştirilmemiş bayi hesabının hatalı girilmiş e-postasını düzeltir ve yeni aktivasyon bağlantısı üretir.
     * Etkin hesapta veya başka bir toptancıyla da ilişkili hesapta değiştirilemez (hesap bayiye aittir).
     */
    public function correctPendingEmail(DealerRelationship $relationship, string $newEmail): string
    {
        return DB::transaction(function () use ($relationship, $newEmail) {
            $user = DealerUser::where('dealer_id', $relationship->dealer_id)->lockForUpdate()->firstOrFail();
            if ($user->status !== 'pending_activation') {
                throw new DomainException('Bayi hesabını etkinleştirdi; giriş e-postasını yalnızca bayi kendisi veya platform yöneticisi değiştirebilir.');
            }
            if (DealerRelationship::withoutGlobalScopes()->where('dealer_id', $relationship->dealer_id)->count() > 1) {
                throw new DomainException('Bu bayi hesabı başka bir toptancıyla da ilişkili; e-posta değişikliği için platform yöneticisine başvurun.');
            }

            $email = Str::lower(trim($newEmail));
            if (DealerUser::where('email', $email)->whereKeyNot($user->id)->exists()) {
                throw new DomainException('Bu e-posta adresi başka bir bayi hesabında kullanılıyor.');
            }

            $old = $user->email;
            $user->forceFill(['email' => $email])->save();
            // Firma e-postası giriş e-postasıyla aynı girilmişse o da düzeltilir.
            if (Str::lower((string) $relationship->dealer->email) === Str::lower($old)) {
                $relationship->dealer->forceFill(['email' => $email])->save();
            }
            $this->audit->log('dealer.pending_email_corrected', $relationship, ['email' => $old], ['email' => $email]);

            return $this->newActivationLink($relationship);
        });
    }

    private function onboard(array $dealerData, string $contactName, int $actorId, array $terms): array
    {
        $business = Business::with('licenses')->findOrFail($this->tenant->businessId());
        $this->assertWithinLicense($business);

        $email = Str::lower(trim($dealerData['email']));
        $user = DealerUser::where('email', $email)->lockForUpdate()->first();
        $existing = $user !== null;

        if ($existing) {
            // K11: aynı bayi başka toptancıyla da çalışıyor; yeni hesap açılmaz, ilişki eklenir.
            if (DealerRelationship::where('dealer_id', $user->dealer_id)->exists()) {
                throw new DomainException('Bu e-posta adresine sahip bayi zaten bayiniz.');
            }
            $dealer = $user->dealer;
        } else {
            $dealer = Dealer::create(collect($dealerData)->only(['legal_name', 'tax_office', 'tax_number', 'phone', 'city', 'district', 'address'])->all() + ['email' => $email]);
            $user = DealerUser::create([
                'dealer_id' => $dealer->id, 'name' => $contactName, 'email' => $email, 'status' => 'pending_activation',
            ]);
        }

        $code = $terms['dealer_code'] ?? $this->nextDealerCode();
        if (DealerRelationship::where('dealer_code', $code)->exists()) {
            throw new DomainException("\"{$code}\" bayi kodu kullanımda.");
        }

        $relationship = DealerRelationship::create([
            'dealer_id' => $dealer->id,
            'dealer_code' => $code,
            'dealer_group_id' => $terms['dealer_group_id'] ?? null,
            'status' => 'active',
            'payment_term_days' => $terms['payment_term_days'] ?? $business->settings?->default_payment_term_days ?? 30,
            'can_edit_addresses' => $terms['can_edit_addresses'] ?? true,
            'approved_at' => now(),
            'approved_by' => $actorId,
        ]);

        CurrentAccount::create(['dealer_relationship_id' => $relationship->id, 'code' => $code]);

        // İlk sevk adresi bayinin işletme adresinden oluşturulur.
        if (filled($dealerData['address'] ?? null) && filled($dealerData['city'] ?? null)) {
            $relationship->addresses()->create([
                'title' => 'Merkez', 'recipient_name' => $contactName, 'phone' => $dealerData['phone'] ?? '',
                'city' => $dealerData['city'], 'district' => $dealerData['district'] ?? '', 'address_line' => $dealerData['address'],
                'is_default' => true,
            ]);
        }

        $url = null;
        if (! $existing) {
            $url = $this->tokens->url(Area::Dealer, 'activation', $this->tokens->issue(Area::Dealer, $user->id, 'activation'));
        }

        $this->audit->log('dealer.onboarded', $relationship, [], ['email' => $email, 'existing_account' => $existing, 'dealer_code' => $code]);

        return ['relationship' => $relationship, 'activation_url' => $url, 'existing_account' => $existing];
    }

    private function assertWithinLicense(Business $business): void
    {
        $limit = $business->currentLicense()?->max_dealers;

        if ($limit !== null && DealerRelationship::whereIn('status', ['active', 'suspended'])->count() >= $limit) {
            throw new DomainException("Lisansınızdaki bayi sınırına ({$limit}) ulaşıldı. Platform yöneticinizle iletişime geçin.");
        }
    }

    private function nextDealerCode(): string
    {
        $n = DealerRelationship::count() + 1;
        do {
            $code = 'B'.str_pad((string) $n++, 4, '0', STR_PAD_LEFT);
        } while (DealerRelationship::where('dealer_code', $code)->exists());

        return $code;
    }

    private function transition(DealerApplication $application, array $from, string $to, int $reviewerId, array $extra = []): void
    {
        DB::transaction(function () use ($application, $from, $to, $reviewerId, $extra) {
            $locked = DealerApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, $from, true)) {
                throw new DomainException('Başvuru bu durumdayken bu işlem yapılamaz.');
            }

            $old = $locked->status;
            $locked->update(['status' => $to, 'reviewed_by' => $reviewerId, 'reviewed_at' => now()] + $extra);
            $this->audit->log('dealer_application.'.$to, $locked, ['status' => $old], ['status' => $to] + $extra);
            $application->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
