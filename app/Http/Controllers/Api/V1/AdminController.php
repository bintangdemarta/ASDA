<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClaimRequest;
use App\Models\AiConversation;
use App\Models\CallSchedule;
use App\Models\InsurancePolicy;
use App\Models\User;
use App\Models\TransactionHistory;
use App\Enums\ClaimStatus;
use App\Enums\AiConversationStatus;
use App\Enums\CallStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Get admin dashboard summary
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user has admin privileges
        if (!$user->hasRole(['admin', 'superadmin', 'claim_officer', 'consultation_officer'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $summary = [
            'total_users' => User::count(),
            'total_active_users' => User::where('is_verified', true)->count(),
            'total_claims' => ClaimRequest::count(),
            'pending_claims' => ClaimRequest::where('status', ClaimStatus::PENDING)->count(),
            'review_claims' => ClaimRequest::where('status', ClaimStatus::REVIEW)->count(),
            'approved_claims' => ClaimRequest::where('status', ClaimStatus::APPROVED)->count(),
            'total_conversations' => AiConversation::count(),
            'escalated_conversations' => AiConversation::where('status', AiConversationStatus::ESCALATED)->count(),
            'total_scheduled_calls' => CallSchedule::count(),
            'upcoming_calls' => CallSchedule::where('status', CallStatus::SCHEDULED)
                ->where('scheduled_at', '>', now())
                ->count(),
            'total_policies' => InsurancePolicy::count(),
            'active_policies' => InsurancePolicy::where('status', 'active')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get all claim requests (admin view)
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllClaims(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole(['admin', 'superadmin', 'claim_officer'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $claims = ClaimRequest::with(['user', 'insurancePolicy'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $claims,
        ]);
    }

    /**
     * Get escalated AI conversations requiring admin attention
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEscalatedConversations(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole(['admin', 'superadmin', 'consultation_officer'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $conversations = AiConversation::where('status', AiConversationStatus::ESCALATED)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    /**
     * Get all scheduled calls requiring admin attention
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getScheduledCallsForAdmin(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole(['admin', 'superadmin', 'consultation_officer'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $calls = CallSchedule::where('admin_id', $user->id)
            ->with(['user'])
            ->orderBy('scheduled_at', 'asc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $calls,
        ]);
    }

    /**
     * Assign admin to handle escalated conversation
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignConversation(int $id, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole(['admin', 'superadmin', 'consultation_officer'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $conversation = AiConversation::findOrFail($id);

        // Update to assign the current admin
        $conversation->update([
            'escalated_to_admin_id' => $user->id,
            'status' => AiConversationStatus::IN_PROGRESS,
            'escalated_at' => $conversation->escalated_at ?? now(), // Set escalated_at if not already set
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Conversation assigned successfully.',
            'data' => $conversation->load(['user', 'escalatedAdmin']),
        ]);
    }

    /**
     * Update conversation with resolution
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateConversationResolution(int $id, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole(['admin', 'superadmin', 'consultation_officer'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $request->validate([
            'resolution' => 'required|string|max:2000',
        ]);

        $conversation = AiConversation::findOrFail($id);

        $conversation->update([
            'resolution' => $request->resolution,
            'status' => AiConversationStatus::COMPLETED,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Conversation resolved successfully.',
            'data' => $conversation->load(['user', 'escalatedAdmin']),
        ]);
    }

    /**
     * Get reports for admin dashboard
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReports(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole(['admin', 'superadmin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $request->validate([
            'type' => 'nullable|string|in:claim_summary,consultation_report,transaction_summary,policy_summary,fund_disbursement,dashboard_report',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Example report: Claims by status
        $claimsByStatus = DB::table('claim_requests')
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->map(fn($item) => $item->count);

        // Example report: Claims by month
        $claimsByMonth = DB::table('claim_requests')
            ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'), DB::raw('count(*) as count'))
            ->whereBetween('created_at', [
                $request->start_date ?? now()->subMonths(6),
                $request->end_date ?? now()
            ])
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
            ->orderBy('month')
            ->get();

        // Example report: Total transactions by type
        $transactionsByType = DB::table('transaction_histories')
            ->select('transaction_type', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
            ->whereBetween('transaction_date', [
                $request->start_date ?? now()->subMonths(6),
                $request->end_date ?? now()
            ])
            ->groupBy('transaction_type')
            ->get();

        $reports = [
            'claims_by_status' => $claimsByStatus,
            'claims_by_month' => $claimsByMonth,
            'transactions_summary' => $transactionsByType,
            'total_claims' => ClaimRequest::count(),
            'total_users' => User::count(),
            'total_conversations' => AiConversation::count(),
            'total_policies' => InsurancePolicy::count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * Get user management data
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsers(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole(['admin', 'superadmin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $users = User::with(['insurancePolicies', 'claimRequests'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }
}
