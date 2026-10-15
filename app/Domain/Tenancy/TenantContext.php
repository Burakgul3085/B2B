<?php

namespace App\Domain\Tenancy;

/**
 * İsteğin hangi toptancı işletmesi (ve bayi tarafında hangi bayi ilişkisi) adına
 * çalıştığını tutar. Değerler yalnızca oturumdan kurulur; URL veya formdan alınmaz.
 */
class TenantContext
{
    private ?int $businessId = null;

    private ?int $dealerRelationshipId = null;

    public function set(int $businessId, ?int $dealerRelationshipId = null): void
    {
        $this->businessId = $businessId;
        $this->dealerRelationshipId = $dealerRelationshipId;
    }

    public function clear(): void
    {
        $this->businessId = null;
        $this->dealerRelationshipId = null;
    }

    public function hasBusiness(): bool
    {
        return $this->businessId !== null;
    }

    public function businessId(): int
    {
        return $this->businessId ?? throw new MissingTenantContext;
    }

    public function dealerRelationshipId(): ?int
    {
        return $this->dealerRelationshipId;
    }

    /**
     * Kuyruk işleri, seed ve testler için: verilen işletme bağlamında çalıştırır,
     * sonra önceki bağlamı geri yükler.
     */
    public function runAs(int $businessId, callable $callback, ?int $dealerRelationshipId = null): mixed
    {
        [$prevBusiness, $prevRelationship] = [$this->businessId, $this->dealerRelationshipId];
        $this->set($businessId, $dealerRelationshipId);

        try {
            return $callback();
        } finally {
            $this->businessId = $prevBusiness;
            $this->dealerRelationshipId = $prevRelationship;
        }
    }
}
