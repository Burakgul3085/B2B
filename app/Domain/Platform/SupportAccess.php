<?php

namespace App\Domain\Platform;

use App\Domain\Audit\AuditLogger;
use App\Domain\Mail\SystemMailer;
use App\Models\Business;
use App\Models\PlatformAdmin;
use App\Models\SupportAccessGrant;
use DomainException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Süreli destek erişimi. Platform yöneticisi işletmenin ticari verisini (sipariş, bayi, ürün) varsayılan olarak göremez;
 * toptancı belirli bir yöneticiye 1–72 saatlik salt okunur erişim verir. Her görüntüleme işletmenin işlem geçmişine
 * yazılır; toptancı erişimi istediği an kaldırabilir.
 */
class SupportAccess
{
    public const DURATIONS = [1 => '1 saat', 4 => '4 saat', 24 => '24 saat', 72 => '3 gün'];

    public function __construct(private AuditLogger $audit) {}

    /** @throws DomainException|ValidationException */
    public function grant(int $businessId, int $grantedBy, array $input): SupportAccessGrant
    {
        $data = Validator::make($input, [
            'platform_admin_id' => ['required', 'integer'],
            'hours' => ['required', 'in:'.implode(',', array_keys(self::DURATIONS))],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['platform_admin_id' => 'platform yöneticisi', 'hours' => 'süre', 'reason' => 'neden'])->validate();

        $admin = PlatformAdmin::where('is_active', true)->find($data['platform_admin_id']);
        if (! $admin) {
            throw new DomainException('Platform yöneticisini seçin.');
        }
        if ($this->activeFor($businessId, $admin->id)) {
            throw new DomainException('Bu yöneticinin zaten etkin bir destek erişimi var; önce kaldırın.');
        }

        $grant = SupportAccessGrant::create([
            'business_id' => $businessId, 'platform_admin_id' => $admin->id, 'granted_by' => $grantedBy,
            'reason' => trim($data['reason']), 'expires_at' => now()->addHours((int) $data['hours']),
        ]);
        $this->audit->log('support.granted', $grant, [], ['admin' => $admin->name, 'hours' => (int) $data['hours']], businessId: $businessId);

        $business = Business::find($businessId);
        $content = ['title' => 'Destek erişimi verildi', 'intro' => "{$business?->display_name} size ".self::DURATIONS[(int) $data['hours']]
            ." süreyle salt okunur destek erişimi verdi.\n\nNeden: {$grant->reason}", 'action_url' => route('platform.businesses.show', $businessId), 'action_label' => 'İşletmeyi aç'];
        $to = $admin->email;
        dispatch(fn () => app(SystemMailer::class)->send($to, 'Destek erişimi: '.$business?->display_name, $content))->afterResponse();

        return $grant;
    }

    public function revoke(SupportAccessGrant $grant): void
    {
        if ($grant->revoked_at) {
            return;
        }
        $grant->update(['revoked_at' => now()]);
        $this->audit->log('support.revoked', $grant, [], ['admin' => $grant->loadMissing('admin')->admin?->name], businessId: $grant->business_id);
    }

    public function activeFor(int $businessId, int $adminId): ?SupportAccessGrant
    {
        return SupportAccessGrant::where('business_id', $businessId)->where('platform_admin_id', $adminId)
            ->whereNull('revoked_at')->where('expires_at', '>', now())->latest('id')->first();
    }

    /** Destek görünümünde bakılan bölüm işletmenin işlem geçmişine yazılır. */
    public function logView(SupportAccessGrant $grant, string $section): void
    {
        $this->audit->log('support.viewed', $grant, [], ['section' => $section], businessId: $grant->business_id);
    }
}
