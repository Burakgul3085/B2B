<?php

namespace App\Domain\Ordering;

use DomainException;

/**
 * Sipariş durum geçişleri (docs/03-is-kurallari.md §3.1). Tanımsız her geçiş reddedilir.
 * "Taslak" durumu yoktur: sepet taslaktır (K1).
 */
final class OrderStateMachine
{
    public const TRANSITIONS = [
        'submitted' => ['in_review', 'approved', 'rejected', 'cancelled'],
        'in_review' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['preparing', 'cancel_requested', 'cancelled', 'completed'],
        'preparing' => ['completed', 'cancel_requested', 'cancelled'],
        'cancel_requested' => ['cancelled', 'approved', 'preparing'],
        'completed' => [],
        'rejected' => [],
        'cancelled' => [],
    ];

    public static function assert(string $from, string $to): void
    {
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new DomainException('Sipariş bu durumdayken bu işlem yapılamaz.');
        }
    }

    public static function can(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
