<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum AiConversationStatus: string
{
    use EnumToArray;

    case COMPLETED = 'completed';
    case ESCALATED = 'escalated';
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';

    public function color()
    {
        return match ($this) {
            self::COMPLETED => 'badge-soft-success',
            self::ESCALATED => 'badge-soft-warning',
            self::PENDING => 'badge-soft-info',
            self::IN_PROGRESS => 'badge-soft-primary',
        };
    }
}
