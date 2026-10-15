<?php

namespace App\Domain\Audit;

use App\Domain\Auth\Area;
use App\Domain\Tenancy\TenantContext;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Kritik işlemleri kim, ne zaman, neyi değiştirdi bilgisiyle kaydeder. */
class AuditLogger
{
    /** Değerleri hiçbir zaman denetim kaydına yazılmayan alanlar. */
    private const MASKED = ['password', 'remember_token', 'two_factor_secret', 'app_password', 'app_password_encrypted', 'token', 'token_hash'];

    public function __construct(private Request $request, private TenantContext $tenant) {}

    public function log(
        string $action,
        ?Model $entity = null,
        array $old = [],
        array $new = [],
        ?int $businessId = null,
        ?Area $actorArea = null,
        ?int $actorId = null,
    ): AuditLog {
        [$area, $id] = $actorArea ? [$actorArea, $actorId] : $this->currentActor();

        return AuditLog::create([
            'business_id' => $businessId ?? ($this->tenant->hasBusiness() ? $this->tenant->businessId() : null),
            'actor_type' => $area?->value,
            'actor_id' => $id,
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'old_values' => $old ? $this->mask($old) : null,
            'new_values' => $new ? $this->mask($new) : null,
            'ip' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 250, ''),
        ]);
    }

    /** @return array{0: ?Area, 1: ?int} */
    private function currentActor(): array
    {
        $area = Area::fromRequest($this->request);

        if ($area && Auth::guard($area->guard())->check()) {
            return [$area, Auth::guard($area->guard())->id()];
        }

        return [null, null];
    }

    private function mask(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::MASKED, true)) {
                $values[$key] = '***';
            }
        }

        return $values;
    }
}
