<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum TransactionStatus: string
{
    use EnumToArray;

    case PENDING = 'pending';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case PROCESSING = 'processing';
    case CANCELLED = 'cancelled';

    public function color()
    {
        return match ($this) {
            self::PENDING => 'badge-soft-info',
            self::SUCCESS => 'badge-soft-success',
            self::FAILED => 'badge-soft-danger',
            self::REFUNDED => 'badge-soft-warning',
            self::PROCESSING => 'badge-soft-primary',
            self::CANCELLED => 'badge-soft-secondary',
        };
    }
}
