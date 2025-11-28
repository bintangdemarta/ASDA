<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InsurancePolicy;
use App\Models\ClaimRequest;
use App\Models\AiConversation;
use App\Models\CallSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerDashboardController extends Controller
{
    /**
     * Get customer dashboard summary
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $dashboardData = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'asabri_member_number' => $user->asabri_member_number,
                'tni_rank' => $user->tni_rank,
                'tni_unit' => $user->tni_unit,
                'is_verified' => $user->is_verified,
            ],
            'policies' => [
                'total' => $user->insurancePolicies()->count(),
                'active' => $user->insurancePolicies()->where('status', 'active')->count(),
                'recent' => $user->insurancePolicies()->latest()->take(5)->get(),
            ],
            'claims' => [
                'total' => $user->claimRequests()->count(),
                'pending' => $user->claimRequests()->where('status', 'pending')->count(),
                'approved' => $user->claimRequests()->where('status', 'approved')->count(),
                'recent' => $user->claimRequests()->latest()->take(5)->get(),
            ],
            'conversations' => [
                'total' => $user->aiConversations()->count(),
                'recent' => $user->aiConversations()->latest()->take(5)->get(),
            ],
            'calls' => [
                'total' => $user->callSchedules()->count(),
                'upcoming' => $user->callSchedules()->where('status', 'scheduled')
                    ->where('scheduled_at', '>', now())
                    ->orderBy('scheduled_at')
                    ->take(5)
                    ->get(),
            ],
            'notifications' => [
                'unread_count' => 0, // Implement notification system later
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $dashboardData,
        ]);
    }

    /**
     * Get recent insurance policies
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function policies(Request $request): JsonResponse
    {
        $user = $request->user();
        $policies = $user->insurancePolicies()
            ->with(['claimRequests'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $policies,
        ]);
    }

    /**
     * Get recent claim requests
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function claims(Request $request): JsonResponse
    {
        $user = $request->user();
        $claims = $user->claimRequests()
            ->with(['insurancePolicy'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $claims,
        ]);
    }

    /**
     * Get recent AI conversations
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversations = $user->aiConversations()
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    /**
     * Get upcoming call schedules
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calls(Request $request): JsonResponse
    {
        $user = $request->user();
        $calls = $user->callSchedules()
            ->orderBy('scheduled_at', 'asc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $calls,
        ]);
    }

    /**
     * Get insurance policy by ID
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function policyDetail(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $policy = $user->insurancePolicies()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $policy->load(['claimRequests']),
        ]);
    }

    /**
     * Get claim request by ID
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function claimDetail(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $claim = $user->claimRequests()->with(['insurancePolicy'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $claim,
        ]);
    }
}
