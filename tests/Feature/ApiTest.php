<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\InsurancePolicy;
use App\Models\ClaimRequest;
use App\Models\AiConversation;
use App\Models\CallSchedule;
use App\Enums\ClaimStatus;
use App\Enums\AiConversationStatus;
use App\Enums\CallStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_complete_application_flow(): void
    {
        // 1. Test user registration (TNI member)
        $userData = [
            'name' => 'Test TNI Member',
            'email' => 'test' . time() . '@example.com', // Make unique with timestamp
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'asabri_member_number' => 'AB-' . rand(10000000, 99999999),
            'tni_id_number' => rand(1000000000000000, 9999999999999999),
        ];

        $user = User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => Hash::make($userData['password']),
            'asabri_member_number' => $userData['asabri_member_number'],
            'tni_id_number' => $userData['tni_id_number'],
            'tni_rank' => 'Letda',
            'tni_unit' => 'Kesatuan Matra Darat',
            'is_verified' => true,
            'email_verified_at' => now(), // Verify the email to allow login
        ]);

        $user->assignRole('tni_member');

        // Verify user was created
        $this->assertNotNull($user);
        $this->assertEquals($userData['asabri_member_number'], $user->asabri_member_number);

        // 2. Test authentication
        $response = $this->postJson('/api/v1/login', [
            'email' => $userData['asabri_member_number'], // Using ASABRI member number for login
            'password' => $userData['password'],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Extract token from response
        $token = $response->json()['token'];
        $this->assertIsString($token);

        // 3. Test creating an insurance policy
        $policyData = [
            'policy_number' => 'POL-2025-001',
            'policy_type' => 'health',
            'status' => \App\Enums\PolicyStatus::ACTIVE,
            'premium_amount' => 500000,
            'coverage_amount' => 100000000,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ];

        $policy = $user->insurancePolicies()->create($policyData);
        $this->assertNotNull($policy);
        $this->assertEquals($policyData['policy_number'], $policy->policy_number);

        // 4. Test creating a claim request
        $claimData = [
            'insurance_policy_id' => $policy->id,
            'claim_type' => 'medical_expense',
            'claim_amount' => 5000000,
            'reason' => 'medical treatment',
            'description' => 'Medical treatment for injury',
        ];

        $claim = $user->claimRequests()->create([
            'insurance_policy_id' => $claimData['insurance_policy_id'],
            'claim_number' => 'CLM-2025-001',
            'claim_type' => $claimData['claim_type'],
            'status' => ClaimStatus::PENDING->value,
            'claim_amount' => $claimData['claim_amount'],
            'reason' => $claimData['reason'],
            'description' => $claimData['description'],
            'submitted_at' => now(),
        ]);

        $this->assertNotNull($claim);
        $this->assertEquals($claimData['claim_type'], $claim->claim_type);
        $this->assertEquals(ClaimStatus::PENDING, $claim->status);

        // 5. Test creating an AI conversation
        $conversation = $user->aiConversations()->create([
            'user_input' => 'Apa status klaim saya?',
            'ai_response' => 'Status klaim Anda sedang dalam proses review.',
            'intent' => 'claim_status',
            'status' => AiConversationStatus::COMPLETED->value,
        ]);

        $this->assertNotNull($conversation);
        $this->assertEquals('claim_status', $conversation->intent);

        // 6. Test creating a call schedule
        $callData = [
            'title' => 'Konsultasi Klaim',
            'description' => 'Konsultasi mengenai status klaim',
            'scheduled_at' => now()->addDays(2),
        ];

        $call = $user->callSchedules()->create([
            'title' => $callData['title'],
            'description' => $callData['description'],
            'scheduled_at' => $callData['scheduled_at'],
            'status' => CallStatus::SCHEDULED->value,
        ]);

        $this->assertNotNull($call);
        $this->assertEquals($callData['title'], $call->title);
        $this->assertEquals(CallStatus::SCHEDULED, $call->status);

        // 7. Test customer dashboard endpoint
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/customer/dashboard');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'user' => [
                    'name' => $userData['name'],
                    'asabri_member_number' => $userData['asabri_member_number'],
                    'is_verified' => true,
                ],
                'policies' => [
                    'total' => 1,
                ],
                'claims' => [
                    'total' => 1,
                ],
            ],
        ]);

        // 8. Test getting user notifications
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/notifications');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // 9. Test admin dashboard (with admin user)
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin' . time() . '@example.com', // Make unique with timestamp
            'password' => Hash::make('password123'),
            'is_verified' => true,
            'email_verified_at' => now(), // Verify the email to allow login
        ]);

        $adminUser->assignRole('admin');

        $response = $this->postJson('/api/v1/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $adminToken = $response->json()['token'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $adminToken,
        ])->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'total_users' => 2, // Including admin and regular user
                'total_claims' => 1,
            ],
        ]);

        // 10. Test updating claim status (admin function)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $adminToken,
        ])->putJson("/api/v1/claims/{$claim->id}/status", [
            'status' => 'approved',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'status' => 'approved',
            ],
        ]);

        $claim->refresh();
        $this->assertEquals(ClaimStatus::APPROVED, $claim->status);

        echo "All tests passed! The ASDA application flow is working correctly.";
    }
}
