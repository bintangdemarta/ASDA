<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClaimRequest;
use App\Models\InsurancePolicy;
use App\Models\TransactionHistory;
use App\Enums\ClaimStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClaimStatusController extends Controller
{
    /**
     * Get all claim requests for the authenticated user
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
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
     * Get a specific claim request
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $claim = $user->claimRequests()
            ->with(['insurancePolicy', 'transactionHistory'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $claim,
        ]);
    }

    /**
     * Create a new claim request
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'insurance_policy_id' => 'required|exists:insurance_policies,id',
            'claim_type' => 'required|string|max:100',
            'claim_amount' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:1000',
            'description' => 'nullable|string|max:2000',
            'documents' => 'nullable|array',
            'documents.*' => 'string|max:500', // Document file paths
        ]);

        // Check if the policy belongs to the user
        $policy = $user->insurancePolicies()->findOrFail($request->insurance_policy_id);

        // Validate claim amount against policy coverage
        if ($request->claim_amount > $policy->coverage_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Claim amount exceeds policy coverage amount.',
            ], 400);
        }

        $claim = ClaimRequest::create([
            'user_id' => $user->id,
            'insurance_policy_id' => $request->insurance_policy_id,
            'claim_number' => 'CLM-' . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'claim_type' => $request->claim_type,
            'status' => ClaimStatus::PENDING,
            'claim_amount' => $request->claim_amount,
            'reason' => $request->reason,
            'description' => $request->description,
            'documents' => $request->documents ?? [],
            'submitted_at' => now(),
        ]);

        // Create a transaction history record for the claim submission
        TransactionHistory::create([
            'user_id' => $user->id,
            'transaction_type' => 'claim_submission',
            'reference_number' => $claim->claim_number,
            'claim_request_id' => $claim->id,
            'insurance_policy_id' => $request->insurance_policy_id,
            'amount' => $request->claim_amount,
            'status' => 'submitted',
            'description' => 'Claim request submitted',
            'transaction_date' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Claim request submitted successfully.',
            'data' => $claim->load(['insurancePolicy']),
        ], 201);
    }

    /**
     * Update claim status (for admin use)
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if the user is an admin
        if (!in_array($user->role, ['admin', 'super_admin', 'claim_officer']) &&
            !$user->hasRole(['admin', 'superadmin', 'claim_officer'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,review,approved,rejected,disbursed,cancelled,in_progress',
            'reviewer_notes' => 'nullable|string|max:1000',
            'admin_id' => 'nullable|exists:users,id',
        ]);

        $claim = ClaimRequest::findOrFail($id);

        // Update claim status
        $oldStatus = $claim->status->value;
        $claim->update([
            'status' => $request->status,
            'reviewer_notes' => $request->reviewer_notes,
            'admin_id' => $request->admin_id ?? $user->id,
        ]);

        // Update timestamps based on status
        $updates = [];
        switch ($request->status) {
            case 'review':
                $updates['reviewed_at'] = now();
                break;
            case 'approved':
                $updates['approved_at'] = now();
                break;
            case 'disbursed':
                $updates['disbursed_at'] = now();
                break;
        }

        if (!empty($updates)) {
            $claim->update($updates);
        }

        // Create transaction history if status changed significantly
        if ($oldStatus !== $request->status) {
            $transactionType = 'claim_status_update';
            $description = "Claim status updated from {$oldStatus} to {$request->status}";

            if ($request->status === 'approved') {
                $transactionType = 'claim_approved';
                $description = 'Claim approved';
            } elseif ($request->status === 'disbursed') {
                $transactionType = 'fund_disbursed';
                $description = 'Fund disbursed';
            } elseif ($request->status === 'rejected') {
                $transactionType = 'claim_rejected';
                $description = 'Claim rejected';
            }

            TransactionHistory::create([
                'user_id' => $claim->user_id,
                'transaction_type' => $transactionType,
                'reference_number' => $claim->claim_number,
                'claim_request_id' => $claim->id,
                'insurance_policy_id' => $claim->insurance_policy_id,
                'amount' => $claim->claim_amount,
                'status' => $request->status,
                'description' => $description,
                'processed_by' => $user->id,
                'transaction_date' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Claim status updated successfully.',
            'data' => $claim->load(['user', 'insurancePolicy']),
        ]);
    }

    /**
     * Search claims by claim number
     *
     * @param string $claimNumber
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchByClaimNumber(string $claimNumber, Request $request): JsonResponse
    {
        $user = $request->user();
        $claim = $user->claimRequests()
            ->with(['insurancePolicy', 'transactionHistory'])
            ->where('claim_number', $claimNumber)
            ->first();

        if (!$claim) {
            return response()->json([
                'success' => false,
                'message' => 'Claim not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $claim,
        ]);
    }

    /**
     * Get claim timeline for a specific claim
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function timeline(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $claim = $user->claimRequests()->findOrFail($id);

        // Get transaction history for the claim
        $transactionHistory = $claim->transactionHistory()->orderBy('created_at', 'asc')->get();

        // Create timeline based on claim events
        $timeline = [];

        if ($claim->submitted_at) {
            $timeline[] = [
                'event' => 'Claim Submitted',
                'status' => 'submitted',
                'description' => 'Claim request has been submitted',
                'date' => $claim->submitted_at,
                'note' => $claim->description,
            ];
        }

        if ($claim->reviewed_at) {
            $timeline[] = [
                'event' => 'Claim Reviewed',
                'status' => 'review',
                'description' => 'Claim request has been reviewed',
                'date' => $claim->reviewed_at,
                'note' => $claim->reviewer_notes,
            ];
        }

        if ($claim->approved_at) {
            $timeline[] = [
                'event' => 'Claim Approved',
                'status' => 'approved',
                'description' => 'Claim request has been approved',
                'date' => $claim->approved_at,
                'note' => $claim->reviewer_notes,
            ];
        }

        if ($claim->disbursed_at) {
            $timeline[] = [
                'event' => 'Fund Disbursed',
                'status' => 'disbursed',
                'description' => 'Claim funds have been disbursed',
                'date' => $claim->disbursed_at,
                'note' => $claim->reviewer_notes,
            ];
        }

        // Add transaction history events
        foreach ($transactionHistory as $transaction) {
            $timeline[] = [
                'event' => 'Transaction: ' . $transaction->transaction_type,
                'status' => $transaction->status,
                'description' => $transaction->description,
                'date' => $transaction->transaction_date,
                'note' => $transaction->details ? json_encode($transaction->details) : null,
            ];
        }

        // Sort timeline by date
        usort($timeline, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'claim' => $claim,
                'timeline' => $timeline,
            ],
        ]);
    }
}
