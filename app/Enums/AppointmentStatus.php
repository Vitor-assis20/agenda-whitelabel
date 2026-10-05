<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';

    /**
     * Status que ocupam o horário do profissional.
     *
     * Deve espelhar o WHERE da constraint appointments_no_overlap.
     *
     * @return list<self>
     */
    public static function blocking(): array
    {
        return [self::Pending, self::Confirmed];
    }

    public function blocksSchedule(): bool
    {
        return in_array($this, self::blocking(), true);
    }
}
