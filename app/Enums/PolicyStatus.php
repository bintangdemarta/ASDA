<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum PolicyStatus: string
{
    use EnumToArray;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case PENDING = 'pending';
    case SUSPENDED = 'suspended';

    public function color()
    {
        return match ($this) {
            self::ACTIVE => 'badge-soft-success',
            self::INACTIVE => 'badge-soft-secondary',
            self::EXPIRED => 'badge-soft-danger',
            self::CANCELLED => 'badge-soft-warning',
            self::PENDING => 'badge-soft-info',
            self::SUSPENDED => 'badge-soft-dark',
        };
    }
}
