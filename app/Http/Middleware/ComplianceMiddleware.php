<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ComplianceMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If not authenticated, skip compliance checks
        if (!$user) {
            return $next($request);
        }

        // Verify user compliance status
        if (!$this->isUserCompliant($user)) {
            Log::warning('Non-compliant user attempted access', [
                'user_id' => $user->id,
                'asabri_member_number' => $user->asabri_member_number,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Your account does not meet compliance requirements. Please contact support.',
            ], 403);
        }

        // Log access for audit trail
        $this->logAccess($user, $request);

        return $next($request);
    }

    /**
     * Check if user meets compliance requirements
     *
     * @param User $user
     * @return bool
     */
    private function isUserCompliant(User $user): bool
    {
        // Check if user is verified TNI member
        if (!$user->is_verified) {
            return false;
        }

        // Check if user has valid ASABRI membership
        if (empty($user->asabri_member_number)) {
            return false;
        }

        // Check if user's enrollment is still valid (not expired)
        // In a real implementation, this would check against actual expiration dates
        if ($user->enrollment_date && $user->enrollment_date->lt(now()->subYears(10))) {
            // If enrolled more than 10 years ago, might need verification
            // This is configurable based on policy
        }

        // Check if user is active (not banned or blocked)
        if (in_array($user->status->value, ['Block', 'Banned', 'Rejected'])) {
            return false;
        }

        return true;
    }

    /**
     * Log access for audit trail
     *
     * @param User $user
     * @param Request $request
     * @return void
     */
    private function logAccess(User $user, Request $request): void
    {
        Log::info('User access logged for compliance', [
            'user_id' => $user->id,
            'asabri_member_number' => $user->asabri_member_number,
            'action' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now(),
        ]);
    }
}
