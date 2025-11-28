<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'insurance_policy_id',
        'claim_number',
        'claim_type',
        'status',
        'claim_amount',
        'reason',
        'description',
        'documents',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'disbursed_at',
        'reviewer_notes',
        'admin_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime',
        'claim_amount' => 'decimal:2',
        'documents' => 'array',
        'status' => ClaimStatus::class,
    ];

    /**
     * Get the user that owns the claim request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the insurance policy associated with the claim request.
     */
    public function insurancePolicy(): BelongsTo
    {
        return $this->belongsTo(InsurancePolicy::class, 'insurance_policy_id');
    }

    /**
     * Get the transaction history for this claim.
     */
    public function transactionHistory()
    {
        return $this->hasOne(TransactionHistory::class, 'claim_request_id');
    }
}
