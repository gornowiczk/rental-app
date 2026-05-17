<?php

namespace App\Enum;

final class ReservationStatus
{
    public const PENDING   = 'pending';
    public const ACCEPTED  = 'accepted';
    public const REJECTED  = 'rejected';
    public const CANCELLED = 'cancelled';
    public const COMPLETED = 'completed';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::ACCEPTED,
            self::REJECTED,
            self::CANCELLED,
            self::COMPLETED,
        ];
    }

    /** Statusy, które BLOKUJĄ terminy w kalendarzu */
    public static function blocking(): array
    {
        return [
            self::PENDING,
            self::ACCEPTED,
            self::COMPLETED,
        ];
    }
}
