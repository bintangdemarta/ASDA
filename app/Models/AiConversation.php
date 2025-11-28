<?php

namespace App\Models;

use App\Enums\AiConversationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiConversation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'user_input',
        'ai_response',
        'intent',
        'context',
        'status',
        'escalated_at',
        'escalated_to_admin_id',
        'resolution',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'context' => 'array',
        'escalated_at' => 'datetime',
        'status' => AiConversationStatus::class,
    ];

    /**
     * Get the user that owns the conversation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin user who handled the escalation.
     */
    public function escalatedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to_admin_id');
    }
}
