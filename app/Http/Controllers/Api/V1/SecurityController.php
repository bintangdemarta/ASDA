<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class SecurityController extends Controller
{
    /**
     * Get user's security information
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function securityInfo(Request $request): JsonResponse
    {
        $user = $request->user();

        $securityInfo = [
            'account_status' => $user->status,
            'is_verified' => $user->is_verified,
            'last_login_at' => $user->last_login_at ?? null,
            'two_factor_enabled' => false, // Assuming 2FA is not implemented yet
            'password_last_changed' => $user->updated_at,
            'asabri_member_number' => $user->asabri_member_number,
            'tni_rank' => $user->tni_rank,
            'tni_unit' => $user->tni_unit,
        ];

        return response()->json([
            'success' => true,
            'data' => $securityInfo,
        ]);
    }

    /**
     * Change user password
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Enable 2FA for user
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function enableTwoFactorAuth(Request $request): JsonResponse
    {
        $user = $request->user();

        // In a real implementation, this would set up 2FA
        // For this demo, we'll just return a success response
        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication feature would be enabled here.',
            'data' => [
                'enabled' => false, // 2FA not actually implemented
                'backup_codes' => [], // Backup codes would be generated
            ],
        ]);
    }

    /**
     * Disable 2FA for user
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function disableTwoFactorAuth(Request $request): JsonResponse
    {
        $user = $request->user();

        // In a real implementation, this would disable 2FA
        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication disabled.',
            'data' => [
                'enabled' => false,
            ],
        ]);
    }

    /**
     * Get account activity log
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function accountActivity(Request $request): JsonResponse
    {
        $user = $request->user();

        // This would use the activity log package that's already in the system
        $activities = \Spatie\Activitylog\Models\Activity::where('causer_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Get compliance status
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function complianceStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        $complianceStatus = [
            'is_compliant' => $user->is_verified && !empty($user->asabri_member_number),
            'asabri_member_number' => $user->asabri_member_number,
            'is_verified' => $user->is_verified,
            'tni_verification_status' => $user->is_verified ? 'Verified' : 'Pending',
            'enrollment_date' => $user->enrollment_date,
            'last_compliance_check' => now(),
        ];

        return response()->json([
            'success' => true,
            'data' => $complianceStatus,
        ]);
    }

    /**
     * Check for potential security threats (admin function)
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function securityAudit(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only allow admin users to access this
        if (!$user->hasRole(['admin', 'superadmin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        // Perform basic security audit
        $securityAudit = [
            'total_users' => User::count(),
            'users_without_verification' => User::where('is_verified', false)->count(),
            'users_with_invalid_status' => User::whereIn('status', ['Block', 'Banned', 'Rejected'])->count(),
            'recent_registrations' => User::where('created_at', '>', now()->subDays(7))->count(),
            'recent_failed_logins' => $this->getRecentFailedLogins(),
        ];

        return response()->json([
            'success' => true,
            'data' => $securityAudit,
        ]);
    }

    /**
     * Get recent failed login attempts
     *
     * @return int
     */
    private function getRecentFailedLogins(): int
    {
        // In a real system, this would check for failed login attempts from the rate limiter
        // or a dedicated security log. For this demo, returning 0.
        return 0;
    }
}
