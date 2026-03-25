<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_plan_version_id',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'lesson_plan_version_id' => 'integer',
            'requested_by_user_id' => 'integer',
            'resolved_by_user_id' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LessonPlanVersion::class, 'lesson_plan_version_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
