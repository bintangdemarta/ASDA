<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'type',
        'format',
        'description',
        'filters',
        'status',
        'file_path',
        'file_name',
        'file_size',
        'generated_at',
        'sent_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'filters' => 'array',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'file_size' => 'decimal:2',
    ];

    /**
     * Get the display label for the report type.
     */
    public function getTypeLabelAttribute(): string
    {
        $reportType = ReportType::tryFrom($this->type);
        return $reportType?->label() ?? $this->type;
    }

    /**
     * Get the user that owns the report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
