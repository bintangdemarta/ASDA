<?php

namespace App\Services;

class TniValidationService
{
    /**
     * Validate ASABRI member number format
     * 
     * @param string $memberNumber
     * @return bool
     */
    public function validateAsabriMemberNumber(string $memberNumber): bool
    {
        // ASABRI member number is typically in format: AB-XXXXXXXX (where X are digits)
        // or could be numbers only, depending on ASABRI standards
        $pattern = '/^[A-Z]{2}-?\d{8}$/';
        
        return preg_match($pattern, $memberNumber) === 1;
    }

    /**
     * Validate TNI ID number format
     * 
     * @param string $tniIdNumber
     * @return bool
     */
    public function validateTniIdNumber(string $tniIdNumber): bool
    {
        // TNI ID number is typically 16 digits
        $pattern = '/^\d{16}$/';
        
        return preg_match($pattern, $tniIdNumber) === 1;
    }

    /**
     * Simulate verification against ASABRI database
     * 
     * @param string $memberNumber
     * @param string $tniIdNumber
     * @return bool
     */
    public function verifyTniMember(string $memberNumber, string $tniIdNumber): bool
    {
        // This would typically call an external ASABRI verification API
        // For now, we'll implement a mock verification
        return $this->validateAsabriMemberNumber($memberNumber) && 
               $this->validateTniIdNumber($tniIdNumber) &&
               // Simulate successful verification for demo purposes
               true;
    }

    /**
     * Get TNI member details from ASABRI database
     * 
     * @param string $memberNumber
     * @return array|null
     */
    public function getTniMemberDetails(string $memberNumber): ?array
    {
        // This would typically call an external ASABRI API
        // For demo, return mock data
        if ($this->validateAsabriMemberNumber($memberNumber)) {
            return [
                'rank' => 'Letda',
                'unit' => 'Kesatuan Matra Darat',
                'name' => 'John Doe',
                'status' => 'active',
                'enrollment_date' => now()->subYears(5)->format('Y-m-d'),
            ];
        }

        return null;
    }
}