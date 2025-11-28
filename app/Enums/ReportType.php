<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum ReportType: string
{
    use EnumToArray;

    case CLAIM_SUMMARY = 'claim_summary';
    case CONSULTATION_REPORT = 'consultation_report';
    case TRANSACTION_SUMMARY = 'transaction_summary';
    case POLICY_SUMMARY = 'policy_summary';
    case FUND_DISBURSEMENT = 'fund_disbursement';
    case DASHBOARD_REPORT = 'dashboard_report';

    public function label(): string
    {
        return match ($this) {
            self::CLAIM_SUMMARY => 'Claim Summary',
            self::CONSULTATION_REPORT => 'Consultation Report',
            self::TRANSACTION_SUMMARY => 'Transaction Summary',
            self::POLICY_SUMMARY => 'Policy Summary',
            self::FUND_DISBURSEMENT => 'Fund Disbursement',
            self::DASHBOARD_REPORT => 'Dashboard Report',
        };
    }
}
