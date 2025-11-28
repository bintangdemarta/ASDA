<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum UserRole: string
{
    use EnumToArray;

    case TNI_MEMBER = 'tni_member';
    case ADMIN = 'admin';
    case SUPER_ADMIN = 'super_admin';
    case CLAIM_OFFICER = 'claim_officer';
    case CONSULTATION_OFFICER = 'consultation_officer';

    public function label(): string
    {
        return match ($this) {
            self::TNI_MEMBER => 'TNI Member',
            self::ADMIN => 'Admin',
            self::SUPER_ADMIN => 'Super Admin',
            self::CLAIM_OFFICER => 'Claim Officer',
            self::CONSULTATION_OFFICER => 'Consultation Officer',
        };
    }

    public function isTniMember(): bool
    {
        return $this === self::TNI_MEMBER;
    }

    public function isAdmin(): bool
    {
        return in_array($this, [self::ADMIN, self::SUPER_ADMIN, self::CLAIM_OFFICER, self::CONSULTATION_OFFICER]);
    }
}
