<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TniValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;

class TniAuthController extends Controller
{
    protected TniValidationService $tniValidationService;

    public function __construct(TniValidationService $tniValidationService)
    {
        $this->tniValidationService = $tniValidationService;
    }

    /**
     * Register a new TNI member
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|string|min:8|confirmed',
            'asabri_member_number' => 'required|string|unique:users,asabri_member_number|max:50',
            'tni_id_number' => 'required|string|unique:users,tni_id_number|max:50',
        ]);

        // Verify the TNI member details with ASABRI
        $isVerified = $this->tniValidationService->verifyTniMember(
            $request->asabri_member_number,
            $request->tni_id_number
        );

        if (!$isVerified) {
            throw ValidationException::withMessages([
                'asabri_member_number' => ['The provided ASABRI member number and TNI ID do not match our records.'],
            ]);
        }

        // Get additional TNI member details
        $tniDetails = $this->tniValidationService->getTniMemberDetails($request->asabri_member_number);

        // Create the user account
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'username' => Str::slug($request->name) . '_' . Str::random(6),
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'asabri_member_number' => $request->asabri_member_number,
            'tni_rank' => $tniDetails['rank'] ?? null,
            'tni_unit' => $tniDetails['unit'] ?? null,
            'tni_id_number' => $request->tni_id_number,
            'enrollment_date' => $tniDetails['enrollment_date'] ?? null,
            'is_verified' => true,
            'api_token' => Str::uuid()->toString(),
        ]);

        // Assign role as TNI member
        $user->assignRole('tni_member');

        return response()->json([
            'message' => 'TNI member registered successfully.',
            'user' => $user,
            'token' => $user->api_token,
        ], 201);
    }

    /**
     * Verify ASABRI member details
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyTniDetails(Request $request): JsonResponse
    {
        $request->validate([
            'asabri_member_number' => 'required|string|max:50',
            'tni_id_number' => 'required|string|max:50',
        ]);

        $isVerified = $this->tniValidationService->verifyTniMember(
            $request->asabri_member_number,
            $request->tni_id_number
        );

        if ($isVerified) {
            $tniDetails = $this->tniValidationService->getTniMemberDetails($request->asabri_member_number);

            return response()->json([
                'verified' => true,
                'member_details' => $tniDetails,
            ]);
        }

        return response()->json([
            'verified' => false,
            'message' => 'ASABRI member number and TNI ID do not match our records.',
        ], 400);
    }

    /**
     * Check if ASABRI member number is already registered
     *
     * @param string $memberNumber
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkMemberNumber(string $memberNumber): JsonResponse
    {
        $user = User::where('asabri_member_number', $memberNumber)->first();

        if ($user) {
            return response()->json([
                'exists' => true,
                'message' => 'This ASABRI member number is already registered.',
            ]);
        }

        return response()->json([
            'exists' => false,
            'message' => 'This ASABRI member number is not yet registered.',
        ]);
    }
}
