<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum ClaimStatus: string
{
    use EnumToArray;

    case PENDING = 'pending';
    case REVIEW = 'review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case DISBURSED = 'disbursed';
    case CANCELLED = 'cancelled';
    case IN_PROGRESS = 'in_progress';

    public function color()
    {
        return match ($this) {
            self::PENDING => 'badge-soft-info',
            self::REVIEW => 'badge-soft-warning',
            self::APPROVED => 'badge-soft-success',
            self::REJECTED => 'badge-soft-danger',
            self::DISBURSED => 'badge-soft-primary',
            self::CANCELLED => 'badge-soft-secondary',
            self::IN_PROGRESS => 'badge-soft-warning',
        };
    }
}
