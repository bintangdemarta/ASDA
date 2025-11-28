<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum CallStatus: string
{
    use EnumToArray;

    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case MISSED = 'missed';
    case RESCHEDULED = 'rescheduled';

    public function color()
    {
        return match ($this) {
            self::SCHEDULED => 'badge-soft-info',
            self::IN_PROGRESS => 'badge-soft-warning',
            self::COMPLETED => 'badge-soft-success',
            self::CANCELLED => 'badge-soft-danger',
            self::MISSED => 'badge-soft-secondary',
            self::RESCHEDULED => 'badge-soft-primary',
        };
    }
}
